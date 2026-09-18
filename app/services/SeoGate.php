<?php

declare(strict_types=1);

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Seo.php';

/**
 * PORTÃO DE QUALIDADE.
 *
 * Com ~3.500 páginas vindas da API da XBZ, nome, descrição e foto são
 * idênticos aos de dezenas de revendedores. O Google não penaliza duplicata
 * entre domínios: ele escolhe um e ignora os outros. O portão decide quais
 * produtos têm conteúdo PRÓPRIO suficiente para merecer o índice.
 *
 * A saída (produtos.seo_indexavel + seo_motivo) só passa a valer quando o
 * admin liga a chave `seo_portao_ativo`. Desligado, o portão só REPORTA —
 * assim ninguém tira 3.500 páginas do índice por acidente antes de ter
 * conteúdo. Ligado, meta robots, sitemap e links internos leem a mesma
 * decisão via Seo::produtoIndexavel().
 *
 * Preço NÃO é critério: decisão de negócio é catálogo consultivo, sem preço.
 */
final class SeoGate
{
    public const REGRAS = [
        'descricao_propria_min_palavras' => 60,
        'imagens_min'                    => 2,
        'similaridade_max'               => 0.80,
    ];

    /**
     * @param array $p linha de produtos + ['imagens' => int]
     * @return array{indexavel:bool,motivo:string}
     */
    public static function avaliarProduto(array $p): array
    {
        $falhas = [];
        $r = self::REGRAS;

        if (empty($p['ativo'])) {
            $falhas[] = 'inativo';
        }
        if (empty($p['imagem_principal'])) {
            $falhas[] = 'sem_imagem';
        }
        $propria  = trim((string) ($p['descricao_propria'] ?? ''));
        $palavras = $propria === '' ? 0 : count(preg_split('/\s+/u', strip_tags($propria)) ?: []);
        if ($palavras < $r['descricao_propria_min_palavras']) {
            $falhas[] = "descricao_propria_curta({$palavras})";
        } elseif (self::similaridade($propria, (string) ($p['descricao'] ?? '')) > $r['similaridade_max']) {
            $falhas[] = 'descricao_propria_copia_do_fornecedor';
        }
        if ((int) ($p['imagens'] ?? 0) < $r['imagens_min']) {
            $falhas[] = 'imagens_insuficientes';
        }
        if (empty($p['quantidade_minima'])) {
            $falhas[] = 'sem_quantidade_minima';
        }
        $op = Seo::operacional($p);
        if ($op['prazo'] === null) {
            $falhas[] = 'sem_prazo';
        }
        if ($op['tecnicas'] === null) {
            $falhas[] = 'sem_tecnica';
        }

        return ['indexavel' => $falhas === [], 'motivo' => implode('|', $falhas)];
    }

    /** Jaccard de vocabulário: texto gerado com o nome trocado é duplicata com máscara. */
    public static function similaridade(string $a, string $b): float
    {
        $norm = static fn (string $t): array => array_unique(preg_split('/\W+/u', mb_strtolower(strip_tags($t), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $ta = $norm($a);
        $tb = $norm($b);
        if (!$ta || !$tb) {
            return 0.0;
        }
        $inter = count(array_intersect($ta, $tb));
        $uniao = count(array_unique(array_merge($ta, $tb)));
        return $uniao ? $inter / $uniao : 0.0;
    }

    /**
     * Reavalia o catálogo inteiro (rode no fim de toda sync). Guarda o resumo
     * em `configuracoes` para o admin mostrar taxa de aprovação e gargalos.
     *
     * @return array{avaliados:int,indexaveis:int,motivos:array<string,int>,em:string}
     */
    public static function reavaliarCatalogo(PDO $pdo, int $lote = 500): array
    {
        $stats = ['avaliados' => 0, 'indexaveis' => 0, 'motivos' => [], 'em' => date('Y-m-d H:i:s')];
        // Aquece o cache de Settings ANTES da transação: o 1º Settings::get() roda um
        // CREATE TABLE IF NOT EXISTS (DDL), e DDL faz commit implícito no MySQL.
        Seo::operacional();
        $upd = $pdo->prepare('UPDATE produtos SET seo_indexavel = :i, seo_motivo = :m, seo_avaliado_em = NOW() WHERE id = :id');
        $offset = 0;

        while (true) {
            $st = $pdo->prepare(
                "SELECT p.*, (
                    SELECT COUNT(*) FROM imagens i JOIN variacoes v ON v.id = i.variacao_id
                    WHERE v.produto_id = p.id AND v.ativo = 1
                 ) AS imagens
                 FROM produtos p ORDER BY p.id LIMIT :lim OFFSET :off"
            );
            $st->bindValue(':lim', $lote, PDO::PARAM_INT);
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->execute();
            $linhas = $st->fetchAll();
            if (!$linhas) {
                break;
            }
            $pdo->beginTransaction();
            foreach ($linhas as $p) {
                $r = self::avaliarProduto($p);
                // só grava se mudou (evita bumpar updated_at de 3.500 linhas à toa)
                if ((int) $p['seo_indexavel'] !== (int) $r['indexavel'] || (string) $p['seo_motivo'] !== ($r['motivo'] ?: '')) {
                    $upd->execute([':i' => (int) $r['indexavel'], ':m' => $r['motivo'] ?: null, ':id' => $p['id']]);
                }
                $stats['avaliados']++;
                if ($r['indexavel']) {
                    $stats['indexaveis']++;
                }
                foreach (array_filter(explode('|', $r['motivo'])) as $m) {
                    $chave = (string) preg_replace('/\(.*\)/', '', $m);
                    $stats['motivos'][$chave] = ($stats['motivos'][$chave] ?? 0) + 1;
                }
            }
            $pdo->commit();
            $offset += $lote;
        }
        arsort($stats['motivos']);
        Settings::set('seo_portao_stats', $stats);
        return $stats;
    }

    /** Categoria: precisa de volume, intro própria e FAQ para ser indexável. */
    public static function avaliarCategoria(array $c, int $qtdProdutos, int $qtdFaq): array
    {
        $falhas = [];
        if ($qtdProdutos < 8) {
            $falhas[] = "produtos_insuficientes({$qtdProdutos})";
        }
        $palavras = count(preg_split('/\s+/u', trim(strip_tags((string) ($c['intro_seo'] ?? '')))) ?: []);
        if ($palavras < 80) {
            $falhas[] = "intro_curta({$palavras})";
        }
        if ($qtdFaq < 3) {
            $falhas[] = 'faq_insuficiente';
        }
        return ['indexavel' => $falhas === [], 'motivo' => implode('|', $falhas)];
    }
}
