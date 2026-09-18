<?php

declare(strict_types=1);

/**
 * Front controller — roteia todas as requisições do site.
 *
 * Mapa de URLs (ver Seo.php para a geração):
 *   /                                   home
 *   /brindes                            hub do catálogo
 *   /brindes/{categoria}                categoria
 *   /brindes/{categoria}/material/{m}   faceta (promovida = indexável; livre = noindex)
 *   /brindes/produto/{slug}             produto
 *   /{ocasiao}                          landing de ocasião (tabela seo_ocasioes)
 *   /produto/{sku}, /catalogo           LEGADO -> 301
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Cabeçalhos de segurança também via PHP (caso o mod_headers não esteja ativo)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pathBruto = $path;
$path = '/' . trim(rawurldecode($path), '/');

// ------------------------------------------------------------
// Host canônico + sem barra final (301). Camada em PHP garante o
// comportamento mesmo que o .htaccess não seja processado.
// ------------------------------------------------------------
$hostAtual = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
// Só pelo host: o .env do servidor pode estar com APP_ENV=local por engano, e aí
// a canonicalização seria pulada em produção.
$ehLocal   = $hostAtual === '' || str_starts_with($hostAtual, 'localhost') || str_starts_with($hostAtual, '127.0.0.1');
if (!$ehLocal && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $hostCanonico = strtolower((string) parse_url(Seo::host(), PHP_URL_HOST));
    $precisaRedirect = false;
    $novoHost = $hostAtual;
    if ($hostCanonico !== '' && $hostAtual !== $hostCanonico && preg_replace('/^www\./', '', $hostAtual) === preg_replace('/^www\./', '', $hostCanonico)) {
        $novoHost = $hostCanonico;
        $precisaRedirect = true;
    }
    $novoPath = $pathBruto;
    if ($pathBruto !== '/' && str_ends_with($pathBruto, '/')) {
        $novoPath = rtrim($pathBruto, '/');
        $precisaRedirect = true;
    }
    if ($precisaRedirect) {
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        header('Location: https://' . $novoHost . $novoPath . ($qs !== '' ? '?' . $qs : ''), true, 301);
        exit;
    }
}

// Schema/dados do pacote de SEO: cria o que falta no 1º request após o deploy.
SeoMigration::garantir();

$controller = new CatalogController();

try {
    switch (true) {
        // ---- SEO técnico ----
        case $path === '/sitemap.xml':
            SitemapGenerator::indice();
            break;
        case (bool) preg_match('#^/sitemap-([a-z]+)(?:-(\d+))?\.xml$#', $path, $m):
            SitemapGenerator::filho($m[1], (int) ($m[2] ?? 1));
            break;
        case $path === '/llms.txt':
            SitemapGenerator::llms();
            break;
        case (bool) preg_match('#^/([a-f0-9]{32})\.txt$#', $path, $m) && IndexNow::chaveValida($m[1]):
            header('Content-Type: text/plain; charset=utf-8');
            echo $m[1];
            break;
        case (bool) preg_match('#^/media/logo\.(png|jpg|webp|svg|gif)$#', $path):
            SiteContent::servirLogo();
            break;

        // ---- Painel oculto /settings-admin (sem links no site) ----
        case $path === '/settings-admin':
            (new AdminController())->index();
            break;
        case $path === '/settings-admin/login':
            (new AdminController())->login();
            break;
        case $path === '/settings-admin/logout':
            (new AdminController())->logout();
            break;
        case $path === '/settings-admin/salvar':
            (new AdminController())->salvar();
            break;
        case $path === '/settings-admin/upload':
            (new AdminController())->upload();
            break;
        case $path === '/settings-admin/sku':
            (new AdminController())->sku();
            break;
        case $path === '/settings-admin/seo':
            (new AdminController())->seo();
            break;

        // ---- Site ----
        case $path === '/' || $path === '':
            $controller->home();
            break;

        case $path === '/brindes':
            $controller->hub();
            break;

        case (bool) preg_match('#^/brindes/produto/([a-z0-9][a-z0-9-]*)$#', $path, $m):
            $controller->produto($m[1]);
            break;

        case (bool) preg_match('#^/brindes/([a-z0-9-]+)/(material)/([a-z0-9-]+)$#', $path, $m):
            $controller->faceta($m[1], $m[2], $m[3]);
            break;

        case (bool) preg_match('#^/brindes/([a-z0-9-]+)$#', $path, $m):
            $controller->categoria($m[1]);
            break;

        case $path === '/busca':
            $controller->busca();
            break;

        // ---- Legado (301) ----
        case $path === '/catalogo':
            $controller->catalogoLegado();
            break;
        case (bool) preg_match('#^/produto/(.+)$#', $path, $m):
            $controller->produtoLegado(trim($m[1]));
            break;

        case in_array($path, ['/sobre', '/atendimento', '/fidelidade'], true):
            $controller->institucional(ltrim($path, '/'));
            break;

        case $path === '/status':
            $controller->status();
            break;

        default:
            // landing de ocasião /{slug}; senão 404 (que ainda consulta a tabela seo_redirects)
            if (preg_match('#^/([a-z0-9][a-z0-9-]{2,})$#', $path, $m) && $controller->ocasiao($m[1])) {
                break;
            }
            $controller->erro404();
    }
} catch (Throwable $e) {
    error_log('[Front] ' . $e->getMessage());
    http_response_code(500);
    if (Env::bool('APP_DEBUG', false)) {
        echo '<pre>' . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        $controller->erro500();
    }
}
