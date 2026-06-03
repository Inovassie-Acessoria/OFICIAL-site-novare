<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Cache.php';

/**
 * Acesso a produtos: busca, filtro, paginação, página de produto.
 *
 * - 100% PDO + prepared statements (sem concatenação de valores).
 * - Ordenação por whitelist (nunca interpola entrada do usuário).
 * - Pensado para 10k+: usa os índices do schema, paginação real (LIMIT/OFFSET),
 *   FULLTEXT para busca textual e EXISTS para o filtro de cor.
 * - Resultados de leitura passam pelo Cache (invalidado pela sync).
 */
final class ProductRepository
{
    private const TTL = 3600; // 1h (catálogo é estável; sync limpa o cache)

    /** Ordenações permitidas (chave do usuário => SQL seguro). */
    private const ORDENACOES = [
        'relevancia' => 'p.created_at DESC',
        'recentes'   => 'p.created_at DESC',
        'preco_asc'  => 'p.preco_base IS NULL, p.preco_base ASC',
        'preco_desc' => 'p.preco_base DESC',
        'nome'       => 'p.nome ASC',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function create(): self
    {
        return new self(Database::connection());
    }

    /**
     * Lista produtos paginados com filtros opcionais.
     *
     * @param array<string,mixed> $f categoria, q, preco_min, preco_max, cor,
     *                                material, sustentavel, quantidade_minima, ordenar
     * @return array{itens:array,total:int,pagina:int,por_pagina:int,total_paginas:int}
     */
    public function listar(array $f = [], int $pagina = 1, int $porPagina = 24): array
    {
        $pagina    = max(1, $pagina);
        $porPagina = max(1, min(60, $porPagina));
        $offset    = ($pagina - 1) * $porPagina;

        [$where, $whereParams, $boolean, $ordenarChave] = $this->montarFiltros($f);
        $whereSql = implode(' AND ', $where);

        // SELECT de relevância (:qscore) e ORDER BY — só a query de dados usa :qscore.
        $matchSelect = '';
        $dataParams  = $whereParams;
        $orderBy     = self::ORDENACOES[$ordenarChave] ?? self::ORDENACOES['relevancia'];
        if ($boolean !== '') {
            $matchSelect = ', MATCH(p.nome, p.descricao, p.tags) AGAINST (:qscore IN BOOLEAN MODE) AS score';
            $dataParams[':qscore'] = $boolean;
            if ($ordenarChave === 'relevancia') {
                $orderBy = 'score DESC';
            }
        }

        // Imagem ciente da cor: com filtro de cor, o card mostra a foto da
        // variação naquela cor (e não a imagem principal/cor padrão).
        $imgSelect = 'p.imagem_principal';
        if (!empty($f['cor'])) {
            $imgSelect = "COALESCE(
                (SELECT i.url FROM variacoes vc
                 JOIN imagens i ON i.variacao_id = vc.id
                 WHERE vc.produto_id = p.id AND vc.ativo = 1 AND vc.cor = :cor_img
                 ORDER BY i.principal DESC, i.ordem ASC LIMIT 1),
                p.imagem_principal
            ) AS imagem_principal";
            $dataParams[':cor_img'] = (string) $f['cor'];
        }

        $chave = 'lst:' . md5(json_encode([$f, $pagina, $porPagina]));

        return Cache::remember($chave, self::TTL, function () use (
            $whereSql, $whereParams, $dataParams, $matchSelect, $imgSelect, $orderBy, $porPagina, $offset, $pagina
        ) {
            // total (apenas params do WHERE)
            $stmtC = $this->pdo->prepare("SELECT COUNT(*) FROM produtos p WHERE {$whereSql}");
            $stmtC->execute($whereParams);
            $total = (int) $stmtC->fetchColumn();

            // página de dados ($porPagina/$offset já são inteiros validados)
            $sql = "SELECT p.id, p.sku_pai, p.nome, p.categoria, p.material,
                           p.preco_base, p.sustentavel, {$imgSelect} {$matchSelect}
                    FROM produtos p
                    WHERE {$whereSql}
                    ORDER BY {$orderBy}
                    LIMIT {$porPagina} OFFSET {$offset}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($dataParams);
            $itens = $stmt->fetchAll();

            return [
                'itens'         => $itens,
                'total'         => $total,
                'pagina'        => $pagina,
                'por_pagina'    => $porPagina,
                'total_paginas' => (int) max(1, ceil($total / $porPagina)),
            ];
        });
    }

    /**
     * Monta WHERE + params do WHERE + string booleana de busca + chave de ordenação.
     *
     * @return array{0:string[],1:array<string,mixed>,2:string,3:string}
     */
    private function montarFiltros(array $f): array
    {
        [$where, $params, $boolean] = $this->construirWhere($f, null);
        $ordenarChave = 'relevancia';
        if (!empty($f['ordenar']) && isset(self::ORDENACOES[$f['ordenar']])) {
            $ordenarChave = (string) $f['ordenar'];
        }

        return [$where, $params, $boolean, $ordenarChave];
    }

