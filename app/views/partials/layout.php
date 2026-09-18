<?php
/**
 * Layout padrão. Cabeçalho ÚNICO de SEO: nenhuma view monta <head> sozinha.
 *
 * @var string     $conteudo
 * @var string     $titulo   título SEM marca (Seo::title aplica " | Novare Brindes")
 * @var array|null $categorias
 * @var array|null $meta     description, canonical, indexavel, og_image, og_type,
 *                           breadcrumbs[], schemas[], faq[], preload_image
 */
$cats = $categorias ?? [];
if (!$cats) {
    try {
        $cats = ProductRepository::create()->categorias();
    } catch (Throwable $e) {
        $cats = [];
    }
}
$logoUrl = SiteContent::logoUrl();
$meta    = $meta ?? [];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$ehHome      = $requestPath === '/';

$tituloFinal = Seo::title((string) ($titulo ?? ''));
$metaDesc    = Seo::description((string) ($meta['description'] ?? 'Brindes corporativos personalizados com a logo da sua empresa. Orçamento rápido pelo WhatsApp e entrega para todo o Brasil.'));
$canonicalUrl = (string) ($meta['canonical'] ?? Seo::canonical($requestPath));
$indexavel   = array_key_exists('indexavel', $meta) ? (bool) $meta['indexavel'] : true;
$robotsMeta  = $indexavel
    ? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'
    : 'noindex,follow';

// Open Graph (WhatsApp/LinkedIn: no B2B é o canal principal de compartilhamento)
$ogTitle = (string) ($meta['og_title'] ?? $tituloFinal);
$ogDesc  = (string) ($meta['og_description'] ?? $metaDesc);
$ogImg   = (string) ($meta['og_image'] ?? '') ?: SiteContent::LOGO_PADRAO;
if (!str_starts_with($ogImg, 'http')) {
    $ogImg = urlAbsoluta($ogImg);
}
$ogType  = ($meta['og_type'] ?? 'website') === 'product' ? 'product' : 'website';

// JSON-LD: Organization/WebSite só na home (consolida a entidade), Breadcrumb
// em toda interna, e o que o controller mandar (Product, ItemList, FAQ).
$schemas = [];
if ($ehHome) {
    $schemas[] = Seo::schemaOrganization();
}
if (!empty($meta['breadcrumbs']) && is_array($meta['breadcrumbs'])) {
    $schemas[] = Seo::schemaBreadcrumb($meta['breadcrumbs']);
}
foreach ($meta['schemas'] ?? [] as $s) {
    if ($s) {
        $schemas[] = $s;
    }
}
if (!empty($meta['faq'])) {
    $faqSchema = Seo::schemaFaq($meta['faq']);
    if ($faqSchema) {
        $schemas[] = $faqSchema;
    }
}

// CSS compilado (Tailwind estático) com hash de conteúdo para cache de 1 ano
$cssFile = APP_ROOT . '/public/assets/css/app.css';
$cssVer  = is_file($cssFile) ? substr(md5_file($cssFile) ?: '', 0, 8) : '1';
$catAtualSlug = $categoria_atual['slug'] ?? null;
$entidade = Settings::get('seo_entidade', []);
$entidade = is_array($entidade) ? $entidade : [];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tituloFinal) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="robots" content="<?= e($robotsMeta) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-TWRJXWFJ');</script>
    <!-- End Google Tag Manager -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if (!empty($meta['preload_image'])): ?>
    <link rel="preload" as="image" href="<?= e($meta['preload_image']) ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= $cssVer ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <link rel="shortcut icon" href="<?= url('/favicon.ico') ?>" type="image/x-icon">
    <link rel="icon" href="<?= url('/favicon.png') ?>" type="image/png" sizes="48x48">
    <link rel="apple-touch-icon" href="<?= url('/assets/images/favicon.png') ?>" sizes="180x180">
    <meta name="theme-color" content="#006590">

    <!-- Open Graph / Twitter -->
    <meta property="og:type" content="<?= $ogType ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Novare Brindes">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDesc) ?>">
    <meta property="og:image" content="<?= e($ogImg) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= e($ogDesc) ?>">
    <meta name="twitter:image" content="<?= e($ogImg) ?>">

    <?php foreach ($schemas as $s): ?>
    <script type="application/ld+json"><?= json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endforeach; ?>
