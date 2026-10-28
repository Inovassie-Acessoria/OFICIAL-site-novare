<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Estoque.php';
require_once __DIR__ . '/XBZService.php';
require_once __DIR__ . '/ProductMapper.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/Seo.php';
require_once __DIR__ . '/IndexNow.php';

/**
 * Atualização de estoque pelo botão do painel (/settings-admin/estoque).
 *
 * Fluxo em duas etapas, para ter certeza antes de tirar qualquer coisa do site:
 *
 *  1. SIMULAR: lê a lista da XBZ, compara com o banco e grava o que mudaria
 *     (catalogo_atualizacoes + catalogo_mudancas). O site não muda nada.
 *  2. APLICAR: lê a XBZ DE NOVO. Um produto ou cor só sai do site se as DUAS
 *     leituras disserem que está abaixo do pedido mínimo. O que volta usa a
 *     leitura mais recente.
 *
 * Travas:
 *  - Bloqueio: a API devolveu menos de 70% das cores do banco, ou mais de 5%
 *    dos itens vieram sem número de estoque válido. Nada pode ser aplicado.
 *  - Confirmação: se for sair mais de 20% dos produtos que estão no ar
 *    (e pelo menos 25 produtos), aplicar exige confirmação explícita.
 *  - Simulação vale 30 minutos e só a mais recente pode ser aplicada.
 *
 * O que nunca acontece aqui: DELETE de produto, cor ou imagem. Produto fora
 * da API não é escondido; vai para a lista de conferência manual.
 */
final class EstoqueSync
{
    public const TRAVA_PERCENTUAL   = 20.0;  // % dos produtos no ar
    public const TRAVA_MIN_PRODUTOS = 25;    // abaixo disso a trava não dispara
    public const COBERTURA_MINIMA   = 70.0;  // % das cores do banco que precisam vir na API
    public const MAX_INVALIDOS      = 5.0;   // % de itens sem estoque legível
    public const VALIDADE_MINUTOS   = 30;

    /** @var callable():array<int,array<string,mixed>> */
    private $fetcher;

    /** @var (callable():PDO)|null abre uma conexão nova quando o MySQL derruba a atual */
    private $reconectar;

    public function __construct(private PDO $pdo, callable $fetcher, ?callable $reconectar = null)
    {
        $this->fetcher = $fetcher;
        $this->reconectar = $reconectar;
    }

    public static function create(): self
    {
        return new self(
            Database::connection(),
            static fn () => XBZService::fromEnv()->getListaDeProdutos(150),
            static function (): PDO {
                Database::reset();
                return Database::connection();
            }
        );
    }

    // ============================================================
    // 1. SIMULAR
    // ============================================================

