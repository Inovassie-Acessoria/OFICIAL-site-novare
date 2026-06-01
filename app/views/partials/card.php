<?php
/**
 * Card de produto reutilizável estilizado com Tailwind CSS.
 * @var array $p produto (sku_pai, nome, categoria, preco_base, sustentavel, imagem_principal)
 */
$img = $p['imagem_principal'] ?? '';
?>
<a class="group flex flex-col bg-white border border-surface-container rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:border-primary/10 transition-all duration-300 h-full" href="<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>">
    <div class="relative w-full aspect-square bg-slate-50 flex items-center justify-center p-6 overflow-hidden border-b border-surface-container/50 flex-shrink-0">
        <?php if (!empty($p['sustentavel'])): ?>
            <span class="absolute top-3 left-3 bg-emerald-500 text-white text-[8px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full shadow-sm z-10 flex items-center gap-1">
                <span class="material-symbols-outlined text-[10px] font-bold">eco</span> Ecológico
            </span>
        <?php endif; ?>
        <?php if ($img !== ''): ?>
            <img class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-500" src="<?= e($img) ?>" alt="<?= e($p['nome']) ?>" loading="lazy" width="240" height="240">
        <?php else: ?>
            <div class="text-xs text-slate-400 flex flex-col items-center gap-2">
                <span class="material-symbols-outlined text-2xl">image_not_supported</span>
                Sem imagem
            </div>
        <?php endif; ?>
    </div>
    <div class="p-4 flex-grow flex flex-col justify-between">
        <div class="mb-4">
            <?php if (!empty($p['categoria'])): ?>
                <span class="text-[9px] font-bold text-primary uppercase tracking-widest block mb-1"><?= e($p['categoria']) ?></span>
            <?php endif; ?>
            <h4 class="text-xs font-bold text-on-surface line-clamp-2 group-hover:text-primary transition-colors leading-snug"><?= e($p['nome']) ?></h4>
        </div>
        <div class="flex items-end justify-between pt-3 border-t border-surface-container-low">
            <div class="flex flex-col">
                <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">a partir de</span>
                <span class="text-sm font-black text-primary"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
            </div>
            <span class="material-symbols-outlined text-primary text-lg opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all">arrow_forward</span>
        </div>
    </div>
</a>
