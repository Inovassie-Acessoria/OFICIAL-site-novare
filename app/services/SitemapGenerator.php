<?php

declare(strict_types=1);

/**
 * Gerador de Sitemap Dinâmico para a Novare Brindes.
 * 
 * - Renderiza todas as URLs institucionais, catálogo, categorias e produtos ativos em tempo real.
 * - Utiliza a data de modificação real do banco de dados (updated_at) para o campo <lastmod>.
 * - Otimizado para não estourar memória com listagens grandes.
 */
final class SitemapGenerator
{
    public static function render(): void
    {
        // Força a resposta HTTP correta de XML
        header('Content-Type: application/xml; charset=utf-8');

        // Desativa cacheamento do sitemap para garantir atualizações imediatas nos indexadores
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $pdo = Database::connection();
        $repo = ProductRepository::create();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 1. Home Page (data da última atualização geral dos produtos como lastmod)
        try {
            $stmtHomeMod = $pdo->query('SELECT MAX(updated_at) FROM produtos WHERE ativo = 1');
            $homeMod = $stmtHomeMod->fetchColumn();
            $homeDate = $homeMod ? date('Y-m-d', strtotime((string) $homeMod)) : date('Y-m-d');
        } catch (Throwable $e) {
            $homeDate = date('Y-m-d');
        }
        self::writeUrl(urlAbsoluta('/'), $homeDate, 'daily', '1.0');

        // 2. Páginas Institucionais
        self::writeUrl(urlAbsoluta('/sobre'), $homeDate, 'weekly', '0.6');
        self::writeUrl(urlAbsoluta('/atendimento'), $homeDate, 'weekly', '0.6');
        self::writeUrl(urlAbsoluta('/fidelidade'), $homeDate, 'weekly', '0.6');

        // 3. Catálogo Geral
        self::writeUrl(urlAbsoluta('/catalogo'), $homeDate, 'daily', '0.8');

        // 4. Categorias Dinâmicas (Ajudam a incentivar sitelinks do Google para as categorias principais)
        try {
            $categorias = $repo->categorias();
            foreach ($categorias as $cat) {
                if (!empty($cat['categoria'])) {
                    $catUrl = urlAbsoluta('/catalogo?categoria=' . rawurlencode($cat['categoria']));
                    self::writeUrl($catUrl, $homeDate, 'weekly', '0.8');
                }
            }
        } catch (Throwable $e) {
            // Silencia para não quebrar a geração do sitemap em caso de erros locais de conexão
        }

        // 5. Produtos Dinâmicos (Filtrados por ativos e com imagem)
        try {
            $stmtProds = $pdo->query(
                "SELECT sku_pai, updated_at 
                 FROM produtos 
                 WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' 
                 ORDER BY id DESC"
            );
            
            while ($row = $stmtProds->fetch(PDO::FETCH_ASSOC)) {
                $prodUrl = urlAbsoluta('/produto/' . rawurlencode($row['sku_pai']));
                $lastmod = date('Y-m-d', strtotime((string) $row['updated_at']));
                self::writeUrl($prodUrl, $lastmod, 'weekly', '0.7');
            }
        } catch (Throwable $e) {
            // Silencia falhas individuais
        }

        echo '</urlset>';
    }

    private static function writeUrl(string $loc, string $lastmod, string $changefreq, string $priority): void
    {
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        echo "    <lastmod>" . $lastmod . "</lastmod>\n";
        echo "    <changefreq>" . $changefreq . "</changefreq>\n";
        echo "    <priority>" . $priority . "</priority>\n";
        echo "  </url>\n";
    }
}
