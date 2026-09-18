<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Cache.php';

/**
 * Baixa a imagem principal do produto (hospedada no domínio da XBZ), converte
 * para WebP e serve do nosso domínio.
 *
 * Por quê: imagem puxada direto do fornecedor adiciona DNS, TLS e dependência
 * de terceiro no caminho crítico (LCP) e impede indexação no Google Imagens,
 * que em brindes gera volume real.
 *
 * Roda em lotes (botão no painel / migrate-web), porque são ~3.500 downloads
 * em hospedagem compartilhada. Os arquivos vão para public/assets/uploads/
 * produtos/, pasta que o deploy NÃO apaga. Se a XBZ trocar a imagem
 * (imagem_local_origem <> imagem_principal), o produto entra na fila de novo.
 */
final class ImagemLocal
{
    public const DIR_REL = '/assets/uploads/produtos';
    public const LARGURA = 800;

    public static function disponivel(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    /** @return array{pendentes:int,feitas:int} */
    public static function status(PDO $pdo): array
    {
        $base = "FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> ''";
        return [
            'pendentes' => (int) $pdo->query("SELECT COUNT(*) {$base} AND (imagem_local IS NULL OR imagem_local_origem IS NULL OR imagem_local_origem <> imagem_principal)")->fetchColumn(),
            'feitas'    => (int) $pdo->query("SELECT COUNT(*) {$base} AND imagem_local IS NOT NULL AND imagem_local_origem = imagem_principal")->fetchColumn(),
        ];
    }

    /**
     * Processa até $lote produtos pendentes. Prioriza os que o admin fixou nos
     * "Top" e nas ocasiões (os mais vistos), depois por id.
     * @return array{processadas:int,erros:int,restantes:int,mensagens:string[]}
     */
    public static function processar(PDO $pdo, int $lote = 40, int $tempoMaxSeg = 50): array
    {
        $r = ['processadas' => 0, 'erros' => 0, 'restantes' => 0, 'mensagens' => []];
        if (!self::disponivel()) {
            $r['mensagens'][] = 'Extensão GD com WebP indisponível neste PHP.';
            return $r;
        }
        $dirAbs = APP_ROOT_PATH() . '/public' . self::DIR_REL;
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0775, true)) {
            $r['mensagens'][] = 'Não foi possível criar ' . self::DIR_REL;
            return $r;
        }

        $st = $pdo->prepare(
            "SELECT id, slug, sku_pai, imagem_principal FROM produtos
             WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND slug IS NOT NULL
               AND (imagem_local IS NULL OR imagem_local_origem IS NULL OR imagem_local_origem <> imagem_principal)
             ORDER BY id LIMIT :l"
        );
        $st->bindValue(':l', max(1, $lote), PDO::PARAM_INT);
        $st->execute();
        $upd = $pdo->prepare('UPDATE produtos SET imagem_local = :l, imagem_local_origem = :o WHERE id = :id');
        $inicio = time();

        foreach ($st->fetchAll() as $p) {
            if (time() - $inicio > $tempoMaxSeg) {
                $r['mensagens'][] = 'Tempo do lote esgotado; continue clicando para processar o restante.';
                break;
            }
            $destino = $dirAbs . '/' . $p['slug'] . '.webp';
            if (self::baixarEConverter((string) $p['imagem_principal'], $destino)) {
                $upd->execute([':l' => self::DIR_REL . '/' . $p['slug'] . '.webp', ':o' => $p['imagem_principal'], ':id' => $p['id']]);
                $r['processadas']++;
            } else {
                // marca a origem para não travar a fila num item quebrado; imagem_local fica NULL (usa a da XBZ)
                $upd->execute([':l' => null, ':o' => $p['imagem_principal'], ':id' => $p['id']]);
                $r['erros']++;
            }
        }
        $r['restantes'] = self::status($pdo)['pendentes'];
        if ($r['processadas'] > 0) {
            Cache::flush();
        }
        return $r;
    }

    private static function baixarEConverter(string $url, string $destino): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'NovareBrindes/1.0'], 'ssl' => ['verify_peer' => true]]);
        $bin = @file_get_contents($url, false, $ctx);
        if ($bin === false || strlen($bin) < 100) {
            return false;
        }
        $src = @imagecreatefromstring($bin);
        if (!$src) {
            return false;
        }
        $ow = imagesx($src);
        $oh = imagesy($src);
        $w  = min(self::LARGURA, $ow);
        $h  = (int) round($oh * ($w / max(1, $ow)));
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transp = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $transp);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $ow, $oh);
        $ok = imagewebp($dst, $destino, 82);
        imagedestroy($dst);
        imagedestroy($src);
        return $ok && is_file($destino) && filesize($destino) > 0;
    }
}

if (!function_exists('APP_ROOT_PATH')) {
    function APP_ROOT_PATH(): string
    {
        return defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    }
}
