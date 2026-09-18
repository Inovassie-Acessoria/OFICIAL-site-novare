<?php

declare(strict_types=1);

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Seo.php';

/**
 * IndexNow: avisa Bing, Yandex e Seznam que URLs mudaram (indexação em
 * horas, não semanas). O ChatGPT com busca depende do índice do Bing e o
 * Copilot É o Bing — quem só cuida do Google ignora metade do GEO.
 *
 * A chave é gerada uma vez e guardada em `configuracoes`; o front controller
 * responde /{chave}.txt com a própria chave (é assim que o Bing valida o domínio).
 *
 * Só enviamos o que mudou de verdade (conteudo_alterado_em) — disparar o
 * catálogo inteiro todo dia queima confiança.
 */
final class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/IndexNow';
    private const LOTE_MAX = 10000;

    public static function chave(): string
    {
        $k = (string) Settings::get('indexnow_key', '');
        if (!preg_match('/^[a-f0-9]{32}$/', $k)) {
            $k = bin2hex(random_bytes(16));
            Settings::set('indexnow_key', $k);
        }
        return $k;
    }

    public static function chaveValida(string $candidata): bool
    {
        return hash_equals(self::chave(), $candidata);
    }

    /**
     * @param string[] $urls absolutas
     * @return array{ok:bool,motivo?:string,lotes?:array}
     */
    public static function enviar(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if (!$urls) {
            return ['ok' => false, 'motivo' => 'sem urls'];
        }
        if (Env::get('APP_ENV', 'production') === 'local') {
            return ['ok' => false, 'motivo' => 'ambiente local'];
        }
        $host = (string) parse_url(Seo::host(), PHP_URL_HOST);
        $key  = self::chave();
        $lotes = [];

        foreach (array_chunk($urls, self::LOTE_MAX) as $lote) {
            $payload = json_encode([
                'host'        => $host,
                'key'         => $key,
                'keyLocation' => Seo::host() . '/' . $key . '.txt',
                'urlList'     => $lote,
            ], JSON_UNESCAPED_SLASHES);

            $ch = curl_init(self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // 200/202 = aceito. 422 costuma ser chave não encontrada no domínio.
            $lotes[] = ['urls' => count($lote), 'status' => $status];
        }
        Settings::set('indexnow_ultimo', ['em' => date('Y-m-d H:i:s'), 'urls' => count($urls), 'lotes' => $lotes]);
        return ['ok' => true, 'lotes' => $lotes];
    }

    /**
     * Chame no FIM da sincronização com a XBZ (ou do salvar do admin) só com o
     * que mudou desde $desde.
     */
    public static function enviarAlteracoes(PDO $pdo, string $desde): array
    {
        $urls = [];
        $sql = "SELECT slug, sku_pai FROM produtos
                WHERE ativo = 1 AND slug IS NOT NULL AND imagem_principal IS NOT NULL AND imagem_principal <> ''
                  AND conteudo_alterado_em >= :d";
        if (Seo::portaoAtivo()) {
            $sql .= ' AND seo_indexavel = 1';
        }
        $st = $pdo->prepare($sql . ' LIMIT ' . self::LOTE_MAX);
        $st->execute([':d' => $desde]);
        foreach ($st->fetchAll() as $p) {
            $urls[] = Seo::canonical(Seo::urlProduto($p));
        }
        try {
            $st = $pdo->prepare('SELECT slug FROM categorias WHERE seo_indexavel = 1 AND updated_at >= :d');
            $st->execute([':d' => $desde]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $slug) {
                $urls[] = Seo::canonical(Seo::urlCategoriaSlug($slug));
            }
            $st = $pdo->prepare('SELECT slug FROM seo_ocasioes WHERE seo_indexavel = 1 AND updated_at >= :d');
            $st->execute([':d' => $desde]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $slug) {
                $urls[] = Seo::canonical(Seo::urlOcasiao($slug));
            }
        } catch (Throwable $e) {
        }
        return self::enviar($urls);
    }
}
