<?php

declare(strict_types=1);

/**
 * Bootstrap da aplicação web.
 * Carrega config, registra autoload das classes de app/ e expõe helpers.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('APP_VIEWS', __DIR__ . '/views');

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/Database.php';

Env::load();

// Autoload simples para app/services e app/controllers
spl_autoload_register(static function (string $class): void {
    foreach (['services', 'controllers'] as $dir) {
        $file = APP_DIR . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/config/helpers.php';

// Em produção, esconde erros do usuário (vão para o log).
if (Env::bool('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/app.log');
}
