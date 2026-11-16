<?php
/**
 * Lista de matérias (/blog).
 *
 * @var array $posts       linhas de blog_posts publicadas + 'capa'
 * @var array $paginacao   Seo::paginacao()
 * @var int   $pagina
 * @var array $breadcrumbs
 */
$fmtData = static fn (?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '';
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
        <h1 class="text-3xl md:text-4xl font-black text-on-surface tracking-tighter leading-tight mb-4">Blog de brindes corporativos</h1>
        <p class="text-base text-secondary leading-relaxed">Guias para escolher, personalizar e cotar brindes para a sua empresa: canetas, cadernos e moleskines, mochilas, garrafas, squeezes, canecas, copos e kits para cada ocasião.</p>
    </header>

    <?php if (!$posts): ?>
        <p class="text-sm text-secondary">As primeiras matérias estão a caminho. Enquanto isso, veja o <a href="<?= e(url(Seo::urlHub())) ?>" class="text-primary font-semibold hover:underline">catálogo completo de brindes</a>.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($posts as $p): ?>
                <article class="group bg-white border border-surface-container rounded-2xl shadow-sm hover:shadow-md transition-shadow overflow-hidden flex flex-col">
                    <a href="<?= e(Seo::urlPost((string) $p['slug'])) ?>" class="block aspect-[16/9] bg-surface-container-low overflow-hidden">
                        <?php if (!empty($p['capa'])): ?>
                            <img src="<?= e($p['capa']) ?>" alt="<?= e($p['imagem_alt'] ?: $p['titulo']) ?>" loading="lazy" decoding="async" class="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform">
                        <?php endif; ?>
                    </a>
                    <div class="p-6 flex flex-col gap-3 flex-grow">
                        <time datetime="<?= e(date('Y-m-d', strtotime((string) $p['publicado_em']))) ?>" class="text-[10px] font-bold uppercase tracking-widest text-slate-400"><?= e($fmtData($p['publicado_em'])) ?></time>
                        <h2 class="text-base font-black text-on-surface tracking-tight leading-snug"><a href="<?= e(Seo::urlPost((string) $p['slug'])) ?>" class="hover:text-primary transition-colors"><?= e($p['titulo']) ?></a></h2>
                        <p class="text-sm text-secondary leading-relaxed flex-grow"><?= e(Seo::truncar(strip_tags((string) $p['resumo']), 170)) ?></p>
                        <a href="<?= e(Seo::urlPost((string) $p['slug'])) ?>" class="text-xs font-bold text-primary inline-flex items-center gap-1">Ler matéria <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($paginacao['anterior'] || $paginacao['proxima']): ?>
            <nav class="flex justify-between items-center mt-10 text-sm font-semibold" aria-label="Paginação do blog">
                <?php if ($paginacao['anterior']): ?><a rel="prev" href="<?= e($paginacao['anterior']) ?>" class="text-primary hover:underline">Matérias mais recentes</a><?php else: ?><span></span><?php endif; ?>
                <span class="text-xs text-slate-400">Página <?= (int) $pagina ?></span>
                <?php if ($paginacao['proxima']): ?><a rel="next" href="<?= e($paginacao['proxima']) ?>" class="text-primary hover:underline">Matérias anteriores</a><?php else: ?><span></span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
