<?php

declare(strict_types=1);

/**
 * Leitor leve de variáveis de ambiente.
 *
 * Ordem de resolução:
 *   1. Arquivo .env.local (desenvolvimento) — se existir
 *   2. Arquivo .env       (produção)        — se existir
 *   3. Variáveis de ambiente reais do servidor (getenv) — Hostinger
 *
 * Não depende de Composer/vendor (compatível com host compartilhado).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    private static bool $loaded = false;

    /**
     * Carrega o arquivo de ambiente (uma única vez).
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $root = dirname(__DIR__, 2); // raiz do projeto
        $candidates = $path !== null
            ? [$path]
            : [$root . '/.env.local', $root . '/.env'];

        foreach ($candidates as $file) {
            if (is_file($file) && is_readable($file)) {
                self::parse($file);
                break;
            }
        }

        self::$loaded = true;
    }

    private static function parse(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignora linhas vazias e comentários
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove comentário inline apenas quando o valor NÃO está entre aspas
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = rtrim(substr($value, 0, $hashPos));
                }
            }

            // Remove aspas envolventes
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' || $first === "'") && $first === $last) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$vars[$key] = $value;
        }
    }

    /**
     * Retorna uma variável (ou o default se ausente).
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (!self::$loaded) {
            self::load();
        }

        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }

        $fromEnv = getenv($key);
        if ($fromEnv !== false) {
            return $fromEnv;
        }

        return $default;
    }

    /**
     * Retorna uma variável obrigatória ou lança exceção.
     */
    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException("Variável de ambiente obrigatória ausente: {$key}");
        }
        return $value;
    }

    /**
     * Conveniência booleana (true/1/yes/on => true).
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
