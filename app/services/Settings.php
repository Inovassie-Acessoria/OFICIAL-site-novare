<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Armazém chave-valor (JSON) das configurações editáveis pelo admin.
 *
 * É a FONTE ÚNICA da verdade compartilhada entre o painel /settings-admin e o
 * site público: tudo que o admin salva aqui é lido pelas views (banners,
 * imagens de categorias, rankings dos "Top", logo e prompt da IA). Quando uma
 * chave não existe, o site cai no valor padrão (o conteúdo atual hardcoded),
 * garantindo que o painel sempre reflita exatamente o que está no ar.
 */
final class Settings
{
    /** @var array<string,?string>|null cache por processo */
    private static ?array $cache = null;

    private static function pdo(): PDO
    {
        return Database::connection();
    }

    public static function ensureTable(): void
    {
        self::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS configuracoes (
                chave         VARCHAR(64)  NOT NULL,
                valor         LONGTEXT     NULL,
                atualizado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (chave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string,?string> */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            self::ensureTable();
            foreach (self::pdo()->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $r) {
                self::$cache[$r['chave']] = $r['valor'];
            }
        } catch (Throwable $e) {
            // Banco indisponível: devolve vazio para o site usar os padrões.
            self::$cache = [];
        }
        return self::$cache;
    }

    /** Lê uma configuração (decodifica JSON). Devolve $default se não existir. */
    public static function get(string $chave, mixed $default = null): mixed
    {
        $all = self::load();
        if (!array_key_exists($chave, $all) || $all[$chave] === null || $all[$chave] === '') {
            return $default;
        }
        $dec = json_decode((string) $all[$chave], true);
        if ($dec === null && json_last_error() !== JSON_ERROR_NONE) {
            return $all[$chave]; // valor cru (string simples)
        }
        return $dec;
    }

    /** Grava (upsert) uma configuração serializada em JSON. */
    public static function set(string $chave, mixed $valor): void
    {
        self::ensureTable();
        $json = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = self::pdo()->prepare(
            'INSERT INTO configuracoes (chave, valor) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute([':c' => $chave, ':v' => $json]);
        if (self::$cache !== null) {
            self::$cache[$chave] = $json;
        }
    }
}
