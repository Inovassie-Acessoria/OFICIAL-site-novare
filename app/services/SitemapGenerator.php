<?php

declare(strict_types=1);

/**
 * Sitemap index segmentado POR TEMPLATE.
 *
 *   /sitemap.xml                 índice
 *   /sitemap-institucional.xml   home + páginas fixas
 *   /sitemap-categorias.xml      categorias + facetas promovidas
 *   /sitemap-ocasioes.xml        landings de ocasião
 *   /sitemap-blog.xml            /blog + matérias publicadas
 *   /sitemap-produtos-N.xml      produtos, 10.000 por arquivo
 *
 * Por que segmentar: o Search Console mostra cobertura por sitemap, então dá
 * para ver "categorias 96% indexadas, produtos 11%" — a informação que
 * direciona o trabalho.
 *
 * Regra absoluta: só entra URL que devolve 200, é indexável e é canônica de
 * si mesma. A decisão de indexabilidade é a MESMA da meta robots
 * (Seo::produtoIndexavel), senão o Google vê sinal contraditório.
 *
 * lastmod: conteudo_alterado_em (muda só quando nome/descrição/imagem mudam
 * de verdade) — nunca updated_at, que o sync bumpa em massa toda semana.
 * priority/changefreq foram abandonados pelo Google: não entram.
 */
final class SitemapGenerator
{
    public const POR_ARQUIVO = 10000;

    public static function indice(): void
    {
        self::cabecalho();
        $pdo = Database::connection();

        $totalProd = (int) $pdo->query(self::sqlProdutos('COUNT(*)'))->fetchColumn();
        $arquivos  = max(1, (int) ceil($totalProd / self::POR_ARQUIVO));

        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        self::sitemap(Seo::canonical('/sitemap-institucional.xml'), self::maxLastmod($pdo, 'produtos', 'conteudo_alterado_em'));
        self::sitemap(Seo::canonical('/sitemap-categorias.xml'), self::maxLastmod($pdo, 'categorias', 'updated_at'));
        if (self::conta($pdo, 'seo_ocasioes', 'seo_indexavel = 1') > 0) {
            self::sitemap(Seo::canonical('/sitemap-ocasioes.xml'), self::maxLastmod($pdo, 'seo_ocasioes', 'updated_at'));
        }
        if (self::conta($pdo, 'blog_posts', BlogController::WHERE_PUBLICADO) > 0) {
            self::sitemap(Seo::canonical('/sitemap-blog.xml'), self::maxLastmod($pdo, 'blog_posts', 'updated_at', BlogController::WHERE_PUBLICADO));
        }
        $lastProd = self::maxLastmod($pdo, 'produtos', 'conteudo_alterado_em');
        for ($i = 1; $i <= $arquivos; $i++) {
            self::sitemap(Seo::canonical("/sitemap-produtos-{$i}.xml"), $lastProd);
        }
        echo '</sitemapindex>';
    }

