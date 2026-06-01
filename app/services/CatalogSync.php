<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/XBZService.php';
require_once __DIR__ . '/ProductMapper.php';
require_once __DIR__ . '/Cache.php';

/**
 * Núcleo da sincronização do catálogo XBZ -> banco.
 *
 * Extraído de scripts/sync_xbz.php para ser reaproveitado tanto pela CLI
 * (cron semanal) quanto pelo migrador web (public/migrate-web.php), já que o
 * ambiente do usuário não tem PHP de linha de comando.
 *
 * Fluxo seguro (idêntico ao original):
 *  1. Abre um registro em sync_runs (auditoria).
 *  2. Busca a lista da XBZ via $fetcher. Se falhar, marca erro e SAI sem tocar no catálogo.
 *  3. Agrupa por CodigoAmigavel (pai) e faz UPSERT transacional de
 *     produtos + variacoes + imagens.
 *  4. Marca como inativo (nunca apaga) o que não veio nesta sync.
 *  5. Limpa o cache do catálogo.
 *  6. Fecha o registro em sync_runs e devolve o resumo.
 */
final class CatalogSync
{
    /** @var callable(string):void */
    private $log;

    public function __construct(
        private readonly PDO $pdo,
        ?callable $logger = null,
    ) {
        // Logger opcional: CLI imprime + grava arquivo; web pode ignorar.
        $this->log = $logger ?? static function (string $_): void {};
    }

    public static function create(?callable $logger = null): self
    {
        return new self(Database::connection(), $logger);
    }

