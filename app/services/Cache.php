<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/**
 * Cache de arquivo simples para aliviar a host compartilhada.
 *
 * O catálogo é estável (sync semanal), então listagens/produtos podem ser
 * cacheados com TTL longo. A sync chama Cache::flush() ao final.
 *
 * Em APP_DEBUG=true o cache é ignorado (dev sempre vê dados frescos).
 */
final class Cache
{
    private static function dir(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache';
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . hash('sha256', $key) . '.json';
    }

    private static function habilitado(): bool
    {
        return !Env::bool('APP_DEBUG', false);
    }

    /**
     * Retorna o valor cacheado ou executa o callback, cacheia e retorna.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function remember(string $key, int $ttlSegundos, callable $callback)
    {
        if (!self::habilitado()) {
            return $callback();
        }

        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        self::put($key, $value, $ttlSegundos);
        return $value;
    }

    public static function get(string $key): mixed
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !array_key_exists('exp', $data)) {
            return null;
        }
        if ($data['exp'] !== 0 && $data['exp'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['val'] ?? null;
    }

    public static function put(string $key, mixed $value, int $ttlSegundos): void
    {
        $file = self::path($key);
        $exp  = $ttlSegundos > 0 ? time() + $ttlSegundos : 0;
        @file_put_contents(
            $file,
            json_encode(['exp' => $exp, 'val' => $value], JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /** Remove todo o cache (chamado pela sync). */
    public static function flush(): void
    {
        foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
    }
}