    public static function filho(string $tipo, int $pagina = 1): void
    {
        $pdo = Database::connection();
        if (!in_array($tipo, ['institucional', 'categorias', 'ocasioes', 'blog', 'produtos'], true)) {
            http_response_code(404);
            return;
        }
        self::cabecalho();
        // produtos levam a extensão de imagem (só a foto hospedada no nosso domínio)
        $nsImagem = $tipo === 'produtos' ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $nsImagem . '>' . "\n";

        switch ($tipo) {
            case 'institucional':
                // lastmod honesto: a home muda quando o catálogo muda; as fixas, quando forem editadas
                self::url(Seo::canonical('/'), self::maxLastmod($pdo, 'produtos', 'conteudo_alterado_em'));
                self::url(Seo::canonical('/brindes'), self::maxLastmod($pdo, 'produtos', 'conteudo_alterado_em'));
                foreach (['/sobre', '/atendimento', '/fidelidade'] as $p) {
                    self::url(Seo::canonical($p), null);
                }
                break;

            case 'categorias':
                $st = $pdo->query(
                    "SELECT c.slug, c.updated_at, COUNT(p.id) AS total
                     FROM categorias c
                     LEFT JOIN produtos p ON p.categoria = c.nome AND p.ativo = 1
                          AND p.imagem_principal IS NOT NULL AND p.imagem_principal <> ''" . Estoque::sqlProduto('p') . "
                     WHERE c.seo_indexavel = 1
                     GROUP BY c.id HAVING total >= 8
                     ORDER BY c.ordem"
                );
                foreach ($st->fetchAll() as $c) {
                    self::url(Seo::canonical(Seo::urlCategoriaSlug($c['slug'])), $c['updated_at']);
                }
                try {
                    $st = $pdo->query('SELECT categoria_slug, atributo, valor_slug, updated_at FROM seo_facetas WHERE seo_indexavel = 1 ORDER BY id');
                    foreach ($st->fetchAll() as $f) {
                        self::url(Seo::canonical(Seo::urlFaceta($f['categoria_slug'], $f['atributo'], $f['valor_slug'])), $f['updated_at']);
                    }
                } catch (Throwable $e) {
                }
                break;

            case 'ocasioes':
                $st = $pdo->query('SELECT slug, updated_at FROM seo_ocasioes WHERE seo_indexavel = 1 ORDER BY id');
                foreach ($st->fetchAll() as $o) {
                    self::url(Seo::canonical(Seo::urlOcasiao($o['slug'])), $o['updated_at']);
                }
                break;

            case 'blog':
                try {
                    $posts = $pdo->query('SELECT slug, updated_at FROM blog_posts WHERE ' . BlogController::WHERE_PUBLICADO . ' ORDER BY publicado_em DESC')->fetchAll();
                } catch (Throwable $e) {
                    $posts = [];
                }
                if ($posts) {
                    self::url(Seo::canonical(Seo::urlBlog()), $posts[0]['updated_at']);
                }
                foreach ($posts as $b) {
                    self::url(Seo::canonical(Seo::urlPost($b['slug'])), $b['updated_at']);
                }
                break;

            case 'produtos':
                $offset = (max(1, $pagina) - 1) * self::POR_ARQUIVO;
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $st = $pdo->query(self::sqlProdutos('slug, sku_pai, conteudo_alterado_em, imagem_local') . ' ORDER BY id LIMIT ' . self::POR_ARQUIVO . " OFFSET {$offset}");
                while ($r = $st->fetch()) {
                    $img = trim((string) ($r['imagem_local'] ?? ''));
                    self::url(Seo::canonical(Seo::urlProduto($r)), $r['conteudo_alterado_em'], $img !== '' ? urlAbsoluta($img) : null);
                }
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                break;
        }
        echo '</urlset>';
    }

