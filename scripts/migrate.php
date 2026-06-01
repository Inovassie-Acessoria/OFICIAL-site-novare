<?php

declare(strict_types=1);

/**
 * Executa o schema do banco (scripts/schema.sql).
 *
 * Uso:
 *   php scripts/migrate.php
 *
 * Em ambiente LOCAL (APP_ENV=local) cria o banco automaticamente.
 * Em PRODUÇÃO assume que o banco já existe (criado no painel Hostinger,
 * onde o usuário da aplicação normalmente não tem CREATE DATABASE).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via linha de comando.\n");
}

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/Database.php';

Env::load();

$dbName = Env::require('DB_NAME');
$isLocal = Env::get('APP_ENV', 'production') === 'local';

echo "== Migração Novare Brindes ==\n";
echo "Banco: {$dbName} (" . Env::get('APP_ENV', 'production') . ")\n\n";

// --- 1. Em local: cria o banco se não existir ---
if ($isLocal) {
    try {
        $host = Env::require('DB_HOST');
        $port = Env::get('DB_PORT', '3306');
        $user = Env::require('DB_USER');
        $pass = Env::get('DB_PASSWORD', '') ?? '';

        $bootstrap = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $bootstrap->exec(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` "
            . "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        echo "[ok] Banco garantido (CREATE DATABASE IF NOT EXISTS).\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "[erro] Não foi possível criar o banco: {$e->getMessage()}\n");
        exit(1);
    }
}

// --- 2. Conecta no banco e roda o schema ---
try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, "[erro] Conexão falhou: {$e->getMessage()}\n");
    fwrite(STDERR, "Dica: confira DB_HOST/DB_USER/DB_PASSWORD no .env.local e se o MySQL está rodando.\n");
    exit(1);
}

$sqlFile = __DIR__ . '/schema.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "[erro] schema.sql não encontrado.\n");
    exit(1);
}

$statements = split_sql(file_get_contents($sqlFile) ?: '');

$count = 0;
foreach ($statements as $sql) {
    try {
        $pdo->exec($sql);
        $count++;
    } catch (PDOException $e) {
        fwrite(STDERR, "[erro] Statement falhou: {$e->getMessage()}\n");
        fwrite(STDERR, substr($sql, 0, 120) . "...\n");
        exit(1);
    }
}

echo "[ok] {$count} comandos executados.\n";
echo "\n✓ Schema aplicado com sucesso.\n";
echo "  Próximo passo: 'php scripts/seed.php' para dados de demonstração,\n";
echo "  ou 'php scripts/sync_xbz.php' para importar do catálogo XBZ.\n";

/**
 * Divide o conteúdo SQL em comandos individuais.
 * Remove comentários de linha (--) e separa por ';' no fim da linha.
 * Não suporta procedures/triggers (não há nenhum no schema).
 */
function split_sql(string $sql): array
{
    $clean = [];
    foreach (preg_split('/\R/', $sql) as $line) {
        $trim = ltrim($line);
        if (str_starts_with($trim, '--') || $trim === '') {
            continue;
        }
        $clean[] = $line;
    }
    $joined = implode("\n", $clean);

    $statements = [];
    foreach (explode(';', $joined) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }
    }
    return $statements;
}
