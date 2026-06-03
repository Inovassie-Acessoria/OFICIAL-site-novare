<?php
/**
 * Layout exclusivo do painel admin — NÃO usa o layout/chrome do site público.
 * @var string $conteudo
 * @var string $csrf
 */
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Painel Novare Brindes</title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background:#0f172a; color:#e2e8f0; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 500; vertical-align: middle; }
        .drag-over { outline: 2px dashed #38bdf8; outline-offset: 2px; }
        .sortable-item { cursor: grab; }
        .sortable-item:active { cursor: grabbing; }
        .dragging { opacity: .4; }
    </style>
</head>
<body class="min-h-full">
    <?= $conteudo ?>
</body>
</html>