    /**
     * /llms.txt — resumo para motores de IA. Gerado do banco para nunca
     * publicar dado inventado: só entra o que existe (categorias com produto,
     * prazo/técnicas quando preenchidos no admin, landings indexáveis).
     */
    public static function llms(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $pdo  = Database::connection();
        $repo = ProductRepository::create();
        $op   = Seo::operacional();
        $totalProd = (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> ''" . Estoque::sqlProduto())->fetchColumn();
        $h = Seo::host();

        $l = [];
        $l[] = '# Novare Brindes';
        $l[] = '';
        $l[] = '> Fornecedora brasileira de brindes corporativos personalizados para empresas.';
        $l[] = '> Catálogo com ' . number_format($totalProd, 0, ',', '.') . ' itens, personalização com a logo do cliente e';
        $l[] = '> atendimento consultivo por WhatsApp. Atende todo o Brasil. Sem venda online direta: o orçamento é feito com a equipe comercial.';
        $l[] = '';
        $l[] = '## Informação operacional';
        $l[] = '- Quantidade mínima por pedido: definida por faixa de valor do produto: 200 unidades para itens de menor valor (canetas, chaveiros), 100 ou 50 unidades para itens intermediários e 20 unidades para itens de maior valor (mochilas, garrafas térmicas). O mínimo exato aparece em cada página de produto.';
        if ($op['prazo']) {
            $l[] = '- Prazo de produção: ' . Seo::prazoTexto($op['prazo']) . ' após a aprovação da arte';
        }
        if ($op['tecnicas']) {
            $l[] = '- Técnicas de personalização: ' . $op['tecnicas'];
        } else {
            $l[] = '- Técnicas de personalização: definidas conforme o material do brinde; a arte é aprovada pelo cliente antes da produção';
        }
        $l[] = '- Forma de orçamento: WhatsApp, com o nome e o código do produto pré-preenchidos pelo site';
        $l[] = '- Abrangência: envio para todo o Brasil';
        $l[] = '';
        $l[] = '## Páginas principais';
        $l[] = "- [Catálogo de brindes]({$h}/brindes): todas as categorias";
        $l[] = "- [Sobre a Novare Brindes]({$h}/sobre): quem somos e como trabalhamos";
        $l[] = "- [Atendimento B2B]({$h}/atendimento): como funciona o orçamento e a aprovação de arte";
        $l[] = '';
        $l[] = '## Categorias';
        foreach ($repo->categorias() as $c) {
            if ((int) $c['total'] < 8 || empty($c['slug'])) {
                continue;
            }
            // rótulo do painel (gênero e acento certos: "Canetas Personalizadas", "Escritório")
            $rotulo = trim((string) (Seo::categoriaPorSlug((string) $c['slug'])['rotulo_seo'] ?? '')) ?: $c['categoria'] . ' personalizados';
            $l[] = sprintf('- [%s](%s): %d modelos', $rotulo, $h . Seo::urlCategoriaSlug($c['slug']), (int) $c['total']);
        }
        try {
            $oc = $pdo->query('SELECT slug, titulo FROM seo_ocasioes WHERE seo_indexavel = 1 ORDER BY id')->fetchAll();
            if ($oc) {
                $l[] = '';
                $l[] = '## Brindes por ocasião';
                foreach ($oc as $o) {
                    $l[] = sprintf('- [%s](%s)', $o['titulo'], $h . Seo::urlOcasiao($o['slug']));
                }
            }
        } catch (Throwable $e) {
        }
        try {
            $bp = $pdo->query('SELECT slug, titulo FROM blog_posts WHERE ' . BlogController::WHERE_PUBLICADO . ' ORDER BY publicado_em DESC LIMIT 20')->fetchAll();
            if ($bp) {
                $l[] = '';
                $l[] = '## Guias do blog';
                foreach ($bp as $b) {
                    $l[] = sprintf('- [%s](%s)', $b['titulo'], $h . Seo::urlPost($b['slug']));
                }
            }
        } catch (Throwable $e) {
        }
        echo implode("\n", $l), "\n";
    }

    // ------------------------------------------------------------

    private static function sqlProdutos(string $select): string
    {
        // produto esgotado fica noindex (Seo::produtoIndexavel), então sai do sitemap
        $sql = "SELECT {$select} FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND slug IS NOT NULL" . Estoque::sqlProduto();
        if (Seo::portaoAtivo()) {
            $sql .= ' AND seo_indexavel = 1';
        }
        return $sql;
    }

    private static function cabecalho(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: public, max-age=3600');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    }

    private static function sitemap(string $loc, ?string $lastmod): void
    {
        echo '<sitemap><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>';
        if ($lastmod) {
            echo '<lastmod>' . $lastmod . '</lastmod>';
        }
        echo "</sitemap>\n";
    }

    private static function url(string $loc, ?string $lastmod, ?string $imagem = null): void
    {
        echo '<url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>';
        if ($lastmod) {
            $ts = strtotime((string) $lastmod);
            if ($ts) {
                echo '<lastmod>' . date('c', $ts) . '</lastmod>';
            }
        }
        if ($imagem) {
            echo '<image:image><image:loc>' . htmlspecialchars($imagem, ENT_XML1, 'UTF-8') . '</image:loc></image:image>';
        }
        echo "</url>\n";
    }

    private static function maxLastmod(PDO $pdo, string $tabela, string $coluna, string $where = '1=1'): ?string
    {
        try {
            $v = $pdo->query("SELECT MAX({$coluna}) FROM {$tabela} WHERE {$where}")->fetchColumn();
            return $v ? date('c', strtotime((string) $v)) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function conta(PDO $pdo, string $tabela, string $where): int
    {
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM {$tabela} WHERE {$where}")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
