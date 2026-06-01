<?php

declare(strict_types=1);

/**
 * Sincronização do catálogo XBZ (rodado pelo cron semanal).
 *
 * Uso:
 *   php scripts/sync_xbz.php              # busca da API XBZ
 *   php scripts/sync_xbz.php --file=storage/xbz_sample.json   # offline (teste)
 *
 * A lógica de sincronização vive em app/services/CatalogSync.php (compartilhada
 * com o migrador web). Este script é apenas o ponto de entrada da CLI: resolve a
 * origem dos dados (API ou arquivo) e loga o resultado.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via linha de comando.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/Database.php';
require_once __DIR__ . '/../app/services/XBZService.php';
require_once __DIR__ . '/../app/services/ProductMapper.php';
require_once __DIR__ . '/../app/services/Cache.php';
require_once __DIR__ . '/../app/services/CatalogSync.php';

Env::load();

$rootDir = dirname(__DIR__);
$logFile = $rootDir . '/storage/logs/sync.log';

/** Loga no stdout e no arquivo (sem dados sensíveis). */
$log = static function (string $msg) use ($logFile): void {
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $linha . "\n";
    @file_put_contents($logFile, $linha . "\n", FILE_APPEND);
};

// Opção --file= para teste offline
$arquivoLocal = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $arquivoLocal = substr($arg, 7);
    }
}

$xbz = XBZService::fromEnv();

$fetcher = static function () use ($xbz, $arquivoLocal, $rootDir, $log): array {
    if ($arquivoLocal !== null) {
        $log("Lendo arquivo local: {$arquivoLocal}");
        return $xbz->fromFile($rootDir . '/' . ltrim($arquivoLocal, '/\\'));
    }
    $log('Chamando GetListaDeProdutos...');
    return $xbz->getListaDeProdutos();
};

$resultado = CatalogSync::create($log)->executar($fetcher);

exit($resultado['ok'] ? 0 : 1);