</head>
<body class="flex flex-col min-h-screen h-full bg-background text-on-background antialiased">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TWRJXWFJ"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <!-- Promotion Bar -->
    <div class="bg-primary text-on-primary py-2 px-6 text-center text-xs font-semibold tracking-wide shadow-sm">
        Transforme sua marca com brindes que encantam. Atendimento especializado!
    </div>

    <!-- Top Header -->
    <header class="bg-white px-6 py-4 border-b border-surface-container/50">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <!-- Brand & Search Row -->
            <div class="flex items-center justify-between w-full md:w-auto gap-8">
                <a href="<?= url('/') ?>" class="flex items-center">
                    <img alt="Novare Brindes" class="h-10 w-auto object-contain" src="<?= e($logoUrl) ?>" onerror="this.onerror=null;this.src='<?= e(SiteContent::LOGO_PADRAO) ?>'" />
                </a>
            </div>

            <!-- Wide Search Bar -->
            <form class="flex-grow w-full max-w-3xl" action="<?= url('/busca') ?>" method="get" role="search">
                <div class="relative group">
                    <input class="w-full bg-surface-container-low border-none rounded-full py-3 px-6 pr-12 text-sm focus:ring-2 focus:ring-primary/40 focus:bg-white transition-all outline-none" name="q" placeholder="Procure por canetas, squeezes ou kits personalizados..." type="search" value="<?= e(q('q') ?? '') ?>" aria-label="Buscar produtos">
                    <button type="submit" class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors flex items-center">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                </div>
            </form>

            <!-- Actions Row -->
            <div class="flex items-center gap-6">
                <a id="whats-header" href="<?= e(whatsappLink('Olá, tudo bem? Eu vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener" class="primary-gradient text-white px-5 py-2.5 rounded-lg text-xs font-bold whitespace-nowrap hidden xl:block shadow-sm hover:opacity-90 transition-opacity">
                    Fale com o Nosso Time &gt;
                </a>
                <div class="flex items-center gap-5 text-secondary">
                    <a href="https://rastreamento.correios.com.br/app/index.php" target="_blank" rel="noopener" class="flex flex-col items-center cursor-pointer group text-center">
                        <span class="material-symbols-outlined group-hover:text-primary transition-colors">package_2</span>
                        <span class="text-[10px] font-bold uppercase mt-1">Rastrear</span>
                    </a>
                    <a href="<?= url('/sobre') ?>" class="flex flex-col items-center cursor-pointer group text-center">
                        <span class="material-symbols-outlined group-hover:text-primary transition-colors">info</span>
                        <span class="text-[10px] font-bold uppercase mt-1">Sobre</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Menu -->
    <nav class="glass-nav sticky top-0 z-50 shadow-sm border-b border-surface-container">
        <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-6 overflow-x-auto no-scrollbar py-2 w-full lg:w-auto">
                <a class="text-slate-600 hover:text-primary transition-colors text-sm font-medium whitespace-nowrap <?= $requestPath === '/brindes' && empty(q('sustentavel')) ? 'text-primary font-bold border-b-2 border-primary pb-1' : '' ?>" href="<?= url(Seo::urlHub()) ?>">Todos os Brindes</a>
                <?php foreach (array_slice($cats, 0, 9) as $c): ?>
                    <?php $active = ($catAtualSlug !== null && $catAtualSlug === ($c['slug'] ?? null)) ? 'text-primary font-bold border-b-2 border-primary pb-1' : ''; ?>
                    <a class="text-slate-600 hover:text-primary transition-colors text-sm font-medium whitespace-nowrap <?= $active ?>" href="<?= url(Seo::urlCategoria($c['categoria'])) ?>"><?= e($c['categoria']) ?></a>
                <?php endforeach; ?>
                <a class="text-slate-600 hover:text-primary transition-colors text-sm font-medium whitespace-nowrap <?= !empty(q('sustentavel')) ? 'text-primary font-bold border-b-2 border-primary pb-1' : '' ?>" href="<?= url(Seo::urlHub() . '?sustentavel=1') ?>" rel="nofollow">Sustentáveis</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow w-full">
        <?= $conteudo ?>
    </main>

    <!-- Newsletter Premium 5% OFF -->
    <section class="bg-slate-50 border-t border-b border-surface-container/30 py-12 select-none" id="section-newsletter">
        <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col lg:flex-row items-center justify-between gap-8">
            <!-- Textos Promocionais -->
            <div class="max-w-xl text-center lg:text-left">
                <h3 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 mb-2 uppercase">
                    GANHE <span class="text-primary font-black border border-primary/20 px-2.5 py-0.5 rounded-lg bg-primary/5 text-sky-600">[5% OFF]</span> NA SUA PRIMEIRA COTAÇÃO!
                </h3>
                <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                    Cadastre-se, receba novidades e garanta 5% de desconto no seu primeiro orçamento.
                    <span class="block text-[10px] text-slate-400 mt-1 font-bold">*consulte condições. *não acumulativo.</span>
                </p>
            </div>
            <!-- Formulário com inputs pílula -->
            <form id="newsletter-form" class="w-full lg:w-auto flex flex-col sm:flex-row items-center gap-3 sm:min-w-[500px]">
                <div class="w-full relative">
                    <input type="text" placeholder="NOME" required class="w-full bg-white border border-slate-200 text-slate-800 text-[10px] font-black tracking-wider px-6 py-4 rounded-full focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all uppercase placeholder-slate-400" name="nome" />
                </div>
                <div class="w-full relative">
                    <input type="email" placeholder="E-MAIL" required class="w-full bg-white border border-slate-200 text-slate-800 text-[10px] font-black tracking-wider px-6 py-4 rounded-full focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all uppercase placeholder-slate-400" name="email" />
                </div>
                <button type="submit" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-950 text-white text-[10px] font-black tracking-widest px-8 py-4 rounded-full hover:scale-105 active:scale-95 transition-all uppercase whitespace-nowrap shadow-md cursor-pointer">
                    CADASTRAR
                </button>
            </form>
            <!-- Div de Feedback Sucesso Oculta -->
            <div id="newsletter-success" class="hidden w-full lg:w-auto text-center py-4 px-8 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-full items-center justify-center gap-2.5 font-black text-xs shadow-sm animate-pulse">
                <span class="material-symbols-outlined text-sm font-bold">check_circle</span>
                🎉 Cadastro realizado! Seu cupom de 5% OFF foi enviado!
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 text-white mt-auto">
        <div class="max-w-7xl mx-auto px-6 md:px-12 py-16">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                <!-- Brand Column -->
                <div class="col-span-1">
                    <a href="<?= url('/') ?>" class="inline-block mb-4">
                        <img alt="Novare Brindes" class="h-10 w-auto object-contain brightness-0 invert" src="<?= e($logoUrl) ?>" onerror="this.onerror=null;this.src='<?= e(SiteContent::LOGO_PADRAO) ?>'" />
                    </a>
                    <p class="text-slate-400 text-xs leading-relaxed mb-6">
                        Seleção sob medida. Elevando o valor e a percepção da sua marca através de brindes personalizados e presentes corporativos desenvolvidos com excelência técnica para sua empresa, do popular ao executivo.
                    </p>
                    <span class="text-[10px] uppercase tracking-[0.2em] font-extrabold text-primary-container text-sky-400">Pronto para impressionar?</span>
                </div>
                <!-- Links Columns -->
                <div>
                    <h4 class="text-xs uppercase tracking-widest font-bold mb-6 text-white border-l-2 border-primary pl-3">Principais Categorias</h4>
                    <ul class="space-y-3">
                        <?php foreach ([['Bolsas e Mochilas', 'Mochilas e bolsas personalizadas'], ['Canetas', 'Canetas personalizadas'], ['Garrafas e Squeezes', 'Garrafas e squeezes personalizados'], ['Kits e Conjuntos', 'Kits de onboarding'], ['Moleskine & Cadernos', 'Moleskines e cadernos personalizados'], ['Canecas e Copos', 'Canecas personalizadas'], ['Tecnologia', 'Brindes de tecnologia']] as [$catNome, $rotulo]): ?>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url(Seo::urlCategoria($catNome)) ?>"><?= e($rotulo) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs uppercase tracking-widest font-bold mb-6 text-white border-l-2 border-primary pl-3">Empresa & Links</h4>
                    <ul class="space-y-3">
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/sobre') ?>">Nossa História</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="https://rastreamento.correios.com.br/app/index.php" target="_blank" rel="noopener">Rastrear Entrega</a></li>
                        <li><a id="whats-footer-time" class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= e(whatsappLink('Olá, tudo bem? Eu vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener">Fale com o Nosso Time</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url(Seo::urlHub()) ?>">Ver Todos os Produtos</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs uppercase tracking-widest font-bold mb-6 text-white border-l-2 border-primary pl-3">Contato & Briefing</h4>
                    <ul class="space-y-3">
                        <li><a id="whats-footer-comercial" class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider font-semibold" href="<?= e(whatsappLink('Olá, tudo bem? Eu vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener">WhatsApp Comercial</a></li>
                        <li class="text-slate-500 text-[10px] mt-2 uppercase tracking-wide leading-relaxed">Atendimento rápido<br>Segunda a Sexta-Feira<br>Horário Comercial</li>
                    </ul>
                </div>
            </div>
            <!-- Bottom Footer with Payments -->
            <div class="pt-8 border-t border-slate-900 flex flex-col md:flex-row justify-between items-center gap-6 select-none">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest text-center md:text-left leading-relaxed">
                    © <?= date('Y') ?> Novare Brindes. Todos os direitos reservados. Catálogo consultivo sem vendas online diretas.
                    <?php if (!empty($entidade['razao_social']) || !empty($entidade['cnpj'])): ?>
                    <br><?= e($entidade['razao_social'] ?? '') ?><?= !empty($entidade['cnpj']) ? ' · CNPJ ' . e($entidade['cnpj']) : '' ?>
                    <?php endif; ?>
                    <?php if (!empty($entidade['endereco']) || !empty($entidade['cidade'])): ?>
                    <br><?= e(trim(($entidade['endereco'] ?? '') . ' ' . ($entidade['cidade'] ?? '') . ($entidade['uf'] ?? '' ? ' - ' . $entidade['uf'] : '') . (!empty($entidade['cep']) ? ' · CEP ' . $entidade['cep'] : ''))) ?>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-6 justify-center">
                    <!-- Formas de Pagamento Faturado -->
                    <div class="flex items-center gap-3 bg-slate-900/60 px-4 py-2 rounded-xl border border-slate-900 shadow-sm text-slate-400 text-[9px] font-bold uppercase tracking-wider">
                        <span>Formas de pagamento faturado:</span>
                        <div class="flex gap-2.5 text-white">
                            <span class="material-symbols-outlined text-xs cursor-help" title="Pix Faturado com Desconto">qr_code_2</span>
                            <span class="material-symbols-outlined text-xs cursor-help" title="Cartão de Crédito Corporativo">credit_card</span>
                            <span class="material-symbols-outlined text-xs cursor-help" title="Boleto Bancário Faturado">description</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <?php partial('chat'); ?>

    <script src="<?= asset('js/novare.js') ?>" defer></script>
</body>
</html>
