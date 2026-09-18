<?php
/**
 * Listagem do catálogo: hub (/brindes), categoria, faceta e busca.
 *
 * Três pontos críticos:
 *   1. categorias e facetas promovidas são LINKS reais em path (rastreáveis);
 *      material/cor/eco continuam parâmetros (noindex,follow, canonical na categoria)
 *   2. paginação com links <a> reais e canonical próprio em cada página
 *   3. intro própria + FAQ só na página 1, para não duplicar
 *
 * @var array  $resultado  itens,total,pagina,por_pagina,total_paginas
 * @var array  $filtros
 * @var array  $categorias
 * @var array  $materiais
 * @var array  $cores
 * @var string $titulo_pagina (H1)
 * @var string $base_path
 * @var array  $paginacao
 * @var array  $breadcrumbs
 * @var array|null $categoria_atual
 * @var array|null $faceta_atual
 * @var string $intro_seo
 * @var array  $faq
 * @var array  $facetas_promovidas
 * @var array  $ocasioes
 * @var bool   $eh_busca
 */
$itens    = $resultado['itens'];
$ehBusca  = !empty($eh_busca);
$basePath = $base_path ?? Seo::urlHub();
$pagAtual = (int) $resultado['pagina'];
$rotulosFiltro = [
    'q' => 'Busca', 'categoria' => 'Categoria', 'material' => 'Material',
    'cor' => 'Cor', 'sustentavel' => 'Ecológico', 'preco_min' => 'Preço Mín.',
    'preco_max' => 'Preço Máx.', 'quantidade_minima' => 'Qtd. Mín.',
];
// filtros que viram query string (categoria é path, não param)
$filtrosQs = $filtros;
unset($filtrosQs['categoria']);
if (!empty($faceta_atual)) {
    unset($filtrosQs['material']);
}
$semOrdem = $filtrosQs;
unset($semOrdem['ordenar']);