    /**
     * Constrói o WHERE + params compartilhado entre a listagem e as facetas.
     *
     * $excluir permite ignorar uma dimensão (ex.: ao montar a faceta de
     * "material", excluímos o próprio filtro de material para que a lista
     * de materiais disponíveis reflita os demais filtros — busca facetada
     * com eliminação). Produtos sem imagem nunca entram.
     *
     * @return array{0:string[],1:array<string,mixed>,2:string}
     */
    private function construirWhere(array $f, ?string $excluir = null): array
    {
        $where  = ['p.ativo = 1', 'p.imagem_principal IS NOT NULL', "p.imagem_principal <> ''"];
        $params = [];
        $boolean = '';

        if ($excluir !== 'categoria' && !empty($f['categoria'])) {
            $where[] = 'p.categoria = :categoria';
            $params[':categoria'] = (string) $f['categoria'];
        }
        if ($excluir !== 'material' && !empty($f['material'])) {
            $where[] = 'p.material = :material';
            $params[':material'] = (string) $f['material'];
        }
        if ($excluir !== 'sustentavel' && !empty($f['sustentavel'])) {
            $where[] = 'p.sustentavel = 1';
        }
        if (isset($f['preco_min']) && $f['preco_min'] !== '') {
            $where[] = 'p.preco_base >= :pmin';
            $params[':pmin'] = (float) $f['preco_min'];
        }
        if (isset($f['preco_max']) && $f['preco_max'] !== '') {
            $where[] = 'p.preco_base <= :pmax';
            $params[':pmax'] = (float) $f['preco_max'];
        }
        if (isset($f['quantidade_minima']) && $f['quantidade_minima'] !== '') {
            $where[] = '(p.quantidade_minima IS NULL OR p.quantidade_minima <= :qmin)';
            $params[':qmin'] = (int) $f['quantidade_minima'];
        }
        if ($excluir !== 'cor' && !empty($f['cor'])) {
            $where[] = 'EXISTS (SELECT 1 FROM variacoes v WHERE v.produto_id = p.id AND v.ativo = 1 AND v.cor = :cor)';
            $params[':cor'] = (string) $f['cor'];
        }
        if (!empty($f['q'])) {
            $buscaOriginal = trim((string) $f['q']);
            $b = $this->prepararBusca($buscaOriginal);
            if ($b !== '') {
                $where[] = '(p.sku_pai = :sku_exato OR EXISTS (SELECT 1 FROM variacoes v2 WHERE v2.produto_id = p.id AND v2.sku_completo = :sku_exato) OR MATCH(p.nome, p.descricao, p.tags) AGAINST (:q IN BOOLEAN MODE))';
                $params[':sku_exato'] = $buscaOriginal;
                $params[':q'] = $b;
                $boolean = $b;
            }
        }

        return [$where, $params, $boolean];
    }