    /** @return array{ok:bool,id:int,status:string,mensagem:string} */
    public function simular(string $usuario): array
    {
        return $this->comTrava(function () use ($usuario): array {
            $pdo = $this->pdo;
            // simulações antigas deixam de valer (UPDATE, nunca DELETE)
            $pdo->exec("UPDATE catalogo_atualizacoes SET status = 'descartada' WHERE status = 'simulada'");
            // guarda a leitura bruta só das 10 últimas (o resto do histórico continua)
            $corte = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM catalogo_atualizacoes')->fetchColumn() - 10;
            if ($corte > 0) {
                $pdo->prepare('UPDATE catalogo_atualizacoes SET leitura = NULL WHERE leitura IS NOT NULL AND id <= :c')->execute([':c' => $corte]);
            }

            // horários sempre pelo relógio do PHP (o MySQL da hospedagem pode estar em outro fuso)
            $pdo->prepare("INSERT INTO catalogo_atualizacoes (status, criado_em, usuario) VALUES ('simulada', :agora, :u)")
                ->execute([':agora' => self::agora(), ':u' => mb_substr($usuario, 0, 190)]);
            $id = (int) $pdo->lastInsertId();

            try {
                $leitura = $this->ler();
            } catch (Throwable $e) {
                $this->fechar($id, 'erro', 'Não consegui ler a XBZ: ' . $e->getMessage() . ' Nada foi alterado.');
                return ['ok' => false, 'id' => $id, 'status' => 'erro', 'mensagem' => 'Não consegui ler a XBZ: ' . $e->getMessage()];
            }
            $pdo = $this->pdo; // a leitura pode ter reaberto a conexão

            $plano = $this->planejar($leitura);
            $c = $plano['cont'];
            $bloqueio = $this->motivoBloqueio($leitura, $c);
            $exige = $this->exigeConfirmacao($c);

            $pdo->beginTransaction();
            try {
                $this->gravarMudancas($id, $plano['mudancas'], 'previsto');
                $pdo->prepare(
                    'UPDATE catalogo_atualizacoes SET status = :st, itens_api = :n, cobertura = :cob, produtos_no_ar = :ar,
                        saem = :s, voltam = :v, cores_saem = :cs, cores_voltam = :cv, fora_api = :fa, incertos = :inc,
                        exige_confirmacao = :ex, mensagem = :msg, leitura = :lei WHERE id = :id'
                )->execute([
                    ':st' => $bloqueio ? 'bloqueada' : 'simulada', ':n' => $leitura['total'], ':cob' => $c['cobertura'],
                    ':ar' => $c['no_ar'], ':s' => $c['saem'], ':v' => $c['voltam'], ':cs' => $c['cores_saem'],
                    ':cv' => $c['cores_voltam'], ':fa' => $c['fora_api'], ':inc' => $c['incertos'], ':ex' => (int) $exige,
                    ':msg' => $bloqueio, ':lei' => json_encode($leitura, JSON_UNESCAPED_UNICODE), ':id' => $id,
                ]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                $this->fechar($id, 'erro', 'Falha ao gravar a simulação: ' . $e->getMessage());
                return ['ok' => false, 'id' => $id, 'status' => 'erro', 'mensagem' => 'Falha ao gravar a simulação: ' . $e->getMessage()];
            }

            if ($bloqueio) {
                return ['ok' => false, 'id' => $id, 'status' => 'bloqueada', 'mensagem' => $bloqueio];
            }
            return ['ok' => true, 'id' => $id, 'status' => 'simulada', 'mensagem' => sprintf(
                'Simulação pronta: sairiam %d produtos e voltariam %d. Nada mudou no site ainda.', $c['saem'], $c['voltam']
            )];
        });
    }

    // ============================================================
    // 2. APLICAR (com segunda leitura)
    // ============================================================

    /** @return array{ok:bool,id:int,status:string,mensagem:string} */
    public function aplicar(int $id, bool $confirmado, string $usuario): array
    {
        return $this->comTrava(function () use ($id, $confirmado, $usuario): array {
            $pdo = $this->pdo;
            $run = $this->atualizacao($id);
            $erro = $this->motivoNaoAplicavel($run);
            if ($erro !== null) {
                return ['ok' => false, 'id' => $id, 'status' => (string) ($run['status'] ?? ''), 'mensagem' => $erro];
            }
            $leituraA = json_decode((string) $run['leitura'], true);
            if (!is_array($leituraA) || !isset($leituraA['m'])) {
                return ['ok' => false, 'id' => $id, 'status' => 'simulada', 'mensagem' => 'A leitura desta simulação não está mais disponível. Rode uma nova.'];
            }
            if ((int) $run['exige_confirmacao'] === 1 && !$confirmado) {
                return ['ok' => false, 'id' => $id, 'status' => 'simulada', 'mensagem' => 'Esta atualização tira muitos produtos de uma vez. Marque a confirmação para aplicar.'];
            }

            // 2a leitura
            try {
                $leituraB = $this->ler();
            } catch (Throwable $e) {
                return ['ok' => false, 'id' => $id, 'status' => 'simulada', 'mensagem' => 'A segunda leitura da XBZ falhou (' . $e->getMessage() . '). Nada foi alterado; tente aplicar de novo.'];
            }
            $pdo = $this->pdo; // a leitura pode ter reaberto a conexão

            $planoA = $this->planejar($leituraA);
            $planoB = $this->planejar($leituraB);
            $bloqueio = $this->motivoBloqueio($leituraB, $planoB['cont']);
            if ($bloqueio !== null) {
                $this->fechar($id, 'bloqueada', 'Segunda leitura: ' . $bloqueio);
                return ['ok' => false, 'id' => $id, 'status' => 'bloqueada', 'mensagem' => 'Segunda leitura da XBZ não passou na checagem: ' . $bloqueio . ' Nada foi alterado.'];
            }
            if ($this->exigeConfirmacao($planoB['cont']) && !$confirmado) {
                $pdo->prepare('UPDATE catalogo_atualizacoes SET exige_confirmacao = 1 WHERE id = :id')->execute([':id' => $id]);
                return ['ok' => false, 'id' => $id, 'status' => 'simulada', 'mensagem' => 'A segunda leitura tira muitos produtos de uma vez. Marque a confirmação para aplicar.'];
            }

            $final = $this->combinar($planoA, $planoB);

            $pdo->beginTransaction();
            try {
                $this->gravarEstado($final);
                $this->consolidarMudancas($id, $final['mudancas']);
                $c = $final['cont'];
                $pdo->prepare(
                    "UPDATE catalogo_atualizacoes SET status = 'aplicada', aplicado_em = :agora, confirmado = :cf,
                        usuario = :u, saem = :s, voltam = :v, cores_saem = :cs, cores_voltam = :cv, fora_api = :fa,
                        incertos = :inc, divergentes = :dv, cobertura = :cob, itens_api = :n, mensagem = NULL WHERE id = :id"
                )->execute([
                    ':agora' => self::agora(), ':cf' => (int) $confirmado, ':u' => mb_substr($usuario, 0, 190), ':s' => $c['saem'], ':v' => $c['voltam'],
                    ':cs' => $c['cores_saem'], ':cv' => $c['cores_voltam'], ':fa' => $c['fora_api'], ':inc' => $c['incertos'],
                    ':dv' => $c['divergentes'], ':cob' => $planoB['cont']['cobertura'], ':n' => $leituraB['total'], ':id' => $id,
                ]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                return ['ok' => false, 'id' => $id, 'status' => 'simulada', 'mensagem' => 'Falha ao gravar (nada foi alterado, rollback aplicado): ' . $e->getMessage()];
            }

            $this->depoisDeAplicar($final);
            $c = $final['cont'];
            return ['ok' => true, 'id' => $id, 'status' => 'aplicada', 'mensagem' => sprintf(
                'Estoque atualizado: %d produtos saíram, %d voltaram, %d cores saíram e %d voltaram.%s',
                $c['saem'], $c['voltam'], $c['cores_saem'], $c['cores_voltam'],
                $c['divergentes'] ? " {$c['divergentes']} ficaram no ar porque a segunda leitura não confirmou." : ''
            )];
        });
    }

    public function descartar(int $id): bool
    {
        $st = $this->pdo->prepare("UPDATE catalogo_atualizacoes SET status = 'descartada' WHERE id = :id AND status IN ('simulada','bloqueada')");
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }

    // ============================================================
    // Consultas usadas pelo painel e pelo relatório
    // ============================================================

    public function atualizacao(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM catalogo_atualizacoes WHERE id = :id');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    }

    /** Última simulação ainda aplicável (ou null). */
    public function pendente(): ?array
    {
        $run = $this->pdo->query("SELECT * FROM catalogo_atualizacoes WHERE status IN ('simulada','bloqueada') ORDER BY id DESC LIMIT 1")->fetch() ?: null;
        if ($run && $run['status'] === 'simulada' && $this->motivoNaoAplicavel($run) !== null) {
            return $run + ['expirada' => true];
        }
        return $run;
    }

    public function historico(int $limite = 30): array
    {
        return $this->pdo->query(
            'SELECT id, status, criado_em, aplicado_em, usuario, itens_api, cobertura, produtos_no_ar, saem, voltam,
                    cores_saem, cores_voltam, fora_api, incertos, divergentes, exige_confirmacao, confirmado, mensagem
             FROM catalogo_atualizacoes ORDER BY id DESC LIMIT ' . max(1, min(200, $limite))
        )->fetchAll();
    }

    /** @return array<string,array<int,array>> linhas agrupadas por tipo */
    public function mudancas(int $id, ?string $situacao = null): array
    {
        $sql = 'SELECT * FROM catalogo_mudancas WHERE atualizacao_id = :id';
        $p = [':id' => $id];
        if ($situacao !== null) {
            $sql .= ' AND situacao = :s';
            $p[':s'] = $situacao;
        }
        $st = $this->pdo->prepare($sql . ' ORDER BY categoria, nome, cor');
        $st->execute($p);
        $g = ['saiu' => [], 'voltou' => [], 'cor_saiu' => [], 'cor_voltou' => [], 'fora_api' => [], 'incerto' => []];
        foreach ($st->fetchAll() as $r) {
            $g[$r['tipo']][] = $r;
        }
        return $g;
    }

    /** Tudo que está fora do site hoje (produto inteiro esgotado). */
    public function foraDoArHoje(): array
    {
        return $this->pdo->query(
            "SELECT p.id, p.sku_pai, p.slug, p.nome, p.categoria, p.quantidade_minima, p.preco_base, p.esgotado_desde, p.estoque_total
             FROM produtos p WHERE p.ativo = 1 AND p.esgotado = 1 ORDER BY p.categoria, p.nome"
        )->fetchAll();
    }

    public function totalForaDoAr(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND esgotado = 1')->fetchColumn();
    }

    public function motivoNaoAplicavel(?array $run): ?string
    {
        if (!$run) {
            return 'Simulação não encontrada.';
        }
        if ($run['status'] !== 'simulada') {
            return 'Esta simulação está como "' . $run['status'] . '" e não pode ser aplicada.';
        }
        if (strtotime((string) $run['criado_em']) < time() - self::VALIDADE_MINUTOS * 60) {
            return 'Esta simulação tem mais de ' . self::VALIDADE_MINUTOS . ' minutos. Rode uma nova para conferir o estoque de agora.';
        }
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM catalogo_atualizacoes WHERE id > :id AND status IN ('simulada','aplicada')");
        $st->execute([':id' => $run['id']]);
        if ((int) $st->fetchColumn() > 0) {
            return 'Existe uma atualização mais recente. Use a última simulação.';
        }
        return null;
    }

    // ============================================================
    // Leitura da XBZ
    // ============================================================

    /**
     * Normaliza a resposta da XBZ.
     * m = [SKU-COR => estoque] só com número válido; i = SKU-COR sem estoque legível;
     * p = códigos-pai presentes; total = itens recebidos.
     */
    private function ler(): array
    {
        try {
            $itens = ($this->fetcher)();
        } finally {
            // a XBZ pode levar minutos; o MySQL da hospedagem derruba a conexão parada nesse meio-tempo
            $this->reconectarSeCaiu();
        }
        if (!is_array($itens) || count($itens) === 0) {
            throw new RuntimeException('a XBZ devolveu uma lista vazia.');
        }
        $m = [];
        $inv = [];
        $pais = [];
        foreach ($itens as $it) {
            if (!is_array($it)) {
                continue;
            }
            $sku = self::chave((string) ($it['CodigoComposto'] ?? ''));
            $pai = self::chave((string) ($it['CodigoAmigavel'] ?? ''));
            if ($pai === '' && $sku !== '') {
                $pai = self::chave((string) strtok($sku, '-'));
            }
            if ($pai !== '') {
                $pais[$pai] = 1;
            }
            if ($sku === '') {
                continue;
            }
            $q = $it['QuantidadeDisponivel'] ?? null;
            if (is_int($q) || is_float($q) || (is_string($q) && is_numeric(trim($q)))) {
                $q = max(0, (int) floor((float) $q));
                // código repetido na API: vale o MAIOR (nunca tira do ar por duplicidade)
                $m[$sku] = isset($m[$sku]) ? max($m[$sku], $q) : $q;
                unset($inv[$sku]);
            } elseif (!isset($m[$sku])) {
                $inv[$sku] = 1;
            }
        }
        return ['m' => $m, 'i' => $inv, 'p' => $pais, 'total' => count($itens)];
    }

    /** Testa a conexão e abre outra se o servidor encerrou a atual ("MySQL server has gone away"). */
    private function reconectarSeCaiu(): void
    {
        if ($this->reconectar === null) {
            return;
        }
        try {
            @$this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->pdo = ($this->reconectar)();
        }
    }

    private static function chave(string $s): string
    {
        return strtoupper(trim($s));
    }

    // ============================================================
    // Planejamento (puro: só lê o banco)
    // ============================================================

    /**
     * Compara uma leitura com o banco.
     *
     * estado do produto:
     *  disponivel  pelo menos uma cor com estoque >= mínimo
     *  esgotado    todas as cores lidas com estoque válido e abaixo do mínimo
     *  incerto     nenhuma cor disponível, mas alguma sem leitura confiável
     *  fora_api    nenhuma cor e nem o código-pai vieram na API
     */
    private function planejar(array $leitura): array
    {
        $m = $leitura['m'];
        $inv = $leitura['i'];
        $pais = $leitura['p'];

        $prods = [];
        foreach ($this->pdo->query(
            'SELECT id, sku_pai, slug, nome, categoria, quantidade_minima, preco_base, esgotado, estoque_total, fora_api_desde
             FROM produtos WHERE ativo = 1'
        ) as $p) {
            $p['vars'] = [];
            $prods[(int) $p['id']] = $p;
        }
        foreach ($this->pdo->query('SELECT id, produto_id, sku_completo, cor, estoque, esgotado FROM variacoes WHERE ativo = 1 ORDER BY id') as $v) {
            $pid = (int) $v['produto_id'];
            if (isset($prods[$pid])) {
                $prods[$pid]['vars'][(int) $v['id']] = $v;
            }
        }

        $cont = ['no_ar' => 0, 'saem' => 0, 'voltam' => 0, 'cores_saem' => 0, 'cores_voltam' => 0, 'fora_api' => 0, 'incertos' => 0, 'cobertura' => 0.0];
        $varsTotal = 0;
        $varsAchadas = 0;
        $estados = [];
        $mud = [];

        foreach ($prods as $pid => $p) {
            if ((int) $p['esgotado'] === 0) {
                $cont['no_ar']++;
            }
            if (!$p['vars']) {
                continue; // sem cor cadastrada: não há o que avaliar
            }
            $min = Estoque::minimo($p);
            $disp = 0;
            $abaixo = 0;
            $incertas = 0;
            $soma = 0;
            $vEst = [];
            $resumoCores = [];
            foreach ($p['vars'] as $vid => $v) {
                $k = self::chave((string) $v['sku_completo']);
                $varsTotal++;
                if (isset($m[$k])) {
                    $varsAchadas++;
                    $q = $m[$k];
                    $soma += $q;
                    $esg = $q < $min;
                    $esg ? $abaixo++ : $disp++;
                    $vEst[$vid] = ['valida' => true, 'qtd' => $q, 'esgotada' => $esg];
                    $resumoCores[] = ($v['cor'] ?: $v['sku_completo']) . ': ' . number_format($q, 0, ',', '.');
                } else {
                    if (isset($inv[$k])) {
                        $varsAchadas++;
                    }
                    $incertas++;
                    $vEst[$vid] = ['valida' => false, 'qtd' => null, 'esgotada' => null];
                    $resumoCores[] = ($v['cor'] ?: $v['sku_completo']) . ': ' . (isset($inv[$k]) ? 'sem número' : 'não veio');
                }
            }

            $paiNaApi = isset($pais[self::chave((string) $p['sku_pai'])]);
            if ($disp > 0) {
                $estado = 'disponivel';
            } elseif ($incertas === 0) {
                $estado = 'esgotado';
            } elseif ($abaixo === 0 && !$paiNaApi && count(array_filter($p['vars'], static fn ($v) => isset($inv[self::chave((string) $v['sku_completo'])]))) === 0) {
                $estado = 'fora_api';
            } else {
                $estado = 'incerto';
            }

            $estados[$pid] = ['estado' => $estado, 'vars' => $vEst, 'soma' => $incertas === count($p['vars']) ? null : $soma, 'min' => $min];
            $base = [
                'produto_id' => $pid, 'sku_pai' => $p['sku_pai'], 'nome' => $p['nome'], 'categoria' => $p['categoria'],
                'quantidade_minima' => $min, 'estoque_anterior' => $p['estoque_total'] !== null ? (int) $p['estoque_total'] : self::somaAnterior($p),
                'estoque_atual' => $estados[$pid]['soma'], 'detalhe' => implode(' · ', $resumoCores),
            ];
            $esgotadoHoje = (int) $p['esgotado'] === 1;

            if ($estado === 'esgotado' && !$esgotadoHoje) {
                $mud[] = $base + ['tipo' => 'saiu'];
                $cont['saem']++;
            } elseif ($estado === 'disponivel' && $esgotadoHoje) {
                $mud[] = $base + ['tipo' => 'voltou'];
                $cont['voltam']++;
            } elseif ($estado === 'fora_api') {
                $desde = $p['fora_api_desde'] ? date('d/m/Y', strtotime((string) $p['fora_api_desde'])) : 'hoje';
                $mud[] = ['tipo' => 'fora_api', 'detalhe' => 'Nenhuma cor nem o código do produto vieram na API (desde ' . $desde . '). Continua no site como está.'] + $base;
                $cont['fora_api']++;
            } elseif ($estado === 'incerto' && !$esgotadoHoje) {
                $motivo = $paiNaApi && $abaixo === 0
                    ? 'O produto está na API, mas com códigos de cor diferentes do banco (rode a importação completa). '
                    : 'Nenhuma cor com estoque suficiente, mas nem todas tiveram leitura confiável. Ficou no ar. ';
                $mud[] = ['tipo' => 'incerto', 'detalhe' => $motivo . implode(' · ', $resumoCores)] + $base;
                $cont['incertos']++;
            }

            // cores: só reporta individualmente quando o produto continua no ar
            if ($estado === 'disponivel' && !$esgotadoHoje) {
                foreach ($p['vars'] as $vid => $v) {
                    $e = $vEst[$vid];
                    if (!$e['valida']) {
                        continue;
                    }
                    $linha = [
                        'produto_id' => $pid, 'sku_pai' => $p['sku_pai'], 'nome' => $p['nome'], 'categoria' => $p['categoria'],
                        'sku_completo' => $v['sku_completo'], 'cor' => $v['cor'], 'quantidade_minima' => $min,
                        'estoque_anterior' => (int) $v['estoque'], 'estoque_atual' => $e['qtd'],
                    ];
                    if ($e['esgotada'] && (int) $v['esgotado'] === 0) {
                        $mud[] = $linha + ['tipo' => 'cor_saiu'];
                        $cont['cores_saem']++;
                    } elseif (!$e['esgotada'] && (int) $v['esgotado'] === 1) {
                        $mud[] = $linha + ['tipo' => 'cor_voltou'];
                        $cont['cores_voltam']++;
                    }
                }
            }
        }

        $cont['cobertura'] = $varsTotal ? round($varsAchadas / $varsTotal * 100, 2) : 0.0;
        $cont['invalidos'] = $leitura['total'] ? round(count($inv) / $leitura['total'] * 100, 2) : 0.0;
        return ['estados' => $estados, 'prods' => $prods, 'mudancas' => $mud, 'cont' => $cont];
    }

    private static function somaAnterior(array $p): int
    {
        return array_sum(array_map(static fn ($v) => (int) $v['estoque'], $p['vars']));
    }

    private function motivoBloqueio(array $leitura, array $cont): ?string
    {
        if ($cont['cobertura'] < self::COBERTURA_MINIMA) {
            return sprintf('A XBZ devolveu só %s%% das cores cadastradas no site (mínimo %s%%). Parece resposta incompleta; nada foi aplicado.',
                number_format($cont['cobertura'], 1, ',', '.'), number_format(self::COBERTURA_MINIMA, 0));
        }
        if (($cont['invalidos'] ?? 0) > self::MAX_INVALIDOS) {
            return sprintf('%s%% dos itens vieram sem número de estoque legível (máximo %s%%). Parece mudança no formato da API; nada foi aplicado.',
                number_format((float) $cont['invalidos'], 1, ',', '.'), number_format(self::MAX_INVALIDOS, 0));
        }
        return null;
    }

    private function exigeConfirmacao(array $cont): bool
    {
        if ($cont['saem'] < self::TRAVA_MIN_PRODUTOS || $cont['no_ar'] === 0) {
            return false;
        }
        return $cont['saem'] / $cont['no_ar'] * 100 > self::TRAVA_PERCENTUAL;
    }

    // ============================================================
    // Combinação das duas leituras
    // ============================================================

    /**
     * Saída (produto ou cor) só com as DUAS leituras concordando.
     * Volta e números de estoque vêm da leitura mais recente (B).
     */
    private function combinar(array $a, array $b): array
    {
        $prods = $b['prods'];
        $alvo = [];      // pid => ['esgotado'=>?int, 'soma'=>?int, 'fora_api'=>bool, 'vars'=>[vid => ['qtd'=>int,'esgotado'=>?int]]]
        $mud = [];
        $cont = ['saem' => 0, 'voltam' => 0, 'cores_saem' => 0, 'cores_voltam' => 0, 'fora_api' => 0, 'incertos' => 0, 'divergentes' => 0];

        $chaveA = [];
        foreach ($a['mudancas'] as $r) {
            $chaveA[$r['tipo'] . '|' . $r['sku_pai'] . '|' . ($r['sku_completo'] ?? '')] = $r;
        }
        $chavesB = [];

        foreach ($b['estados'] as $pid => $eb) {
            $p = $prods[$pid];
            $ea = $a['estados'][$pid] ?? null;
            $esgHoje = (int) $p['esgotado'];
            $novo = $esgHoje;
            if ($eb['estado'] === 'disponivel') {
                $novo = 0;
            } elseif ($eb['estado'] === 'esgotado' && ($ea['estado'] ?? null) === 'esgotado') {
                $novo = 1;
            }
            $vars = [];
            foreach ($eb['vars'] as $vid => $vb) {
                if (!$vb['valida']) {
                    continue; // sem leitura confiável: não mexe na cor
                }
                $va = $ea['vars'][$vid] ?? ['valida' => false, 'esgotada' => null];
                $esgV = (int) $p['vars'][$vid]['esgotado'];
                if (!$vb['esgotada']) {
                    $esgV = 0;
                } elseif ($va['valida'] && $va['esgotada']) {
                    $esgV = 1;
                }
                $vars[$vid] = ['qtd' => $vb['qtd'], 'esgotado' => $esgV];
            }
            $alvo[$pid] = ['esgotado' => $novo, 'soma' => $eb['soma'], 'fora_api' => $eb['estado'] === 'fora_api', 'vars' => $vars];
        }

        foreach ($b['mudancas'] as $r) {
            $k = $r['tipo'] . '|' . $r['sku_pai'] . '|' . ($r['sku_completo'] ?? '');
            $chavesB[$k] = true;
            // saída só vale se a 1a leitura também viu o produto/cor abaixo do mínimo
            $ea = $a['estados'][(int) $r['produto_id']] ?? null;
            $confirmada = match ($r['tipo']) {
                'saiu'     => ($ea['estado'] ?? null) === 'esgotado',
                'cor_saiu' => self::corEsgotadaEm($ea, $prods[(int) $r['produto_id']] ?? null, (string) $r['sku_completo']),
                default    => true,
            };
            if (!$confirmada) {
                $mud[] = ['situacao' => 'nao_aplicado', 'detalhe' => 'Apareceu só na segunda leitura. Fica no ar e entra na próxima atualização. ' . ($r['detalhe'] ?? '')] + $r;
                continue;
            }
            $mud[] = ['situacao' => 'aplicado'] + $r;
            $chave = match ($r['tipo']) {
                'saiu' => 'saem', 'voltou' => 'voltam', 'cor_saiu' => 'cores_saem', 'cor_voltou' => 'cores_voltam',
                'fora_api' => 'fora_api', default => 'incertos',
            };
            $cont[$chave]++;
        }
        foreach ($chaveA as $k => $r) {
            if (isset($chavesB[$k])) {
                continue;
            }
            if (in_array($r['tipo'], ['saiu', 'cor_saiu'], true)) {
                $mud[] = ['situacao' => 'divergente', 'detalhe' => 'A segunda leitura não confirmou (estoque mudou ou leitura incerta). Ficou no ar.'] + $r;
                $cont['divergentes']++;
            } else {
                $mud[] = ['situacao' => 'nao_aplicado', 'detalhe' => 'Não se repetiu na segunda leitura.'] + $r;
            }
        }
        return ['alvo' => $alvo, 'prods' => $prods, 'mudancas' => $mud, 'cont' => $cont];
    }

    /** A cor (sku-cor) estava com leitura válida e abaixo do mínimo nesta leitura? */
    private static function corEsgotadaEm(?array $estado, ?array $produto, string $skuCor): bool
    {
        if (!$estado || !$produto) {
            return false;
        }
        foreach ($produto['vars'] as $vid => $v) {
            if ((string) $v['sku_completo'] === $skuCor) {
                $e = $estado['vars'][$vid] ?? null;
                return $e !== null && $e['valida'] && $e['esgotada'];
            }
        }
        return false;
    }

    // ============================================================
    // Gravação
    // ============================================================

    private function gravarEstado(array $final): void
    {
        $upP = $this->pdo->prepare(
            'UPDATE produtos SET
                esgotado_desde = CASE WHEN :e1 = 1 AND esgotado = 0 THEN :t1 WHEN :e2 = 0 THEN NULL ELSE esgotado_desde END,
                esgotado = :e3,
                estoque_total = COALESCE(:soma, estoque_total),
                estoque_conferido_em = :t2,
                fora_api_desde = CASE WHEN :fa1 = 1 THEN COALESCE(fora_api_desde, :t3) ELSE NULL END
             WHERE id = :id'
        );
        $upV = $this->pdo->prepare(
            'UPDATE variacoes SET
                esgotado_desde = CASE WHEN :e1 = 1 AND esgotado = 0 THEN :t1 WHEN :e2 = 0 THEN NULL ELSE esgotado_desde END,
                esgotado = :e3,
                estoque = :q
             WHERE id = :id'
        );
        $t = self::agora();
        foreach ($final['alvo'] as $pid => $a) {
            $upP->execute([
                ':e1' => $a['esgotado'], ':e2' => $a['esgotado'], ':e3' => $a['esgotado'], ':t1' => $t, ':t2' => $t, ':t3' => $t,
                ':soma' => $a['soma'], ':fa1' => (int) $a['fora_api'], ':id' => $pid,
            ]);
            foreach ($a['vars'] as $vid => $v) {
                $upV->execute([':e1' => $v['esgotado'], ':e2' => $v['esgotado'], ':e3' => $v['esgotado'], ':t1' => $t, ':q' => $v['qtd'], ':id' => $vid]);
            }
        }
    }

    private function gravarMudancas(int $id, array $linhas, string $situacao): void
    {
        $ins = $this->pdo->prepare(
            'INSERT INTO catalogo_mudancas (atualizacao_id, tipo, situacao, produto_id, sku_pai, nome, categoria, sku_completo, cor,
                estoque_anterior, estoque_atual, quantidade_minima, detalhe)
             VALUES (:a, :t, :s, :pid, :sku, :nome, :cat, :skc, :cor, :ea, :eat, :qm, :det)'
        );
        foreach ($linhas as $r) {
            $ins->execute([
                ':a' => $id, ':t' => $r['tipo'], ':s' => $r['situacao'] ?? $situacao, ':pid' => $r['produto_id'] ?? null,
                ':sku' => $r['sku_pai'], ':nome' => mb_substr((string) ($r['nome'] ?? ''), 0, 255), ':cat' => $r['categoria'] ?? null,
                ':skc' => $r['sku_completo'] ?? null, ':cor' => $r['cor'] ?? null, ':ea' => $r['estoque_anterior'] ?? null,
                ':eat' => $r['estoque_atual'] ?? null, ':qm' => $r['quantidade_minima'] ?? null,
                ':det' => isset($r['detalhe']) ? mb_substr((string) $r['detalhe'], 0, 500) : null,
            ]);
        }
    }

    /** Atualiza as linhas da simulação para o resultado final (UPDATE; só insere o que é novo). */
    private function consolidarMudancas(int $id, array $final): void
    {
        $st = $this->pdo->prepare('SELECT id, tipo, sku_pai, sku_completo FROM catalogo_mudancas WHERE atualizacao_id = :a');
        $st->execute([':a' => $id]);
        $existentes = [];
        foreach ($st->fetchAll() as $r) {
            $existentes[$r['tipo'] . '|' . $r['sku_pai'] . '|' . ($r['sku_completo'] ?? '')] = (int) $r['id'];
        }
        $up = $this->pdo->prepare(
            'UPDATE catalogo_mudancas SET situacao = :s, estoque_atual = :eat, detalhe = :det WHERE id = :id'
        );
        $novas = [];
        $vistos = [];
        foreach ($final as $r) {
            $k = $r['tipo'] . '|' . $r['sku_pai'] . '|' . ($r['sku_completo'] ?? '');
            $vistos[$k] = true;
            if (isset($existentes[$k])) {
                $up->execute([
                    ':s' => $r['situacao'], ':eat' => $r['estoque_atual'] ?? null,
                    ':det' => isset($r['detalhe']) ? mb_substr((string) $r['detalhe'], 0, 500) : null, ':id' => $existentes[$k],
                ]);
            } else {
                $novas[] = $r;
            }
        }
        if ($novas) {
            $this->gravarMudancas($id, $novas, 'aplicado');
        }
        $resto = array_diff_key($existentes, $vistos);
        if ($resto) {
            $ph = implode(',', array_fill(0, count($resto), '?'));
            $this->pdo->prepare("UPDATE catalogo_mudancas SET situacao = 'nao_aplicado' WHERE id IN ({$ph})")->execute(array_values($resto));
        }
    }

    private function depoisDeAplicar(array $final): void
    {
        Cache::flush();
        Seo::limparCacheCategorias();
        // Avisa o IndexNow das páginas que mudaram de status (saiu = noindex, voltou = index).
        try {
            $urls = [];
            foreach ($final['mudancas'] as $r) {
                if ($r['situacao'] === 'aplicado' && in_array($r['tipo'], ['saiu', 'voltou'], true)) {
                    $p = $final['prods'][(int) $r['produto_id']] ?? null;
                    if ($p && !empty($p['slug'])) {
                        $urls[] = Seo::canonical(Seo::urlProduto($p));
                    }
                }
            }
            if ($urls) {
                IndexNow::enviar($urls);
            }
        } catch (Throwable $e) {
            error_log('[EstoqueSync] IndexNow: ' . $e->getMessage());
        }
    }

    private static function agora(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function fechar(int $id, string $status, string $msg): void
    {
        $this->pdo->prepare('UPDATE catalogo_atualizacoes SET status = :s, mensagem = :m WHERE id = :id')
            ->execute([':s' => $status, ':m' => mb_substr($msg, 0, 500), ':id' => $id]);
    }

    /** Uma operação por vez (dois cliques seguidos não rodam em paralelo). */
    private function comTrava(callable $fn): array
    {
        @set_time_limit(300);
        $lock = dirname(__DIR__, 2) . '/storage/cache/.estoque-sync.lock';
        $fp = @fopen($lock, 'c');
        if ($fp === false || !flock($fp, LOCK_EX | LOCK_NB)) {
            if ($fp) {
                fclose($fp);
            }
            return ['ok' => false, 'id' => 0, 'status' => 'ocupado', 'mensagem' => 'Já existe uma atualização de estoque rodando. Aguarde terminar.'];
        }
        try {
            return $fn();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EstoqueSync] ' . $e->getMessage());
            return ['ok' => false, 'id' => 0, 'status' => 'erro', 'mensagem' => 'Erro inesperado, nada foi alterado no site: ' . $e->getMessage()];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
