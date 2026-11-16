<?php
/**
 * Matéria do blog (/blog/{slug}).
 * Estrutura pensada para SEO e respostas de IA: H1 com a palavra-chave, lead
 * que responde a pergunta logo de cara, sumário com âncoras, H2 por subtema,
 * produtos citados com link interno, FAQ visível (= schema FAQPage) e CTA de orçamento.
 *
 * @var array       $post
 * @var string      $corpo     HTML já sanitizado, com id nos h2
 * @var array       $sumario   [[id, texto], ...]
 * @var string|null $capa
 * @var int         $minutos
 * @var array       $produtos
 * @var array       $faq
 * @var array       $categorias_relacionadas
 * @var array       $relacionados
 * @var bool        $previa
 * @var array       $breadcrumbs
 */
$dataPub = !empty($post['publicado_em']) ? strtotime((string) $post['publicado_em']) : null;
$dataAtu = !empty($post['updated_at']) ? strtotime((string) $post['updated_at']) : null;
$msgWhats = 'Olá! Li a matéria "' . $post['titulo'] . '" no blog e quero um orçamento de brindes. Quantidade aproximada: ';
?>
<div class="max-w-7xl mx-auto px-6 py-8">
    <?php if ($previa): ?>
        <div class="max-w-3xl mb-6 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold px-4 py-3">
            Prévia: esta matéria está como <?= e($post['status']) ?><?= $dataPub ? ' (publicação em ' . e(date('d/m/Y H:i', $dataPub)) . ')' : '' ?> e só aparece para quem está logado no painel. Ela não é indexada.
        </div>
    <?php endif; ?>

    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6 flex-wrap" aria-label="Você está em">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span class="material-symbols-outlined text-[10px]">chevron_right</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
                <a href="<?= e($bc['url']) ?>" class="hover:text-primary transition-colors flex items-center gap-1"><?php if ($i === 0): ?><span class="material-symbols-outlined text-sm">home</span><?php endif; ?><?= e($bc['name']) ?></a>
            <?php else: ?>
                <span class="text-secondary font-semibold"><?= e($bc['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <article class="max-w-3xl">
        <header class="mb-8">
            <h1 class="text-3xl md:text-4xl font-black text-on-surface tracking-tighter leading-tight mb-4"><?= e($post['titulo']) ?></h1>
            <p class="text-xs text-slate-400 font-semibold mb-6">
                Por Equipe Novare Brindes
                <?php if ($dataPub): ?> · <time datetime="<?= e(date('c', $dataPub)) ?>"><?= e(date('d/m/Y', $dataPub)) ?></time><?php endif; ?>
                <?php if ($dataAtu && $dataPub && date('Y-m-d', $dataAtu) !== date('Y-m-d', $dataPub)): ?> · atualizado em <time datetime="<?= e(date('c', $dataAtu)) ?>"><?= e(date('d/m/Y', $dataAtu)) ?></time><?php endif; ?>
                · <?= (int) $minutos ?> min de leitura
            </p>
            <div class="text-base text-secondary leading-relaxed"><?= nl2br(e(strip_tags((string) $post['resumo']))) ?></div>
        </header>

        <?php if ($capa): ?>
            <figure class="mb-10 bg-white border border-surface-container rounded-2xl overflow-hidden">
                <img src="<?= e($capa) ?>" alt="<?= e($post['imagem_alt'] ?: $post['titulo']) ?>" fetchpriority="high" decoding="async" class="w-full max-h-[420px] object-contain p-6">
            </figure>
        <?php endif; ?>

        <?php if (count($sumario) >= 3): ?>
            <nav class="mb-10 bg-surface-container-low rounded-2xl px-6 py-5" aria-label="Neste artigo">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-3">Neste artigo</p>
                <ol class="space-y-2 text-sm list-decimal list-inside">
                    <?php foreach ($sumario as [$id, $texto]): ?>
                        <li><a href="#<?= e($id) ?>" class="text-secondary hover:text-primary transition-colors"><?= e($texto) ?></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <?php if ($corpo !== ''): ?>
            <div class="blog-corpo"><?= $corpo ?></div>
        <?php endif; ?>

        <div class="mt-10 bg-white border border-surface-container rounded-2xl shadow-sm px-6 py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <p class="text-sm font-bold text-on-surface">Quer esses brindes com a logo da sua empresa? Mande a quantidade e a data de entrega que a gente monta o orçamento.</p>
            <a href="<?= e(whatsappLink($msgWhats)) ?>" target="_blank" rel="noopener nofollow" data-evento="orcamento_whatsapp" data-sku="blog:<?= e($post['slug']) ?>" class="inline-flex items-center justify-center gap-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black uppercase tracking-widest py-4 px-8 rounded-xl shadow-lg transition-all whitespace-nowrap">
                <span class="material-symbols-outlined text-lg">chat</span> Pedir orçamento
            </a>
        </div>
    </article>

    <?php if ($produtos): ?>
        <section class="mt-12">
            <h2 class="text-lg font-black text-on-surface tracking-tight mb-6">Brindes citados nesta matéria</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($produtos as $p): ?>
                    <?php partial('card', ['p' => $p]); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="max-w-3xl">
        <?php partial('faq', ['faq' => $faq, 'titulo' => 'Perguntas frequentes']); ?>
    </div>

    <?php if ($categorias_relacionadas): ?>
        <section class="mt-12 border-t border-surface-container/60 pt-10">
            <h2 class="text-lg font-black text-on-surface tracking-tight mb-5">Veja no catálogo</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($categorias_relacionadas as $c): ?>
                    <a href="<?= e(Seo::urlCategoriaSlug($c['slug'])) ?>" class="bg-white border border-surface-container hover:border-primary hover:text-primary px-3.5 py-1.5 rounded-xl text-xs font-semibold text-secondary transition-colors shadow-sm"><?= e(trim((string) ($c['rotulo_seo'] ?? '')) ?: $c['nome']) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($relacionados): ?>
        <section class="mt-12 border-t border-surface-container/60 pt-10">
            <h2 class="text-lg font-black text-on-surface tracking-tight mb-6">Continue lendo</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <?php foreach ($relacionados as $r): ?>
                    <a href="<?= e(Seo::urlPost((string) $r['slug'])) ?>" class="group bg-white border border-surface-container rounded-2xl shadow-sm hover:shadow-md transition-shadow p-5 flex flex-col gap-2">
                        <span class="text-sm font-black text-on-surface leading-snug group-hover:text-primary transition-colors"><?= e($r['titulo']) ?></span>
                        <span class="text-xs text-secondary leading-relaxed"><?= e(Seo::truncar(strip_tags((string) $r['resumo']), 110)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
