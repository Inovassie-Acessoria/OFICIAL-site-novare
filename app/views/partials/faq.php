<?php
/**
 * Blocos de resposta direta (AEO/GEO). H2 escrito como a pergunta real e a
 * resposta completa logo abaixo. As MESMAS perguntas vão no schema FAQPage
 * (meta['faq']) — schema de conteúdo invisível é violação.
 *
 * @var array  $faq    [['pergunta' => ..., 'resposta' => ...], ...]
 * @var string $titulo opcional
 */
if (empty($faq)) {
    return;
}
?>
<section class="mt-12 border-t border-surface-container/60 pt-10" id="perguntas-frequentes">
    <h2 class="text-lg font-black text-on-surface tracking-tight mb-6"><?= e($titulo ?? 'Perguntas frequentes') ?></h2>
    <div class="space-y-3">
        <?php foreach ($faq as $f): ?>
            <details class="group bg-white border border-surface-container rounded-2xl shadow-sm open:shadow-md transition-shadow">
                <summary class="cursor-pointer list-none flex items-start justify-between gap-4 px-6 py-4">
                    <h3 class="text-sm font-bold text-on-surface leading-snug"><?= e($f['pergunta']) ?></h3>
                    <span class="material-symbols-outlined text-primary text-xl flex-shrink-0 transition-transform group-open:rotate-180">expand_more</span>
                </summary>
                <div class="px-6 pb-5 text-sm text-secondary leading-relaxed"><?= nl2br(e(strip_tags((string) $f['resposta']))) ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
