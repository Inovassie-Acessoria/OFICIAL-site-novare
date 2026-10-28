<?php
/**
 * Relatório de estoque para o time comercial (página de impressão: Salvar em PDF).
 *
 * @var string     $tipo      'atualizacao' ou 'fora'
 * @var array|null $run       linha de catalogo_atualizacoes
 * @var array|null $grupos    saiu, voltou, cor_saiu, cor_voltou
 * @var array      $conferir  fora da API, leitura incerta, não confirmados
 * @var array      $fora      produtos fora do site hoje (tipo = fora)
 */
$n  = static fn ($v) => $v === null || $v === '' ? '' : number_format((int) $v, 0, ',', '.');
$dt = static fn (?string $d) => $d ? date('d/m/Y H:i', strtotime($d)) : '';
$nome = static fn (?string $s) => Seo::nomeLegivel((string) $s);

if ($tipo === 'fora') {
    $tituloDoc = 'Produtos fora do site ' . date('d-m-Y');
    $titulo = 'Produtos fora do site hoje';
    $sub = 'Situação em ' . date('d/m/Y H:i') . ' · ' . count($fora) . ' produtos sem estoque suficiente para o pedido mínimo';
} else {
    $aplicada = $run['status'] === 'aplicada';
    $quando = $aplicada ? $run['aplicado_em'] : $run['criado_em'];
    $tituloDoc = 'Estoque Novare ' . date('d-m-Y H\hi', strtotime((string) $quando));
    $titulo = 'Atualização de estoque do catálogo';
    $sub = ($aplicada ? 'Aplicada em ' : 'PRÉVIA, ainda não aplicada no site · conferida em ') . $dt($quando)
        . ($run['usuario'] ? ' · por ' . $run['usuario'] : '') . ' · atualização #' . (int) $run['id'];
}
$tabelaProdutos = static function (array $linhas, string $vazio) use ($n, $nome): void {
    if (!$linhas) {
        echo '<p class="vazio">' . e($vazio) . '</p>';
        return;
    }
    echo '<table><thead><tr><th class="cod">Código</th><th>Produto</th><th>Categoria</th><th class="num">Pedido mín.</th><th>Estoque por cor na XBZ</th></tr></thead><tbody>';
    foreach ($linhas as $l) {
        echo '<tr><td class="cod">' . e((string) $l['sku_pai']) . '</td><td>' . e($nome($l['nome'])) . '</td><td>' . e((string) $l['categoria'])
            . '</td><td class="num">' . $n($l['quantidade_minima']) . '</td><td class="det">' . e((string) $l['detalhe']) . '</td></tr>';
    }
    echo '</tbody></table>';
};
$tabelaCores = static function (array $linhas, string $vazio) use ($n, $nome): void {
    if (!$linhas) {
        echo '<p class="vazio">' . e($vazio) . '</p>';
        return;
    }
    echo '<table><thead><tr><th class="cod">Código da cor</th><th>Produto</th><th>Cor</th><th class="num">Estoque</th><th class="num">Pedido mín.</th></tr></thead><tbody>';
    foreach ($linhas as $l) {
        echo '<tr><td class="cod">' . e((string) $l['sku_completo']) . '</td><td>' . e($nome($l['nome'])) . '</td><td>' . e((string) $l['cor'])
            . '</td><td class="num">' . $n($l['estoque_atual']) . '</td><td class="num">' . $n($l['quantidade_minima']) . '</td></tr>';
    }
    echo '</tbody></table>';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($tituloDoc) ?></title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Inter, "Segoe UI", Arial, sans-serif; color: #1e293b; background: #f1f5f9; margin: 0; font-size: 12px; line-height: 1.45; }
        .folha { max-width: 210mm; margin: 24px auto; background: #fff; padding: 28px 32px; box-shadow: 0 2px 12px rgba(15,23,42,.08); }
        .barra { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
        .barra button, .barra a { font: inherit; font-size: 12px; font-weight: 700; border: 0; border-radius: 8px; padding: 8px 14px; cursor: pointer; text-decoration: none; }
        .barra button { background: #006590; color: #fff; }
        .barra a { background: #e2e8f0; color: #1e293b; }
        header { border-bottom: 3px solid #006590; padding-bottom: 12px; margin-bottom: 18px; }
        .marca { font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #006590; }
        h1 { font-size: 22px; margin: 4px 0 2px; letter-spacing: -.02em; }
        .sub { color: #64748b; font-size: 11px; }
        .previa { display: inline-block; background: #fef3c7; color: #92400e; font-weight: 800; padding: 2px 8px; border-radius: 6px; font-size: 11px; margin-top: 6px; }
        .resumo { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 0 0 16px; }
        .resumo div { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; }
        .resumo b { display: block; font-size: 22px; line-height: 1.1; }
        .resumo span { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 700; }
        .resumo .r-sai b { color: #b91c1c; } .resumo .r-volta b { color: #047857; }
        .guia { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px; font-size: 11px; }
        .guia p { margin: 2px 0; }
        h2 { font-size: 14px; margin: 22px 0 4px; padding-left: 8px; border-left: 4px solid #006590; break-after: avoid; }
        h2.sai { border-color: #b91c1c; } h2.volta { border-color: #047857; } h2.confere { border-color: #d97706; }
        .explica { color: #64748b; font-size: 11px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        thead { display: table-header-group; }
        th { text-align: left; background: #f1f5f9; color: #334155; font-weight: 700; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr { break-inside: avoid; }
        .cod { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .det { color: #475569; font-size: 10px; }
        .vazio { color: #94a3b8; font-style: italic; margin: 4px 0 0; }
        footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 10px; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .folha { margin: 0; padding: 0; box-shadow: none; max-width: none; }
        }
    </style>
</head>
<body>
<div class="barra">
    <a href="/settings-admin/estoque">Voltar ao painel</a>
    <button type="button" onclick="window.print()">Salvar em PDF</button>
</div>
<div class="folha">
    <header>
        <div class="marca">Novare Brindes · uso interno do comercial</div>
        <h1><?= e($titulo) ?></h1>
        <div class="sub"><?= e($sub) ?></div>
        <?php if ($tipo !== 'fora' && !$aplicada): ?><div class="previa">Prévia: o site ainda não mudou. Use só para conferência.</div><?php endif; ?>
    </header>

<?php if ($tipo === 'fora'): ?>
    <div class="guia">
        <p><strong>Não oferecer estes produtos agora.</strong> O fornecedor não tem estoque para o pedido mínimo em nenhuma cor. Eles estão fora das listagens e da busca do site, mas a página continua abrindo com aviso de indisponível.</p>
        <p>Se o cliente pedir um deles, ofereça um similar da mesma categoria. Quando o estoque voltar, o produto reaparece no site na próxima atualização.</p>
    </div>
    <?php if (!$fora): ?>
        <p class="vazio">Nenhum produto fora do site hoje.</p>
    <?php else: ?>
        <table>
            <thead><tr><th class="cod">Código</th><th>Produto</th><th>Categoria</th><th class="num">Pedido mín.</th><th class="num">Estoque total</th><th>Fora desde</th></tr></thead>
            <tbody>
            <?php foreach ($fora as $p): ?>
                <tr>
                    <td class="cod"><?= e((string) $p['sku_pai']) ?></td>
                    <td><?= e($nome($p['nome'])) ?></td>
                    <td><?= e((string) $p['categoria']) ?></td>
                    <td class="num"><?= $n(Estoque::minimo($p)) ?></td>
                    <td class="num"><?= $n($p['estoque_total']) ?></td>
                    <td class="cod"><?= e($p['esgotado_desde'] ? date('d/m/Y', strtotime((string) $p['esgotado_desde'])) : '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php else: ?>
    <div class="resumo">
        <div class="r-sai"><b><?= $n(count($grupos['saiu'])) ?></b><span>produtos saíram</span></div>
        <div class="r-volta"><b><?= $n(count($grupos['voltou'])) ?></b><span>produtos voltaram</span></div>
        <div class="r-sai"><b><?= $n(count($grupos['cor_saiu'])) ?></b><span>cores saíram</span></div>
        <div class="r-volta"><b><?= $n(count($grupos['cor_voltou'])) ?></b><span>cores voltaram</span></div>
    </div>

    <div class="guia">
        <p><strong>Como usar:</strong> o site considera esgotado o que tem estoque abaixo do pedido mínimo do produto no fornecedor (XBZ).</p>
        <p><strong>Saíram:</strong> não oferecer; ofereça um similar. <strong>Voltaram:</strong> pode oferecer de novo. <strong>Cores:</strong> o produto continua disponível, só a cor indicada não atende o mínimo.</p>
        <p>O estoque é o da hora da leitura e muda ao longo do dia. Confirme sempre no orçamento.</p>
    </div>

    <h2 class="sai">Produtos que saíram do site</h2>
    <p class="explica">Nenhuma cor com estoque para o pedido mínimo. Não oferecer.</p>
    <?php $tabelaProdutos($grupos['saiu'], 'Nenhum produto saiu nesta atualização.'); ?>

    <h2 class="volta">Produtos que voltaram ao site</h2>
    <p class="explica">Voltaram a ter pelo menos uma cor com estoque para o pedido mínimo. Pode oferecer.</p>
    <?php $tabelaProdutos($grupos['voltou'], 'Nenhum produto voltou nesta atualização.'); ?>

    <h2 class="sai">Cores que saíram (o produto continua disponível)</h2>
    <p class="explica">Só estas cores estão abaixo do mínimo. Ofereça outra cor do mesmo produto.</p>
    <?php $tabelaCores($grupos['cor_saiu'], 'Nenhuma cor saiu nesta atualização.'); ?>

    <h2 class="volta">Cores que voltaram</h2>
    <?php $tabelaCores($grupos['cor_voltou'], 'Nenhuma cor voltou nesta atualização.'); ?>

    <?php if ($conferir): ?>
        <h2 class="confere">Para conferir manualmente (continuam no site)</h2>
        <p class="explica">Produtos que não vieram na lista da XBZ, com leitura incerta ou que a segunda leitura não confirmou. Nada foi tirado do ar. Confira com o fornecedor antes de oferecer.</p>
        <table>
            <thead><tr><th class="cod">Código</th><th>Produto</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($conferir as $l): ?>
                <tr><td class="cod"><?= e((string) ($l['sku_completo'] ?: $l['sku_pai'])) ?></td><td><?= e($nome($l['nome'])) ?><?= $l['cor'] ? ' · ' . e((string) $l['cor']) : '' ?></td><td class="det"><?= e((string) $l['detalhe']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>

    <footer>Gerado em <?= e(date('d/m/Y H:i')) ?> pelo painel da Novare Brindes. Estoque informado pela XBZ no momento da leitura.</footer>
</div>
</body>
</html>
