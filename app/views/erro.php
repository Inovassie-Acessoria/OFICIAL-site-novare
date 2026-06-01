<?php
/**
 * View de Erro (404, 500) estilizada com Tailwind CSS.
 * @var int $codigo
 * @var string $titulo
 * @var string $msg
 */
?>
<section class="max-w-md mx-auto px-6 py-24 flex flex-col justify-center items-center text-center">
    <div class="text-7xl font-black text-primary tracking-tighter mb-4 relative">
        <span class="absolute -top-6 -right-6 opacity-10 text-primary select-none pointer-events-none">
            <span class="material-symbols-outlined text-[64px]">warning</span>
        </span>
        <?= (int) $codigo ?>
    </div>
    <h1 class="text-2xl font-black text-on-surface tracking-tight mb-2"><?= e($titulo) ?></h1>
    <p class="text-secondary text-xs leading-relaxed mb-8 max-w-xs"><?= e($msg) ?></p>
    <div class="flex flex-wrap gap-4 justify-center">
        <a href="<?= url('/') ?>" class="primary-gradient text-white px-6 py-3 rounded-lg text-xs font-bold uppercase tracking-wider shadow-md hover:opacity-95 transition-opacity inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm">home</span> Início
        </a>
        <a href="<?= url('/catalogo') ?>" class="border border-secondary text-secondary hover:bg-surface px-6 py-3 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors shadow-sm inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm">grid_view</span> Catálogo
        </a>
    </div>
</section>
