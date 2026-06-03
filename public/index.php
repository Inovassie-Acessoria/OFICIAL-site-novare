<?php

declare(strict_types=1);

/**
 * Front controller — roteia todas as requisições do site.
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Cabeçalhos de segurança também via PHP (caso o mod_headers não esteja ativo)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim(rawurldecode($path), '/');

$controller = new CatalogController();

try {
    switch (true) {
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

        case $path === '/' || $path === '':
            $controller->home();
            break;

        case $path === '/catalogo':
            $controller->catalogo();
            break;

        case $path === '/busca':
            $controller->busca();
            break;

        case (bool) preg_match('#^/produto/(.+)$#', $path, $m):
            $controller->produto(trim($m[1]));
            break;

        case in_array($path, ['/sobre', '/atendimento', '/fidelidade'], true):
            $controller->institucional(ltrim($path, '/'));
            break;

        case $path === '/status':
            $controller->status();
            break;

        default:
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
