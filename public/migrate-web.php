<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/Database.php';

Env::load();

$action = $_GET['action'] ?? null;
$message = '';
$error = '';

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

if ($action === 'migrate') {
    try {
        $pdo = Database::connection();
        $sqlFile = __DIR__ . '/../scripts/schema.sql';
        if (!is_file($sqlFile)) {
            throw new Exception("Arquivo schema.sql não encontrado.");
        }
        
        // Desativa checagem de FK para limpar com segurança
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Busca e dropa TODAS as tabelas existentes de forma dinâmica
        $stmtTables = $pdo->query("SHOW TABLES");
        $tables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`;");
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        
        $statements = split_sql(file_get_contents($sqlFile) ?: '');
        $count = 0;
        foreach ($statements as $sql) {
            $pdo->exec($sql);
            $count++;
        }
        $message = "Banco de dados configurado com sucesso! Todas as tabelas antigas foram limpas e recriadas. {$count} comandos SQL executados.";
    } catch (Throwable $e) {
        $error = "Erro ao executar migração: " . $e->getMessage();
    }
} elseif ($action === 'seed') {
    try {
        $pdo = Database::connection();
        
        // Verifica se a tabela produtos existe
        $stmtCheck = $pdo->query("SHOW TABLES LIKE 'produtos'");
        if (!$stmtCheck->fetch()) {
            throw new Exception("Tabela 'produtos' não existe. Por favor, execute a migração primeiro.");
        }

        $demo = [
            ['90001', 'Garrafa Térmica Inox 500ml', 'Garrafa térmica em aço inox com parede dupla, mantém a temperatura por até 12h. Ideal para kits de onboarding.', 'Garrafas e Copos', 'Aço Inox', 89.90, 50, 0, ['PRE', 'AZ', 'VM', 'PRT']],
            ['90002', 'Caderno Capa Dura A5', 'Caderno corporativo capa dura, miolo pautado 80 folhas, com elástico e marcador.', 'Escritório', 'Papel / Couro Sintético', 34.50, 100, 1, ['PRE', 'AZM', 'VNH']],
            ['90003', 'Caneta Metal Premium', 'Caneta esferográfica em metal escovado, escrita azul, embalagem individual para presente.', 'Canetas', 'Metal', 19.90, 200, 0, ['PRT', 'DOU', 'PRE']],
            ['90004', 'Ecobag Algodão Cru', 'Sacola ecológica 100% algodão cru, alça reforçada, área ampla para personalização.', 'Sacolas', 'Algodão', 12.00, 300, 1, ['NAT', 'PRE', 'AZ']],
            ['90005', 'Mochila Executiva Notebook', 'Mochila para notebook 15\", compartimento acolchoado, saída USB e tecido resistente à água.', 'Mochilas e Bolsas', 'Poliéster', 159.00, 30, 0, ['PRE', 'CZ', 'AZM']],
            ['90006', 'Squeeze Esportivo 750ml', 'Squeeze plástico livre de BPA, bico retrátil, perfeito para eventos esportivos.', 'Garrafas e Copos', 'Plástico (PP)', 15.90, 250, 1, ['BRC', 'AZ', 'VD', 'LJ', 'RS']],
        ];

        $insertProduto = $pdo->prepare(
            'INSERT INTO produtos (sku_pai, nome, descricao, categoria, material, preco_base, quantidade_minima, sustentavel, imagem_principal, ativo)
             VALUES (:sku, :nome, :desc, :cat, :mat, :preco, :qmin, :sus, :img, 1)
             ON DUPLICATE KEY UPDATE nome=VALUES(nome), descricao=VALUES(descricao), categoria=VALUES(categoria),
                material=VALUES(material), preco_base=VALUES(preco_base), quantidade_minima=VALUES(quantidade_minima),
                sustentavel=VALUES(sustentavel), imagem_principal=VALUES(imagem_principal), ativo=1'
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
            $imgPrincipal = "https://picsum.photos/seed/novare{$sku}/600/600";

            $insertProduto->execute([
                ':sku' => $sku, ':nome' => $nome, ':desc' => $desc, ':cat' => $cat,
                ':mat' => $mat, ':preco' => $preco, ':qmin' => $qmin, ':sus' => $sus, ':img' => $imgPrincipal,
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

        $pdo->commit();
        $message = "Dados semeados com sucesso! Inseridos {$nProd} produtos-pai e {$nVar} variações de demonstração.";
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Erro ao semear banco: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurador de Banco - Novare Brindes</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            border: 1px solid #e5e7eb;
        }
        .logo {
            text-align: center;
            font-weight: 700;
            font-size: 1.5rem;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 1rem;
            box-sizing: border-box;
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
        .btn-secondary {
            background-color: #10b981;
        }
        .btn-secondary:hover {
            background-color: #059669;
        }
        .btn-outline {
            background-color: transparent;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .btn-outline:hover {
            background-color: #f9fafb;
            color: #111827;
        }
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            font-weight: 500;
        }
        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Novare Brindes</div>
        <div class="subtitle">Configurador Web de Banco de Dados (v2.0 - Auto-Limpeza)</div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <strong>Sucesso!</strong> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>Erro!</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            ⚠️ <strong>Aviso de Segurança:</strong> delete este arquivo (<code>public/migrate-web.php</code>) do servidor Hostinger após utilizá-lo para evitar acessos indesejados.
        </div>

        <a href="?action=migrate" class="btn">1. Executar Migração (Criar Tabelas)</a>
        <a href="?action=seed" class="btn btn-secondary">2. Popular Banco com Dados de Teste</a>
        <a href="/" class="btn btn-outline">Ir para o Site Inicial</a>

        <div class="footer">
            Novare Brindes &copy; 2026. Todos os direitos reservados.
        </div>
    </div>
</body>
</html>