    /**
     * Converte a busca do usuário em expressão BOOLEAN MODE segura.
     *
     * Termos são OPCIONAIS (sem '+') com curinga '*', e o resultado é ordenado
     * por relevância (score). Assim frases naturais ("preciso de garrafa térmica")
     * ainda retornam os itens mais relevantes, em vez de exigir todas as palavras.
     * Tokens com menos de 3 caracteres são ignorados (alinhado ao min token do FULLTEXT).
     */
    private function prepararBusca(string $termo): string
    {
        $termo = preg_replace('/[+\-><()~*"@]+/', ' ', $termo) ?? '';
        $palavras = preg_split('/\s+/', trim($termo), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $partes = [];
        foreach ($palavras as $p) {
            if (mb_strlen($p) >= 3) {
                $partes[] = $p . '*';
            }
        }
        return implode(' ', $partes);
    }

    /**
     * Carrega um produto-pai com variações ativas e imagens (página de produto).
     *
     * @return array{produto:array,variacoes:array}|null
     */
    public function buscarPorSkuPai(string $skuPai): ?array
    {
        $chave = 'prod:' . md5($skuPai);

        return Cache::remember($chave, self::TTL, function () use ($skuPai) {
            $stmt = $this->pdo->prepare('SELECT * FROM produtos WHERE sku_pai = :sku AND ativo = 1');
            $stmt->execute([':sku' => $skuPai]);
            $produto = $stmt->fetch();
            if (!$produto) {
                return null;
            }

            $stmtV = $this->pdo->prepare(
                'SELECT id, sku_completo, cor_sufixo, cor, cor_codigo, estoque
                 FROM variacoes WHERE produto_id = :pid AND ativo = 1 ORDER BY id'
            );
            $stmtV->execute([':pid' => $produto['id']]);
            $variacoes = $stmtV->fetchAll();

            // imagens de todas as variações em uma query
            $imagensPorVar = [];
            if ($variacoes) {
                $ids = array_column($variacoes, 'id');
                $ph  = implode(',', array_fill(0, count($ids), '?'));
                $stmtI = $this->pdo->prepare(
                    "SELECT variacao_id, url, principal FROM imagens
                     WHERE variacao_id IN ({$ph}) ORDER BY variacao_id, ordem"
                );
                $stmtI->execute($ids);
                foreach ($stmtI->fetchAll() as $img) {
                    $imagensPorVar[(int) $img['variacao_id']][] = $img['url'];
                }
            }
            foreach ($variacoes as &$v) {
                $v['imagens'] = $imagensPorVar[(int) $v['id']] ?? [];
            }
            unset($v);

            return ['produto' => $produto, 'variacoes' => $variacoes];
        });
    }

    /** Categorias ativas com contagem (sidebar/home). */
    public function categorias(): array
    {
        return Cache::remember('cats', self::TTL, function () {
            $sql = 'SELECT categoria, COUNT(*) AS total FROM produtos
                    WHERE ativo = 1 AND categoria IS NOT NULL AND categoria <> ""
                    GROUP BY categoria ORDER BY total DESC';
            return $this->pdo->query($sql)->fetchAll();
        });
    }

    /**
     * Materiais disponíveis com contagem (faceta).
     *
     * Respeita os filtros ativos (categoria, cor, busca, etc.), exceto o
     * próprio material: ao escolher uma categoria, só aparecem os materiais
     * que de fato existem nela.
     *
     * @param array<string,mixed> $f
     */
    public function materiais(array $f = []): array
    {
        [$where, $params] = $this->construirWhere($f, 'material');
        $whereSql = implode(' AND ', $where);

        return Cache::remember('mats:' . md5(json_encode($f)), self::TTL, function () use ($whereSql, $params) {
            $sql = "SELECT p.material AS material, COUNT(*) AS total
                    FROM produtos p
                    WHERE {$whereSql} AND p.material IS NOT NULL AND p.material <> ''
                    GROUP BY p.material ORDER BY total DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        });
    }

    /**
     * Cores disponíveis com hex e contagem (swatches do filtro).
     *
     * Respeita os filtros ativos, exceto a própria cor — assim a lista de
     * cores se restringe ao que existe dentro da categoria/material/busca.
     *
     * @param array<string,mixed> $f
     */
    public function cores(array $f = []): array
    {
        [$where, $params] = $this->construirWhere($f, 'cor');
        $whereSql = implode(' AND ', $where);

        return Cache::remember('cores:' . md5(json_encode($f)), self::TTL, function () use ($whereSql, $params) {
            $sql = "SELECT v.cor AS cor, MIN(v.cor_codigo) AS hex, COUNT(DISTINCT v.produto_id) AS total
                    FROM variacoes v
                    JOIN produtos p ON p.id = v.produto_id
                    WHERE v.ativo = 1 AND v.cor IS NOT NULL AND v.cor <> '' AND {$whereSql}
                    GROUP BY v.cor ORDER BY total DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        });
    }

    /** Faixa de preço (mín/máx) para o filtro. */
    public function faixaPreco(): array
    {
        return Cache::remember('faixa', self::TTL, function () {
            $row = $this->pdo->query(
                'SELECT MIN(preco_base) AS min, MAX(preco_base) AS max
                 FROM produtos WHERE ativo = 1 AND preco_base > 0'
            )->fetch();
            return [
                'min' => (float) ($row['min'] ?? 0),
                'max' => (float) ($row['max'] ?? 0),
            ];
        });
    }

    /** Produtos para destaque na home (mais recentes). */
    public function destaques(int $limite = 8): array
    {
        $limite = max(1, min(24, $limite));
        return Cache::remember("dest:{$limite}", self::TTL, function () use ($limite) {
            $sql = "SELECT id, sku_pai, nome, categoria, preco_base, sustentavel, imagem_principal
                    FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> ''
                    ORDER BY created_at DESC LIMIT {$limite}";
            return $this->pdo->query($sql)->fetchAll();
        });
    }


    /**
     * Carrega produtos por uma lista de sku_pai PRESERVANDO a ordem informada.
     * Usado pelo ranking manual do admin (arrastar/soltar) e pela busca por SKU.
     * Só retorna ativos com imagem.
     *
     * @param string[] $skus
     */
    public function porSkus(array $skus): array
    {
        $skus = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $skus), static fn ($s) => $s !== ''));
        if (!$skus) {
            return [];
        }
        $skus = array_slice($skus, 0, 50);
        $ph   = implode(',', array_fill(0, count($skus), '?'));
        $sql  = "SELECT sku_pai, nome, categoria, preco_base, sustentavel, imagem_principal
                 FROM produtos
                 WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> ''
                   AND sku_pai IN ({$ph})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($skus);

        $porSku = [];
        foreach ($stmt->fetchAll() as $row) {
            $porSku[$row['sku_pai']] = $row;
        }
        // Reordena conforme a lista de entrada (ordem do admin).
        $ordenado = [];
        foreach ($skus as $sku) {
            if (isset($porSku[$sku])) {
                $ordenado[] = $porSku[$sku];
            }
        }
        return $ordenado;
    }
}