    /**
     * Executa a sincronização completa.
     *
     * @param callable():array<int,array<string,mixed>> $fetcher  Devolve os itens brutos da XBZ.
     * @return array{
     *     status:string, ok:bool, run_id:int, mensagem:string,
     *     contadores:array<string,int>
     * }
     */
    public function executar(callable $fetcher): array
    {
        $log = $this->log;
        $pdo = $this->pdo;

        // -----------------------------------------------------------
        // 1. Abre o registro de auditoria
        // -----------------------------------------------------------
        $inicio = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO sync_runs (iniciado_em, status) VALUES (:i, :s)')
            ->execute([':i' => $inicio, ':s' => 'em_andamento']);
        $runId = (int) $pdo->lastInsertId();
        $log("== Sync XBZ iniciada (run #{$runId}) ==");

        // -----------------------------------------------------------
        // 2. Busca os dados (falha = não tocar no catálogo)
        // -----------------------------------------------------------
        try {
            $itens = $fetcher();
        } catch (Throwable $e) {
            $log('[ERRO] Falha ao obter dados da XBZ: ' . $e->getMessage());
            $this->fecharRun($runId, 'erro', [], 'Falha ao obter dados: ' . $e->getMessage());
            return $this->resultado($runId, 'erro', 'Falha ao obter dados da XBZ: ' . $e->getMessage(), []);
        }

        $total = count($itens);
        if ($total === 0) {
            $log('[ERRO] Lista vazia — abortando para não inativar o catálogo inteiro.');
            $this->fecharRun($runId, 'erro', [], 'Lista vazia da XBZ.');
            return $this->resultado($runId, 'erro', 'A XBZ retornou uma lista vazia — nada foi alterado.', []);
        }
        $log("Recebidos {$total} itens (variações).");

        // -----------------------------------------------------------
        // 3. Agrupa por pai (CodigoAmigavel)
        // -----------------------------------------------------------
        $grupos = [];
        foreach ($itens as $it) {
            $pai = trim((string) ($it['CodigoAmigavel'] ?? ''));
            if ($pai === '') {
                // sem pai: usa o próprio composto como pai
                $pai = trim((string) ($it['CodigoComposto'] ?? ''));
            }
            if ($pai === '') {
                continue; // item irrecuperável
            }
            $grupos[$pai][] = $it;
        }
        unset($itens);
        $log('Pais distintos: ' . count($grupos));

        // -----------------------------------------------------------
        // Statements reaproveitados
        // -----------------------------------------------------------
        $syncStamp = date('Y-m-d H:i:s');

        $upProduto = $pdo->prepare(
            'INSERT INTO produtos
                (sku_pai, nome, descricao, categoria, material, preco_base, sustentavel, imagem_principal, ativo, synced_at)
             VALUES (:sku, :nome, :desc, :cat, :mat, :preco, :sus, :img, 1, :sy)
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                nome = VALUES(nome), descricao = VALUES(descricao), categoria = VALUES(categoria),
                material = VALUES(material), preco_base = VALUES(preco_base), sustentavel = VALUES(sustentavel),
                imagem_principal = VALUES(imagem_principal), ativo = 1, synced_at = VALUES(synced_at)'
        );

        $upVariacao = $pdo->prepare(
            'INSERT INTO variacoes
                (produto_id, sku_completo, cor_sufixo, cor, cor_codigo, estoque, ativo, synced_at)
             VALUES (:pid, :sku, :suf, :cor, :hex, :est, 1, :sy)
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                produto_id = VALUES(produto_id), cor_sufixo = VALUES(cor_sufixo), cor = VALUES(cor),
                cor_codigo = VALUES(cor_codigo), estoque = VALUES(estoque), ativo = 1, synced_at = VALUES(synced_at)'
        );

        // Registra sufixos novos para revisão (não sobrescreve correções manuais).
        $upCor = $pdo->prepare(
            'INSERT INTO cores (sufixo, nome, hex, revisar) VALUES (:suf, :nome, :hex, :rev)
             ON DUPLICATE KEY UPDATE sufixo = sufixo'
        );

        $delImgs = $pdo->prepare('DELETE FROM imagens WHERE variacao_id = :vid');
        $insImg  = $pdo->prepare('INSERT INTO imagens (variacao_id, url, ordem, principal) VALUES (:vid, :url, 1, 1)');

        // -----------------------------------------------------------
        // 4. Upsert transacional
        // -----------------------------------------------------------
        $c = ['pais_ins' => 0, 'pais_upd' => 0, 'var_ins' => 0, 'var_upd' => 0];
        $sufixosDesconhecidos = [];

        try {
            $pdo->beginTransaction();

            foreach ($grupos as $skuPai => $variacoes) {
                $skuPai    = (string) $skuPai; // chaves numéricas viram int em PHP
                $primeiro  = $variacoes[0];
                $nome      = trim((string) ($primeiro['Nome'] ?? '')) ?: 'Produto ' . $skuPai;
                $descricao = trim((string) ($primeiro['Descricao'] ?? ''));

                // preço-base = menor preço positivo entre as variações
                $precos = [];
                foreach ($variacoes as $v) {
                    $p = (float) ($v['PrecoVenda'] ?? 0);
                    if ($p > 0) {
                        $precos[] = $p;
                    }
                }
                $precoBase = $precos ? min($precos) : null;

                // imagem principal = primeira ImageLink não vazia
                $imgPrincipal = null;
                foreach ($variacoes as $v) {
                    $link = trim((string) ($v['ImageLink'] ?? ''));
                    if ($link !== '') {
                        $imgPrincipal = $link;
                        break;
                    }
                }

                $categoria   = ProductMapper::categoria($nome);
                $material    = ProductMapper::material($nome, $descricao);
                $sustentavel = ProductMapper::sustentavel($nome, $descricao) ? 1 : 0;

                $upProduto->execute([
                    ':sku' => $skuPai, ':nome' => $nome, ':desc' => $descricao !== '' ? $descricao : null,
                    ':cat' => $categoria, ':mat' => $material, ':preco' => $precoBase,
                    ':sus' => $sustentavel, ':img' => $imgPrincipal, ':sy' => $syncStamp,
                ]);
                $produtoId = (int) $pdo->lastInsertId();
                $upProduto->rowCount() === 1 ? $c['pais_ins']++ : $c['pais_upd']++;

                foreach ($variacoes as $v) {
                    $composto = trim((string) ($v['CodigoComposto'] ?? ''));
                    if ($composto === '') {
                        continue;
                    }
                    $sufixo  = ProductMapper::sufixoCor($composto, $skuPai);
                    $corNome = trim((string) ($v['CorWebPrincipal'] ?? '')) ?: 'Padrão';
                    $corInfo = ProductMapper::corHex($corNome);
                    $estoque = (int) ($v['QuantidadeDisponivel'] ?? 0);

                    $upVariacao->execute([
                        ':pid' => $produtoId, ':sku' => $composto, ':suf' => $sufixo,
                        ':cor' => $corNome, ':hex' => $corInfo['hex'], ':est' => $estoque, ':sy' => $syncStamp,
                    ]);
                    $variacaoId = (int) $pdo->lastInsertId();
                    $upVariacao->rowCount() === 1 ? $c['var_ins']++ : $c['var_upd']++;

                    // registra cor para revisão se hex foi genérico
                    if (!$corInfo['conhecida']) {
                        $sufixosDesconhecidos[$sufixo] = true;
                    }
                    $upCor->execute([
                        ':suf' => $sufixo, ':nome' => $corNome, ':hex' => $corInfo['hex'],
                        ':rev' => $corInfo['conhecida'] ? 0 : 1,
                    ]);

                    // imagem (XBZ entrega 1 por variação)
                    $link = trim((string) ($v['ImageLink'] ?? ''));
                    $delImgs->execute([':vid' => $variacaoId]);
                    if ($link !== '') {
                        $insImg->execute([':vid' => $variacaoId, ':url' => $link]);
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $log('[ERRO] Falha durante o upsert (rollback aplicado): ' . $e->getMessage());
            $this->fecharRun($runId, 'erro', $c, 'Falha no upsert: ' . $e->getMessage());
            return $this->resultado($runId, 'erro', 'Falha ao gravar no banco (rollback aplicado): ' . $e->getMessage(), $c);
        }

        // -----------------------------------------------------------
        // 5. Inativa o que não veio nesta sync (nunca apaga)
        // -----------------------------------------------------------
        $stmtInaP = $pdo->prepare('UPDATE produtos SET ativo = 0 WHERE ativo = 1 AND (synced_at IS NULL OR synced_at < :sy)');
        $stmtInaP->execute([':sy' => $syncStamp]);
        $paisInativados = $stmtInaP->rowCount();

        $stmtInaV = $pdo->prepare('UPDATE variacoes SET ativo = 0 WHERE ativo = 1 AND (synced_at IS NULL OR synced_at < :sy)');
        $stmtInaV->execute([':sy' => $syncStamp]);
        $varInativadas = $stmtInaV->rowCount();

        // -----------------------------------------------------------
        // 6. Invalida o cache do catálogo
        // -----------------------------------------------------------
        $removidos = count(glob(APP_ROOT_PATH() . '/storage/cache/*.json') ?: []);
        Cache::flush();

        $c['sufixos_desconhecidos'] = count($sufixosDesconhecidos);
        $resumo = sprintf(
            'Pais: +%d/~%d | Variações: +%d/~%d | Inativados: %d pais, %d var | Cores novas p/ revisão: %d | Cache limpo: %d',
            $c['pais_ins'], $c['pais_upd'], $c['var_ins'], $c['var_upd'],
            $paisInativados, $varInativadas, $c['sufixos_desconhecidos'], $removidos
        );
        $log($resumo);
        $this->fecharRun($runId, 'sucesso', $c, $resumo);
        $log("== Sync XBZ concluída (run #{$runId}) ==");

        $c['pais_inativados'] = $paisInativados;
        $c['var_inativadas']  = $varInativadas;
        return $this->resultado($runId, 'sucesso', $resumo, $c);
    }

    /** @param array<string,int> $c */
    private function fecharRun(int $runId, string $status, array $c, string $msg = ''): void
    {
        $this->pdo->prepare(
            'UPDATE sync_runs SET finalizado_em = NOW(), status = :st,
                pais_inseridos = :pi, pais_atualizados = :pa,
                variacoes_inseridas = :vi, variacoes_atualizadas = :va,
                sufixos_desconhecidos = :sd, mensagem = :m
             WHERE id = :id'
        )->execute([
            ':st' => $status,
            ':pi' => $c['pais_ins'] ?? 0, ':pa' => $c['pais_upd'] ?? 0,
            ':vi' => $c['var_ins'] ?? 0, ':va' => $c['var_upd'] ?? 0,
            ':sd' => $c['sufixos_desconhecidos'] ?? 0,
            ':m'  => mb_substr($msg, 0, 1000),
            ':id' => $runId,
        ]);
    }

    /** @param array<string,int> $c */
    private function resultado(int $runId, string $status, string $msg, array $c): array
    {
        return [
            'status'     => $status,
            'ok'         => $status === 'sucesso',
            'run_id'     => $runId,
            'mensagem'   => $msg,
            'contadores' => $c,
        ];
    }
}

/**
 * Raiz do projeto, resolvida de forma compatível com a CLI e com a web.
 * (A constante APP_ROOT só existe no bootstrap web; a CLI não a define.)
 */
if (!function_exists('APP_ROOT_PATH')) {
    function APP_ROOT_PATH(): string
    {
        return defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    }
}