/** Monta querystring preservando filtros, com alterações. */
$qs = static function (array $base, array $alt = []) {
    $m = array_merge($base, $alt);
    $m = array_filter($m, static fn ($v) => $v !== null && $v !== '');
    return $m ? '?' . http_build_query($m) : '';
};
/** Inputs hidden para preservar filtros num form. */
$hidden = static function (array $f, array $exceto = []) {
    foreach ($f as $k => $v) {
        if (in_array($k, $exceto, true) || $v === '' || $v === null) continue;
        echo '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
};
$catNome = $categoria_atual['nome'] ?? null;
$rotuloCat = trim((string) ($categoria_atual['rotulo_seo'] ?? '')) ?: ($catNome ? $catNome . ' Personalizados' : 'brindes personalizados');
?>
<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6 flex-wrap" aria-label="Você está em">
        <?php foreach ($breadcrumbs ?? [] as $i => $bc): ?>
            <?php if ($i > 0): ?><span class="material-symbols-outlined text-[10px]">chevron_right</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
                <a href="<?= e($bc['url']) ?>" class="hover:text-primary transition-colors flex items-center gap-1"><?php if ($i === 0): ?><span class="material-symbols-outlined text-sm">home</span><?php endif; ?><?= e($bc['name']) ?></a>
            <?php else: ?>
                <span class="text-secondary font-semibold"><?= e($bc['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <header class="mb-8">
        <h1 class="text-2xl md:text-3xl font-black text-on-surface tracking-tighter leading-tight"><?= e($titulo_pagina) ?></h1>
        <?php if ($pagAtual === 1 && !$ehBusca): ?>
            <p class="text-sm text-secondary mt-2">
                <strong><?= number_format((int) $resultado['total'], 0, ',', '.') ?></strong> modelos disponíveis
                <?php if ($catNome): ?> em <?= e(mb_strtolower($catNome, 'UTF-8')) ?><?php endif; ?>,
                com quantidade mínima a partir de 20 unidades e personalização com a logo da sua empresa.
            </p>
            <?php if (!empty($intro_seo)): ?>
                <!-- INTRO PRÓPRIA: dado real e texto próprio é o que diferencia do catálogo herdado -->
                <div class="text-sm text-secondary leading-relaxed mt-4 max-w-3xl space-y-3"><?= nl2br(e(strip_tags((string) $intro_seo, '<b><strong><em><i><br><p><a><ul><ol><li>'))) ?></div>
            <?php endif; ?>
        <?php elseif ($pagAtual > 1): ?>
            <p class="text-xs text-slate-400 mt-2">Página <?= $pagAtual ?> de <?= (int) $resultado['total_paginas'] ?></p>
        <?php endif; ?>

        <?php if (!empty($facetas_promovidas)): ?>
            <!-- FACETAS PROMOVIDAS: link real em path, entram no rastreio -->
            <nav class="flex flex-wrap gap-2 mt-5" aria-label="Filtrar por material">
                <?php foreach ($facetas_promovidas as $f): ?>
                    <a href="<?= e(Seo::urlFaceta($categoria_atual['slug'], $f['atributo'], $f['valor_slug'])) ?>" class="bg-white border border-surface-container hover:border-primary hover:text-primary px-3.5 py-1.5 rounded-xl text-xs font-semibold text-secondary transition-colors shadow-sm">
                        <?= e($catNome) ?> de <?= e($f['valor_label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Filtros -->
        <aside class="lg:col-span-1 bg-white border border-surface-container rounded-2xl p-6 shadow-sm h-fit">
            <!-- Categorias: links reais (path indexável) -->
            <div class="mb-6">
                <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Categorias</h2>
                <nav class="space-y-1 max-h-64 overflow-y-auto pr-2 no-scrollbar" aria-label="Categorias">
                    <a href="<?= e(Seo::urlHub()) ?>" class="flex items-center justify-between gap-2 text-xs py-1 transition-colors <?= !$catNome && !$ehBusca ? 'text-primary font-bold' : 'text-secondary hover:text-primary' ?>">
                        <span>Todos os brindes</span>
                    </a>
                    <?php foreach ($categorias as $c): ?>
                        <a href="<?= e(Seo::urlCategoria($c['categoria'])) ?>" class="flex items-center justify-between gap-2 text-xs py-1 transition-colors <?= $catNome === $c['categoria'] ? 'text-primary font-bold' : 'text-secondary hover:text-primary' ?>">
                            <span><?= e($c['categoria']) ?></span>
                            <span class="text-[10px] text-slate-400 font-bold bg-surface-container-low px-2 py-0.5 rounded-full"><?= (int) $c['total'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <form method="get" action="<?= e($basePath) ?>" class="space-y-6">
                <?php $hidden($filtrosQs, ['material', 'cor', 'sustentavel', 'preco_min', 'preco_max', 'pagina']); ?>

                <!-- Material (filtro livre: param + noindex) -->
                <?php if ($materiais && empty($faceta_atual)): ?>
                <div>
                    <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Material</h2>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-2 no-scrollbar">
                        <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                            <input type="radio" name="material" value="" data-autosubmit <?= empty($filtros['material']) ? 'checked' : '' ?> class="rounded-full border-outline text-primary focus:ring-primary/30 w-4 h-4">
                            <span>Todos os Materiais</span>
                        </label>
                        <?php foreach ($materiais as $mt): ?>
                            <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                                <input type="radio" name="material" value="<?= e($mt['material']) ?>" data-autosubmit <?= ($filtros['material'] ?? '') === $mt['material'] ? 'checked' : '' ?> class="rounded-full border-outline text-primary focus:ring-primary/30 w-4 h-4">
                                <span><?= e($mt['material']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cor -->
                <?php if ($cores): ?>
                <div>
                    <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Cor Principal</h2>
                    <div class="flex flex-wrap gap-2 my-2">
                        <?php foreach (array_slice($cores, 0, 18) as $cor): ?>
                            <label title="<?= e($cor['cor']) ?>" class="cursor-pointer relative">
                                <input type="radio" name="cor" value="<?= e($cor['cor']) ?>" class="sr-only peer" data-autosubmit <?= ($filtros['cor'] ?? '') === $cor['cor'] ? 'checked' : '' ?>>
                                <span class="w-6 h-6 rounded-full border border-surface-container flex items-center justify-center transition-all peer-checked:ring-2 peer-checked:ring-primary peer-checked:scale-110 shadow-sm" style="background:<?= e($cor['hex'] ?: '#ccc') ?>"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sustentabilidade -->
                <div>
                    <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Eco-Friendly</h2>
                    <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                        <input type="checkbox" name="sustentavel" value="1" data-autosubmit <?= !empty($filtros['sustentavel']) ? 'checked' : '' ?> class="rounded border-outline text-primary focus:ring-primary/30 w-4 h-4">
                        <span class="flex items-center gap-1 font-semibold text-emerald-600">Apenas Ecológicos <span class="material-symbols-outlined text-[14px]">eco</span></span>
                    </label>
                </div>

                <div class="pt-4 border-t border-surface-container/50 space-y-2">
                    <button type="submit" class="w-full primary-gradient text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow-md hover:opacity-95 transition-opacity">Aplicar Filtros</button>
                    <?php if ($semOrdem): ?>
                        <a href="<?= e($basePath) ?>" class="w-full border border-secondary text-secondary block text-center py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-surface transition-colors">Limpar Filtros</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

        <!-- Resultados -->
        <div class="lg:col-span-3">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white border border-surface-container rounded-2xl px-6 py-4 mb-6 shadow-sm">
                <span class="text-xs font-extrabold text-secondary uppercase tracking-wider">
                    <?= number_format((int) $resultado['total'], 0, ',', '.') ?> produto<?= $resultado['total'] === 1 ? '' : 's' ?> encontrado<?= $resultado['total'] === 1 ? '' : 's' ?>
                </span>
                <form method="get" action="<?= e($basePath) ?>" class="flex items-center gap-2.5">
                    <?php $hidden($filtrosQs, ['ordenar', 'pagina']); ?>
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider" for="ordenar">Ordenar por:</label>
                    <select name="ordenar" id="ordenar" data-autosubmit class="text-xs border border-surface-container rounded-lg px-3 py-1.5 bg-surface focus:ring-1 focus:ring-primary/30 outline-none cursor-pointer font-medium text-secondary focus:bg-white">
                        <?php foreach (['relevancia' => 'Relevância', 'recentes' => 'Novidades', 'nome' => 'Nome (A–Z)'] as $v => $rotulo): ?>
                            <option value="<?= $v ?>" <?= ($filtros['ordenar'] ?? 'relevancia') === $v ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php $chips = array_diff_key($semOrdem, ['pagina' => 1]); if ($chips): ?>
                <div class="flex flex-wrap gap-2 mb-6">
                    <?php foreach ($chips as $k => $v): ?>
                        <span class="bg-primary/10 text-primary text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-2 shadow-sm border border-primary/10">
                            <span class="text-slate-400"><?= e($rotulosFiltro[$k] ?? $k) ?>:</span> <?= e($k === 'sustentavel' ? 'Sim' : (string) $v) ?>
                            <a href="<?= e($basePath . $qs($filtrosQs, [$k => null, 'pagina' => null])) ?>" class="hover:text-red-500 font-bold transition-colors ml-1" aria-label="Remover filtro" rel="nofollow">✕</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$itens): ?>
                <div class="flex flex-col items-center justify-center text-center py-20 bg-white border border-surface-container rounded-2xl shadow-sm">
                    <span class="material-symbols-outlined text-primary text-5xl mb-4">search_off</span>
                    <h2 class="text-lg font-bold text-on-surface">Nenhum brinde encontrado</h2>
                    <p class="text-slate-400 text-xs mt-1 max-w-xs">Tente remover alguns filtros ou buscar por outros termos de pesquisa.</p>
                    <a href="<?= e(Seo::urlHub()) ?>" class="mt-6 primary-gradient text-white px-6 py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow-md hover:opacity-95 transition-opacity">Ver Catálogo Completo</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                    <?php foreach ($itens as $p): ?>
                        <?php partial('card', ['p' => $p]); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação: links <a> reais; cada página é canônica de si mesma -->
                <?php $pag = $pagAtual; $tot = (int) $resultado['total_paginas']; if ($tot > 1): ?>
                    <nav class="flex justify-center items-center gap-1.5 mt-12" aria-label="Paginação">
                        <?php if ($pag > 1): ?>
                            <a rel="prev" class="px-4 py-2.5 text-xs font-bold border border-surface-container rounded-lg bg-white hover:bg-surface text-secondary transition-colors shadow-sm" href="<?= e($basePath . $qs($filtrosQs, ['pagina' => $pag - 1 > 1 ? $pag - 1 : null])) ?>">Anterior</a>
                        <?php else: ?>
                            <span class="px-4 py-2.5 text-xs font-bold border border-surface-container rounded-lg opacity-40">Anterior</span>
                        <?php endif; ?>
                        <div class="flex items-center gap-1">
                            <?php
                            $ini = max(1, $pag - 2);
                            $fim = min($tot, $pag + 2);
                            $link = static fn (int $n) => $basePath . $qs($filtrosQs, ['pagina' => $n > 1 ? $n : null]);
                            if ($ini > 1) {
                                echo '<a class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg bg-white border border-surface-container hover:bg-surface text-secondary transition-colors" href="' . e($link(1)) . '">1</a>';
                                if ($ini > 2) echo '<span class="w-9 h-9 flex items-center justify-center text-xs font-bold text-slate-400">…</span>';
                            }
                            for ($i = $ini; $i <= $fim; $i++):
                            ?>
                                <?php if ($i === $pag): ?>
                                    <span class="w-9 h-9 flex items-center justify-center text-xs font-black rounded-lg bg-primary text-white shadow-md" aria-current="page"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg bg-white border border-surface-container hover:bg-surface text-secondary transition-colors" href="<?= e($link($i)) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor;
                            if ($fim < $tot) {
                                if ($fim < $tot - 1) echo '<span class="w-9 h-9 flex items-center justify-center text-xs font-bold text-slate-400">…</span>';
                                echo '<a class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg bg-white border border-surface-container hover:bg-surface text-secondary transition-colors" href="' . e($link($tot)) . '">' . $tot . '</a>';
                            }
                            ?>
                        </div>
                        <?php if ($pag < $tot): ?>
                            <a rel="next" class="px-4 py-2.5 text-xs font-bold border border-surface-container rounded-lg bg-white hover:bg-surface text-secondary transition-colors shadow-sm" href="<?= e($basePath . $qs($filtrosQs, ['pagina' => $pag + 1])) ?>">Próxima</a>
                        <?php else: ?>
                            <span class="px-4 py-2.5 text-xs font-bold border border-surface-container rounded-lg opacity-40">Próxima</span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pagAtual === 1 && !$ehBusca): ?>
        <?php partial('faq', ['faq' => $faq ?? [], 'titulo' => 'Perguntas frequentes sobre ' . mb_strtolower($rotuloCat, 'UTF-8')]); ?>

        <?php if (!empty($ocasioes)): ?>
            <!-- Conecta o cluster comercial ao de intenção -->
            <section class="mt-12 border-t border-surface-container/60 pt-10">
                <h2 class="text-lg font-black text-on-surface tracking-tight mb-5">Onde usar <?= e(mb_strtolower($rotuloCat, 'UTF-8')) ?></h2>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($ocasioes as $o): ?>
                        <a href="<?= e(Seo::urlOcasiao($o['slug'])) ?>" class="bg-white border border-surface-container hover:border-primary hover:text-primary px-3.5 py-1.5 rounded-xl text-xs font-semibold text-secondary transition-colors shadow-sm"><?= e($o['titulo']) ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
