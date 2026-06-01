<?php
/**
 * View de Catálogo estilizada com Tailwind CSS.
 * @var array $resultado  itens,total,pagina,por_pagina,total_paginas
 * @var array $filtros
 * @var array $categorias
 * @var array $materiais
 * @var array $cores
 * @var array $faixa
 * @var string $titulo_pagina
 */
$itens = $resultado['itens'];
$rotulosFiltro = [
    'q' => 'Busca', 'categoria' => 'Categoria', 'material' => 'Material',
    'cor' => 'Cor', 'sustentavel' => 'Ecológico', 'preco_min' => 'Preço Mín.',
    'preco_max' => 'Preço Máx.', 'quantidade_minima' => 'Qtd. Mín.',
];
$semOrdem = $filtros;
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
?>
<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-8" aria-label="Breadcrumb">
        <a href="<?= url('/') ?>" class="hover:text-primary transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">home</span> Início
        </a>
        <span class="material-symbols-outlined text-[10px]">chevron_right</span>
        <span class="text-secondary font-semibold"><?= e($titulo_pagina ?? 'Catálogo') ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Filtros -->
        <aside class="lg:col-span-1 bg-white border border-surface-container rounded-2xl p-6 shadow-sm h-fit">
            <form method="get" action="<?= url('/catalogo') ?>" class="space-y-6">
                <?php $hidden($filtros, ['categoria', 'material', 'cor', 'sustentavel', 'preco_min', 'preco_max', 'pagina']); ?>

                <!-- Categoria -->
                <div>
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Categoria</h3>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-2 no-scrollbar">
                        <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                            <input type="radio" name="categoria" value="" <?= empty($filtros['categoria']) ? 'checked' : '' ?> class="rounded-full border-outline text-primary focus:ring-primary/30 w-4 h-4">
                            <span>Todas as Categorias</span>
                        </label>
                        <?php foreach ($categorias as $c): ?>
                            <label class="flex items-center justify-between gap-2 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                                <span class="flex items-center gap-2.5">
                                    <input type="radio" name="categoria" value="<?= e($c['categoria']) ?>" <?= ($filtros['categoria'] ?? '') === $c['categoria'] ? 'checked' : '' ?> class="rounded-full border-outline text-primary focus:ring-primary/30 w-4 h-4">
                                    <span><?= e($c['categoria']) ?></span>
                                </span>
                                <span class="text-[10px] text-slate-400 font-bold bg-surface-container-low px-2 py-0.5 rounded-full">(<?= (int) $c['total'] ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Material -->
                <?php if ($materiais): ?>
                <div>
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Material</h3>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-2 no-scrollbar">
                        <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                            <input type="radio" name="material" value="" <?= empty($filtros['material']) ? 'checked' : '' ?> class="rounded-full border-outline text-primary focus:ring-primary/30 w-4 h-4">
                            <span>Todos os Materiais</span>
                        </label>
                        <?php foreach ($materiais as $mt): ?>
                            <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                                <input type="radio" name="material" value="<?= e($mt['material']) ?>" <?= ($filtros['material'] ?? '') === $mt['material'] ? 'checked' : '' ?> class="rounded-full border-outline text-primary focus:ring-primary/30 w-4 h-4">
                                <span><?= e($mt['material']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cor -->
                <?php if ($cores): ?>
                <div>
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Cor Principal</h3>
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

                <!-- Preço -->
                <div>
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Faixa de Preço</h3>
                    <div class="flex items-center gap-2 my-2">
                        <input type="number" name="preco_min" min="0" step="1" placeholder="Mín" value="<?= e($filtros['preco_min'] ?? '') ?>" class="w-full text-xs border border-surface-container rounded-lg px-3 py-2 bg-surface focus:ring-1 focus:ring-primary/40 focus:bg-white outline-none" aria-label="Preço mínimo">
                        <span class="text-slate-400 text-xs">até</span>
                        <input type="number" name="preco_max" min="0" step="1" placeholder="Máx" value="<?= e($filtros['preco_max'] ?? '') ?>" class="w-full text-xs border border-surface-container rounded-lg px-3 py-2 bg-surface focus:ring-1 focus:ring-primary/40 focus:bg-white outline-none" aria-label="Preço máximo">
                    </div>
                </div>

                <!-- Sustentabilidade -->
                <div>
                    <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Eco-Friendly</h3>
                    <label class="flex items-center gap-2.5 text-xs text-secondary hover:text-primary cursor-pointer py-1 transition-colors">
                        <input type="checkbox" name="sustentavel" value="1" <?= !empty($filtros['sustentavel']) ? 'checked' : '' ?> class="rounded border-outline text-primary focus:ring-primary/30 w-4 h-4">
                        <span class="flex items-center gap-1 font-semibold text-emerald-600">Apenas Ecológicos <span class="material-symbols-outlined text-[14px]">eco</span></span>
                    </label>
                </div>

                <!-- Ações -->
                <div class="pt-4 border-t border-surface-container/50 space-y-2">
                    <button type="submit" class="w-full primary-gradient text-white py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow-md hover:opacity-95 transition-opacity">Aplicar Filtros</button>
                    <?php if ($semOrdem): ?>
                        <a href="<?= url('/catalogo') ?>" class="w-full border border-secondary text-secondary block text-center py-3 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-surface transition-colors">Limpar Filtros</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

        <!-- Resultados Catálogo -->
        <div class="lg:col-span-3">
            <!-- Toolbar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white border border-surface-container rounded-2xl px-6 py-4 mb-6 shadow-sm">
                <span class="text-xs font-extrabold text-secondary uppercase tracking-wider">
                    <?= number_format($resultado['total'], 0, ',', '.') ?> produto<?= $resultado['total'] === 1 ? '' : 's' ?> encontrado<?= $resultado['total'] === 1 ? '' : 's' ?>
                </span>
                <form method="get" action="<?= strpos($titulo_pagina ?? '', 'Resultados') === 0 ? url('/busca') : url('/catalogo') ?>" class="flex items-center gap-2.5">
                    <?php $hidden($filtros, ['ordenar', 'pagina']); ?>
                    <label class="text-xs text-slate-400 font-semibold uppercase tracking-wider" for="ordenar">Ordenar por:</label>
                    <select name="ordenar" id="ordenar" data-autosubmit class="text-xs border border-surface-container rounded-lg px-3 py-1.5 bg-surface focus:ring-1 focus:ring-primary/30 outline-none cursor-pointer font-medium text-secondary focus:bg-white">
                        <?php
                        $ops = ['relevancia' => 'Relevância', 'recentes' => 'Novidades', 'preco_asc' => 'Menor preço', 'preco_desc' => 'Maior preço', 'nome' => 'Nome (A–Z)'];
                        foreach ($ops as $v => $rotulo):
                        ?>
                            <option value="<?= $v ?>" <?= ($filtros['ordenar'] ?? 'relevancia') === $v ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Active Chips -->
            <?php
            $chips = array_diff_key($semOrdem, ['pagina' => 1]);
            if ($chips): ?>
                <div class="flex flex-wrap gap-2 mb-6">
                    <?php foreach ($chips as $k => $v): ?>
                        <span class="bg-primary/10 text-primary text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-2 shadow-sm border border-primary/10">
                            <span class="text-slate-400"><?= e($rotulosFiltro[$k] ?? $k) ?>:</span> <?= e($k === 'sustentavel' ? 'Sim' : (string) $v) ?>
                            <a href="<?= url('/catalogo') . $qs($filtros, [$k => null]) ?>" class="hover:text-red-500 font-bold transition-colors ml-1" aria-label="Remover filtro">✕</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Produtos Grid -->
            <?php if (!$itens): ?>
                <div class="flex flex-col items-center justify-center text-center py-20 bg-white border border-surface-container rounded-2xl shadow-sm">
                    <span class="material-symbols-outlined text-primary text-5xl mb-4">search_off</span>
                    <h3 class="text-lg font-bold text-on-surface">Nenhum brinde encontrado</h3>
                    <p class="text-slate-400 text-xs mt-1 max-w-xs">Tente remover alguns filtros ou buscar por outros termos de pesquisa.</p>
                    <a href="<?= url('/catalogo') ?>" class="mt-6 primary-gradient text-white px-6 py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow-md hover:opacity-95 transition-opacity">Ver Catálogo Completo</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                    <?php foreach ($itens as $p): ?>
                        <?php partial('card', ['p' => $p]); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação -->
                <?php
                $pag = $resultado['pagina'];
                $tot = $resultado['total_paginas'];
                if ($tot > 1):
                    $base = url('/catalogo');
                ?>
                    <nav class="flex justify-center items-center gap-1.5 mt-12" aria-label="Paginação">
                        <a class="px-4 py-2.5 text-xs font-bold border border-surface-container rounded-lg <?= $pag <= 1 ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'bg-white hover:bg-surface text-secondary transition-colors shadow-sm' ?>" href="<?= $base . $qs($filtros, ['pagina' => max(1, $pag - 1)]) ?>">Anterior</a>
                        
                        <div class="flex items-center gap-1">
                            <?php
                            $ini = max(1, $pag - 2);
                            $fim = min($tot, $pag + 2);
                            if ($ini > 1) {
                                echo '<a class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg bg-white border border-surface-container hover:bg-surface text-secondary transition-colors" href="' . $base . $qs($filtros, ['pagina' => 1]) . '">1</a>';
                                if ($ini > 2) echo '<span class="w-9 h-9 flex items-center justify-center text-xs font-bold text-slate-400">…</span>';
                            }
                            for ($i = $ini; $i <= $fim; $i++):
                            ?>
                                <?php if ($i === $pag): ?>
                                    <span class="w-9 h-9 flex items-center justify-center text-xs font-black rounded-lg bg-primary text-white shadow-md"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg bg-white border border-surface-container hover:bg-surface text-secondary transition-colors" href="<?= $base . $qs($filtros, ['pagina' => $i]) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor;
                            if ($fim < $tot) {
                                if ($fim < $tot - 1) echo '<span class="w-9 h-9 flex items-center justify-center text-xs font-bold text-slate-400">…</span>';
                                echo '<a class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg bg-white border border-surface-container hover:bg-surface text-secondary transition-colors" href="' . $base . $qs($filtros, ['pagina' => $tot]) . '">' . $tot . '</a>';
                            }
                            ?>
                        </div>

                        <a class="px-4 py-2.5 text-xs font-bold border border-surface-container rounded-lg <?= $pag >= $tot ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'bg-white hover:bg-surface text-secondary transition-colors shadow-sm' ?>" href="<?= $base . $qs($filtros, ['pagina' => min($tot, $pag + 1)]) ?>">Próxima</a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
