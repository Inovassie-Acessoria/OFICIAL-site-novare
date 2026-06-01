<?php
/**
 * View de Status do Ambiente estilizada com Tailwind CSS.
 * @var array<string,bool> $checks
 */
?>
<section class="max-w-xl mx-auto px-6 py-16">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-8" aria-label="Breadcrumb">
        <a href="<?= url('/') ?>" class="hover:text-primary transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">home</span> Início
        </a>
        <span class="material-symbols-outlined text-[10px]">chevron_right</span>
        <span class="text-secondary font-semibold">Status do Ambiente</span>
    </nav>

    <div class="mb-8 text-center md:text-left">
        <h1 class="text-3xl font-black text-on-surface tracking-tighter mb-2 flex items-center justify-center md:justify-start gap-2.5">
            <span class="material-symbols-outlined text-primary text-3xl">terminal</span> Status do Sistema
        </h1>
        <p class="text-slate-500 text-xs">Diagnóstico em tempo real dos serviços e conexões Novare Brindes.</p>
    </div>
    <div class="bg-white border border-surface-container rounded-2xl p-6 shadow-sm">
        <ul class="divide-y divide-surface-container/50">
            <?php foreach ($checks as $label => $ok): ?>
                <li class="flex items-center justify-between py-4 gap-4">
                    <span class="text-xs font-bold text-secondary flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-slate-400 text-sm">settings_ethernet</span>
                        <?= e($label) ?>
                    </span>
                    <span class="flex items-center gap-2 text-xs font-black uppercase tracking-wider <?= $ok ? 'text-emerald-600' : 'text-red-600' ?>">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-black <?= $ok ? 'bg-emerald-500 shadow-emerald-100 shadow-sm' : 'bg-red-500 shadow-red-100 shadow-sm' ?>">
                            <?= $ok ? '✓' : '✕' ?>
                        </span>
                        <?= $ok ? 'Operacional' : 'Falha' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
