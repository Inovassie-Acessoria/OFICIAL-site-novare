<?php
/**
 * Landing de ocasião/segmento (/brindes-para-sipat, /kit-boas-vindas...).
 * Onde a concorrência é mais fraca e a intenção mais alta: nenhum revendedor
 * cobre essas buscas porque elas não vêm no catálogo do fornecedor.
 * Conteúdo é curadoria manual (admin): intro própria, 8–15 produtos, FAQ.
 *
 * @var array $ocasiao
 * @var array $produtos
 * @var array $faq
 * @var array $categorias_relacionadas
 * @var array $breadcrumbs
 */
$msgWhats = 'Olá! Quero um orçamento de brindes para ' . $ocasiao['titulo'] . '. Quantidade aproximada: ';
?>
<div class="max-w-7xl mx-auto px-6 py-8">
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6 flex-wrap" aria-label="Você está em">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span class="material-symbols-outlined text-[10px]">chevron_right</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
                <a href="<?= e($bc['url']) ?>" class="hover:text-primary transition-colors flex items-center gap-1"><span class="material-symbols-outlined text-sm">home</span><?= e($bc['name']) ?></a>
            <?php else: ?>
                <span class="text-secondary font-semibold"><?= e($bc['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <header class="max-w-3xl mb-10">
        <h1 class="text-3xl md:text-4xl font-black text-on-surface tracking-tighter leading-tight mb-4"><?= e($ocasiao['h1']) ?></h1>
        <div class="text-base text-secondary leading-relaxed space-y-3"><?= nl2br(e(strip_tags((string) $ocasiao['intro'], '<b><strong><em><i><br><p><a><ul><ol><li>'))) ?></div>
        <a href="<?= e(whatsappLink($msgWhats)) ?>" target="_blank" rel="noopener nofollow" data-evento="orcamento_whatsapp" data-sku="ocasiao:<?= e($ocasiao['slug']) ?>" class="mt-6 inline-flex items-center gap-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black uppercase tracking-widest py-4 px-8 rounded-xl shadow-lg transition-all">
            <span class="material-symbols-outlined text-lg">chat</span> Pedir orçamento para <?= e(mb_strtolower($ocasiao['titulo'], 'UTF-8')) ?>
        </a>
    </header>

    <?php if ($produtos): ?>
        <section class="mb-12">
            <h2 class="text-lg font-black text-on-surface tracking-tight mb-6">Brindes selecionados para <?= e(mb_strtolower($ocasiao['titulo'], 'UTF-8')) ?></h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($produtos as $p): ?>
                    <?php partial('card', ['p' => $p]); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($ocasiao['corpo'])): ?>
        <section class="max-w-3xl mb-12 text-sm text-secondary leading-relaxed space-y-4">
            <?= strip_tags((string) $ocasiao['corpo'], '<h2><h3><p><b><strong><em><i><br><a><ul><ol><li><table><thead><tbody><tr><th><td>') ?>
        </section>
    <?php endif; ?>

    <?php partial('faq', ['faq' => $faq, 'titulo' => 'Perguntas frequentes sobre brindes para ' . mb_strtolower($ocasiao['titulo'], 'UTF-8')]); ?>

    <?php if ($categorias_relacionadas): ?>
        <section class="mt-12 border-t border-surface-container/60 pt-10">
            <h2 class="text-lg font-black text-on-surface tracking-tight mb-5">Categorias relacionadas</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($categorias_relacionadas as $c): ?>
                    <a href="<?= e(Seo::urlCategoriaSlug($c['slug'])) ?>" class="bg-white border border-surface-container hover:border-primary hover:text-primary px-3.5 py-1.5 rounded-xl text-xs font-semibold text-secondary transition-colors shadow-sm"><?= e($c['nome']) ?> personalizados</a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
