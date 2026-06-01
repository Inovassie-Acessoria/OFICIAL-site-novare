<?php

declare(strict_types=1);

/**
 * Router APENAS para o servidor embutido do PHP (php -S / start.bat).
 * Serve arquivos estáticos existentes diretamente e roteia o resto p/ index.php.
 * Em produção (Apache/Hostinger) este arquivo não é usado — quem roteia é o .htaccess.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . urldecode($uri);

if ($uri !== '/' && is_file($file)) {
    return false; // deixa o servidor embutido servir o arquivo (css, js, img, api/*.php)
}

require __DIR__ . '/index.php';
