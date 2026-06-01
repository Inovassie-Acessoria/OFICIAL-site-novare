<?php

declare(strict_types=1);

/**
 * Sincronização do catálogo XBZ (rodado pelo cron semanal).
 *
 * Uso:
 *   php scripts/sync_xbz.php              # busca da API XBZ
 *   php scripts/sync_xbz.php --file=storage/xbz_sample.json   # offline (teste)
 *
 * Fluxo seguro:
 *  1. Abre um registro em sync_runs (auditoria).
 *  2. Busca a lista da XBZ. Se falhar, marca erro e SAI sem tocar no catálogo.
 *  3. Agrupa por CodigoAmigavel (pai) e faz UPSERT transacional de
 *     produtos + variacoes + imagens.
 *  4. Marca como inativo (nunca apaga) o que não veio nesta sync.
 *  5. Limpa o cache do catálogo.
 *  6. Fecha o registro em sync_runs e loga o resumo.
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

$pdo = Database::connection();

// ---------------------------------------------------------------
// 1. Abre o registro de auditoria
// ---------------------------------------------------------------
$inicio = date('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO sync_runs (iniciado_em, status) VALUES (:i, :s)')
    ->execute([':i' => $inicio, ':s' => 'em_andamento']);
$runId = (int) $pdo->lastInsertId();
$log("== Sync XBZ iniciada (run #{$runId}) ==");

$fecharRun = static function (string $status, array $c, string $msg = '') use ($pdo, $runId): void {
    $pdo->prepare(
        'UPDATE sync_runs SET finalizado_em = NOW(), status = :st,
            pais_inseridos = :pi, pais_atualizados = :pa,
            variacoes_inseridas = :vi, variacoes_atualizadas = :va,
            sufixos_desconhecidos = :sd, mensagem = :m
         WHERE id = :id'
    )->execute([
        ':st' => $status,
        ':pi' => $c['pais_ins'] ?? 0, ':pa' => $c['pais_upd'] ?? 0,
        ':vi' => $c['var_ins'] ?? 0, ':va' => $c['var_upd'] ?? 0,
        ':sd' => $c['sufixos_desconhecidos'] ?? 0,
        ':m'  => mb_substr($msg, 0, 1000),
        ':id' => $runId,
    ]);
};

// ---------------------------------------------------------------
// 2. Busca os dados (falha = não tocar no catálogo)
// ---------------------------------------------------------------
try {
    $xbz = XBZService::fromEnv();
    if ($arquivoLocal !== null) {
        $log("Lendo arquivo local: {$arquivoLocal}");
        $itens = $xbz->fromFile($rootDir . '/' . ltrim($arquivoLocal, '/\\'));
    } else {
        $log('Chamando GetListaDeProdutos...');
        $itens = $xbz->getListaDeProdutos();
    }
} catch (Throwable $e) {
    $log('[ERRO] Falha ao obter dados da XBZ: ' . $e->getMessage());
    $fecharRun('erro', [], 'Falha ao obter dados: ' . $e->getMessage());
    exit(1);
}

$total = count($itens);
if ($total === 0) {
    $log('[ERRO] Lista vazia — abortando para não inativar o catálogo inteiro.');
    $fecharRun('erro', [], 'Lista vazia da XBZ.');
    exit(1);
}
$log("Recebidos {$total} itens (variações).");

// ---------------------------------------------------------------
// 3. Agrupa por pai (CodigoAmigavel)
// ---------------------------------------------------------------
$grupos = [];
foreach ($itens as $it) {
    $pai = trim((string) ($it['CodigoAmigavel'] ?? ''));
    if ($pai === '') {
        // sem pai: usa o próprio composto como pai
        $pai = trim((string) ($it['CodigoComposto'] ?? ''));
    }
    if ($pai === '') {
        continue; // item irrecuperável
    }
    $grupos[$pai][] = $it;
}
unset($itens);
$log('Pais distintos: ' . count($grupos));

// ---------------------------------------------------------------
// Statements reaproveitados
// ---------------------------------------------------------------
$syncStamp = date('Y-m-d H:i:s');

$upProduto = $pdo->prepare(
    'INSERT INTO produtos
        (sku_pai, nome, descricao, categoria, material, preco_base, sustentavel, imagem_principal, ativo, synced_at)
     VALUES (:sku, :nome, :desc, :cat, :mat, :preco, :sus, :img, 1, :sy)
     ON DUPLICATE KEY UPDATE
        id = LAST_INSERT_ID(id),
        nome = VALUES(nome), descricao = VALUES(descricao), categoria = VALUES(categoria),
        material = VALUES(material), preco_base = VALUES(preco_base), sustentavel = VALUES(sustentavel),
        imagem_principal = VALUES(imagem_principal), ativo = 1, synced_at = VALUES(synced_at)'
);

$upVariacao = $pdo->prepare(
    'INSERT INTO variacoes
        (produto_id, sku_completo, cor_sufixo, cor, cor_codigo, estoque, ativo, synced_at)
     VALUES (:pid, :sku, :suf, :cor, :hex, :est, 1, :sy)
     ON DUPLICATE KEY UPDATE
        id = LAST_INSERT_ID(id),
        produto_id = VALUES(produto_id), cor_sufixo = VALUES(cor_sufixo), cor = VALUES(cor),
        cor_codigo = VALUES(cor_codigo), estoque = VALUES(estoque), ativo = 1, synced_at = VALUES(synced_at)'
);

// Registra sufixos novos para revisão (não sobrescreve correções manuais).
$upCor = $pdo->prepare(
    'INSERT INTO cores (sufixo, nome, hex, revisar) VALUES (:suf, :nome, :hex, :rev)
     ON DUPLICATE KEY UPDATE sufixo = sufixo'
);

$delImgs = $pdo->prepare('DELETE FROM imagens WHERE variacao_id = :vid');
$insImg  = $pdo->prepare('INSERT INTO imagens (variacao_id, url, ordem, principal) VALUES (:vid, :url, 1, 1)');

// ---------------------------------------------------------------
// 4. Upsert transacional
// ---------------------------------------------------------------
$c = ['pais_ins' => 0, 'pais_upd' => 0, 'var_ins' => 0, 'var_upd' => 0];
$sufixosDesconhecidos = [];

try {
    $pdo->beginTransaction();

    foreach ($grupos as $skuPai => $variacoes) {
        $skuPai    = (string) $skuPai; // chaves numéricas viram int em PHP
        $primeiro  = $variacoes[0];
        $nome      = trim((string) ($primeiro['Nome'] ?? '')) ?: 'Produto ' . $skuPai;
        $descricao = trim((string) ($primeiro['Descricao'] ?? ''));

        // preço-base = menor preço positivo entre as variações
        $precos = [];
        foreach ($variacoes as $v) {
            $p = (float) ($v['PrecoVenda'] ?? 0);
            if ($p > 0) {
                $precos[] = $p;
            }
        }
        $precoBase = $precos ? min($precos) : null;

        // imagem principal = primeira ImageLink não vazia
        $imgPrincipal = null;
        foreach ($variacoes as $v) {
            $link = trim((string) ($v['ImageLink'] ?? ''));
            if ($link !== '') {
                $imgPrincipal = $link;
                break;
            }
        }

        $categoria   = ProductMapper::categoria($nome);
        $material    = ProductMapper::material($nome, $descricao);
        $sustentavel = ProductMapper::sustentavel($nome, $descricao) ? 1 : 0;

        $upProduto->execute([
            ':sku' => $skuPai, ':nome' => $nome, ':desc' => $descricao !== '' ? $descricao : null,
            ':cat' => $categoria, ':mat' => $material, ':preco' => $precoBase,
            ':sus' => $sustentavel, ':img' => $imgPrincipal, ':sy' => $syncStamp,
        ]);
        $produtoId = (int) $pdo->lastInsertId();
        $upProduto->rowCount() === 1 ? $c['pais_ins']++ : $c['pais_upd']++;

        foreach ($variacoes as $v) {
            $composto = trim((string) ($v['CodigoComposto'] ?? ''));
            if ($composto === '') {
                continue;
            }
            $sufixo  = ProductMapper::sufixoCor($composto, $skuPai);
            $corNome = trim((string) ($v['CorWebPrincipal'] ?? '')) ?: 'Padrão';
            $corInfo = ProductMapper::corHex($corNome);
            $estoque = (int) ($v['QuantidadeDisponivel'] ?? 0);

            $upVariacao->execute([
                ':pid' => $produtoId, ':sku' => $composto, ':suf' => $sufixo,
                ':cor' => $corNome, ':hex' => $corInfo['hex'], ':est' => $estoque, ':sy' => $syncStamp,
            ]);
            $variacaoId = (int) $pdo->lastInsertId();
            $upVariacao->rowCount() === 1 ? $c['var_ins']++ : $c['var_upd']++;

            // registra cor para revisão se hex foi genérico
            if (!$corInfo['conhecida']) {
                $sufixosDesconhecidos[$sufixo] = true;
            }
            $upCor->execute([
                ':suf' => $sufixo, ':nome' => $corNome, ':hex' => $corInfo['hex'],
                ':rev' => $corInfo['conhecida'] ? 0 : 1,
            ]);

            // imagem (XBZ entrega 1 por variação)
            $link = trim((string) ($v['ImageLink'] ?? ''));
            $delImgs->execute([':vid' => $variacaoId]);
            if ($link !== '') {
                $insImg->execute([':vid' => $variacaoId, ':url' => $link]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $log('[ERRO] Falha durante o upsert (rollback aplicado): ' . $e->getMessage());
    $fecharRun('erro', $c, 'Falha no upsert: ' . $e->getMessage());
    exit(1);
}

// ---------------------------------------------------------------
// 5. Inativa o que não veio nesta sync (nunca apaga)
// ---------------------------------------------------------------
$stmtInaP = $pdo->prepare('UPDATE produtos SET ativo = 0 WHERE ativo = 1 AND (synced_at IS NULL OR synced_at < :sy)');
$stmtInaP->execute([':sy' => $syncStamp]);
$paisInativados = $stmtInaP->rowCount();

$stmtInaV = $pdo->prepare('UPDATE variacoes SET ativo = 0 WHERE ativo = 1 AND (synced_at IS NULL OR synced_at < :sy)');
$stmtInaV->execute([':sy' => $syncStamp]);
$varInativadas = $stmtInaV->rowCount();

// ---------------------------------------------------------------
// 6. Invalida o cache do catálogo
// ---------------------------------------------------------------
$removidos = count(glob($rootDir . '/storage/cache/*.json') ?: []);
Cache::flush();

$c['sufixos_desconhecidos'] = count($sufixosDesconhecidos);
$resumo = sprintf(
    'Pais: +%d/~%d | Variações: +%d/~%d | Inativados: %d pais, %d var | Cores novas p/ revisão: %d | Cache limpo: %d',
    $c['pais_ins'], $c['pais_upd'], $c['var_ins'], $c['var_upd'],
    $paisInativados, $varInativadas, $c['sufixos_desconhecidos'], $removidos
);
$log($resumo);
$fecharRun('sucesso', $c, $resumo);
$log("== Sync XBZ concluída (run #{$runId}) ==");
exit(0);
