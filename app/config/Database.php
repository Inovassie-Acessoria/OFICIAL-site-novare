<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Fábrica de conexão PDO (singleton de processo).
 *
 * - Charset utf8mb4 (suporte completo a acentos/emoji)
 * - Exceções habilitadas (ERRMODE_EXCEPTION)
 * - Prepared statements REAIS (EMULATE_PREPARES = false) -> segurança
 * - Erro de conexão é logado, mas a mensagem ao usuário é genérica
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::require('DB_HOST');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::require('DB_NAME');
        $user = Env::require('DB_USER');
        $pass = Env::get('DB_PASSWORD', '') ?? '';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Detalhe vai só para o log; usuário recebe mensagem genérica.
            error_log('[Database] Falha de conexão: ' . $e->getMessage());
            throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $e);
        }

        return self::$pdo;
    }

    /**
     * Útil para testes/reset de estado.
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
