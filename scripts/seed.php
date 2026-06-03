<?php

declare(strict_types=1);

/**
 * Dados de DEMONSTRAÇÃO para preview local (idempotente).
 *
 * Uso:  php scripts/seed.php
 *
 * Cria alguns produtos-pai fictícios (sku_pai na faixa 9000x) com
 * variações de cor e imagens placeholder. NÃO usar em produção —
 * a fonte da verdade é a sincronização XBZ (sync_xbz.php).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via linha de comando.\n");
}

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/Database.php';
require_once __DIR__ . '/../app/services/ProductMapper.php';

Env::load();
$pdo = Database::connection();

/** Produtos de demonstração: [sku_pai, nome, descricao, categoria, material, preco, qtd_min, sustentavel, [variacoes sufixo]] */
$demo = [
    ['90001', 'Garrafa Térmica Inox 500ml', 'Garrafa térmica em aço inox com parede dupla, mantém a temperatura por até 12h. Ideal para kits de onboarding.', 'Garrafas e Copos', 'Aço Inox', 89.90, 50, 0, ['PRE', 'AZ', 'VM', 'PRT']],
    ['90002', 'Caderno Capa Dura A5', 'Caderno corporativo capa dura, miolo pautado 80 folhas, com elástico e marcador.', 'Escritório', 'Papel / Couro Sintético', 34.50, 100, 1, ['PRE', 'AZM', 'VNH']],
    ['90003', 'Caneta Metal Premium', 'Caneta esferográfica em metal escovado, escrita azul, embalagem individual para presente.', 'Canetas', 'Metal', 19.90, 200, 0, ['PRT', 'DOU', 'PRE']],
    ['90004', 'Ecobag Algodão Cru', 'Sacola ecológica 100% algodão cru, alça reforçada, área ampla para personalização.', 'Sacolas', 'Algodão', 12.00, 300, 1, ['NAT', 'PRE', 'AZ']],
    ['90005', 'Mochila Executiva Notebook', 'Mochila para notebook 15", compartimento acolchoado, saída USB e tecido resistente à água.', 'Mochilas e Bolsas', 'Poliéster', 159.00, 30, 0, ['PRE', 'CZ', 'AZM']],
    ['90006', 'Squeeze Esportivo 750ml', 'Squeeze plástico livre de BPA, bico retrátil, perfeito para eventos esportivos.', 'Garrafas e Copos', 'Plástico (PP)', 15.90, 250, 1, ['BRC', 'AZ', 'VD', 'LJ', 'RS']],
];

$insertProduto = $pdo->prepare(
    'INSERT INTO produtos (sku_pai, nome, descricao, categoria, material, preco_base, quantidade_minima, sustentavel, imagem_principal, tags, ativo)
     VALUES (:sku, :nome, :desc, :cat, :mat, :preco, :qmin, :sus, :img, :tags, 1)
     ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), categoria=VALUES(categoria),
        material=VALUES(material), preco_base=VALUES(preco_base), quantidade_minima=VALUES(quantidade_minima),
        sustentavel=VALUES(sustentavel), imagem_principal=VALUES(imagem_principal), tags=VALUES(tags), ativo=1'
);

$findProduto = $pdo->prepare('SELECT id FROM produtos WHERE sku_pai = :sku');
$findCor     = $pdo->prepare('SELECT nome, hex FROM cores WHERE sufixo = :suf');
$insertVar   = $pdo->prepare(
    'INSERT INTO variacoes (produto_id, sku_completo, cor_sufixo, cor, cor_codigo, estoque, ativo)
     VALUES (:pid, :sku, :suf, :cor, :hex, :est, 1)
     ON DUPLICATE KEY UPDATE cor=VALUES(cor), cor_codigo=VALUES(cor_codigo), estoque=VALUES(estoque), ativo=1'
);
$findVar     = $pdo->prepare('SELECT id FROM variacoes WHERE sku_completo = :sku');
$delImgs     = $pdo->prepare('DELETE FROM imagens WHERE variacao_id = :vid');
$insImg      = $pdo->prepare('INSERT INTO imagens (variacao_id, url, ordem, principal) VALUES (:vid, :url, :ord, :pri)');

$pdo->beginTransaction();
$nProd = 0; $nVar = 0;

foreach ($demo as [$sku, $nome, $desc, $cat, $mat, $preco, $qmin, $sus, $sufixos]) {
    // imagem principal placeholder (cor do tema por sku)
    $imgPrincipal = "https://picsum.photos/seed/novare{$sku}/600/600";

    $tags = ProductMapper::gerarTags($cat ?? '', $nome ?? '', $desc ?? '');
    $insertProduto->execute([
        ':sku' => $sku, ':nome' => $nome, ':desc' => $desc, ':cat' => $cat,
        ':mat' => $mat, ':preco' => $preco, ':qmin' => $qmin, ':sus' => $sus, ':img' => $imgPrincipal,
        ':tags' => $tags,
    ]);
    $findProduto->execute([':sku' => $sku]);
    $produtoId = (int) $findProduto->fetchColumn();
    $nProd++;

    foreach ($sufixos as $i => $suf) {
        $findCor->execute([':suf' => $suf]);
        $cor = $findCor->fetch();
        $corNome = $cor['nome'] ?? $suf;
        $corHex  = $cor['hex'] ?? '#9AA5AD';
        $skuCompleto = "{$sku}-{$suf}";

        $insertVar->execute([
            ':pid' => $produtoId, ':sku' => $skuCompleto, ':suf' => $suf,
            ':cor' => $corNome, ':hex' => $corHex, ':est' => random_int(20, 500),
        ]);
        $findVar->execute([':sku' => $skuCompleto]);
        $variacaoId = (int) $findVar->fetchColumn();
        $nVar++;

        // 3 imagens por variação (regeneradas a cada seed)
        $delImgs->execute([':vid' => $variacaoId]);
        for ($k = 1; $k <= 3; $k++) {
            $insImg->execute([
                ':vid' => $variacaoId,
                ':url' => "https://picsum.photos/seed/novare{$skuCompleto}-{$k}/800/800",
                ':ord' => $k,
                ':pri' => $k === 1 ? 1 : 0,
            ]);
        }
    }
}

// Remove produtos órfãos ou sem imagem principal
$pdo->exec("DELETE FROM produtos WHERE imagem_principal IS NULL OR imagem_principal = '';");
$pdo->commit();

echo "✓ Seed concluído: {$nProd} produtos-pai e {$nVar} variações de demonstração (produtos sem imagem foram limpos).\n";
echo "  (sku_pai na faixa 9000x — remova antes de ir para produção)\n";
