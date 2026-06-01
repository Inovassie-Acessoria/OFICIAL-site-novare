<?php
/**
 * @var string $conteudo
 * @var string $titulo
 * @var array|null $categorias
 */
$cats = $categorias ?? [];
if (!$cats) {
    try {
        $cats = ProductRepository::create()->categorias();
    } catch (Throwable $e) {
        $cats = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? 'Novare Brindes — Brindes Corporativos') ?></title>
    <meta name="description" content="Brindes corporativos personalizados para eventos, feiras e kits de onboarding. Atendimento de alta excelência e inteligência de marca.">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "outline-variant": "#bec8d1",
              "on-primary": "#ffffff",
              "surface-container-lowest": "#ffffff",
              "inverse-on-surface": "#f0f0f3",
              "on-tertiary-fixed": "#2a1700",
              "surface-container-high": "#e8e8ea",
              "tertiary": "#845400",
              "on-secondary-fixed-variant": "#264a62",
              "primary-fixed-dim": "#88ceff",
              "on-surface-variant": "#3e4850",
              "surface": "#f9f9fc",
              "surface-container": "#eeeef0",
              "inverse-primary": "#88ceff",
              "surface-bright": "#f9f9fc",
              "surface-container-low": "#f3f3f6",
              "secondary-fixed-dim": "#a7cbe7",
              "on-surface": "#1a1c1e",
              "error-container": "#ffdad6",
              "on-error": "#ffffff",
              "on-primary-fixed": "#001e2f",
              "on-primary-container": "#00344d",
              "primary-container": "#24a1e0",
              "tertiary-fixed": "#ffddb7",
              "on-primary-fixed-variant": "#004c6e",
              "inverse-surface": "#2f3133",
              "on-tertiary-container": "#462a00",
              "on-error-container": "#93000a",
              "error": "#ba1a1a",
              "tertiary-container": "#d1880c",
              "background": "#f9f9fc",
              "surface-container-highest": "#e2e2e5",
              "surface-dim": "#dadadc",
              "secondary-fixed": "#c8e6ff",
              "secondary": "#3f627b",
              "primary-fixed": "#c8e6ff",
              "surface-variant": "#e2e2e5",
              "on-secondary-fixed": "#001e2f",
              "secondary-container": "#bde1fe",
              "outline": "#6f7881",
              "on-background": "#1a1c1e",
              "primary": "#006590",
              "tertiary-fixed-dim": "#ffb95b",
              "on-secondary-container": "#42657d",
              "on-secondary": "#ffffff",
              "on-tertiary": "#ffffff",
              "on-tertiary-fixed-variant": "#643f00",
              "surface-tint": "#006590"
            },
            borderRadius: {
              "DEFAULT": "0.25rem",
              "lg": "0.5rem",
              "xl": "0.75rem",
              "full": "9999px"
            },
            fontFamily: {
              "headline": ["Inter"],
              "body": ["Inter"],
              "label": ["Inter"]
            }
          },
        },
      }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f9f9fc; color: #1a1c1e; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .primary-gradient { background: linear-gradient(135deg, #006590 0%, #24a1e0 100%); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="flex flex-col min-h-screen h-full bg-background text-on-background antialiased">
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
                    <img alt="Novare Brindes" class="h-10 w-auto object-contain" src="https://novarebrindes.com.br/wp-content/uploads/2025/10/LOGO-IMAGEM-1.png" />
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
                <a href="<?= e(whatsappLink('Olá! Gostaria de falar com o time de atendimento da Novare.')) ?>" target="_blank" rel="noopener" class="primary-gradient text-white px-5 py-2.5 rounded-lg text-xs font-bold whitespace-nowrap hidden xl:block shadow-sm hover:opacity-90 transition-opacity">
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
                <a class="text-slate-600 hover:text-primary transition-colors text-sm font-medium whitespace-nowrap <?= empty(q('categoria')) && empty(q('sustentavel')) && !str_contains($_SERVER['REQUEST_URI'], '/sobre') && !str_contains($_SERVER['REQUEST_URI'], '/atendimento') ? 'text-primary font-bold border-b-2 border-primary pb-1' : '' ?>" href="<?= url('/catalogo') ?>">Novidades</a>
                <?php foreach (array_slice($cats, 0, 7) as $c): ?>
                    <?php 
                        $active = (q('categoria') === $c['categoria']) ? 'text-primary font-bold border-b-2 border-primary pb-1' : ''; 
                    ?>
                    <a class="text-slate-600 hover:text-primary transition-colors text-sm font-medium whitespace-nowrap <?= $active ?>" href="<?= url('/catalogo?categoria=' . rawurlencode($c['categoria'])) ?>"><?= e($c['categoria']) ?></a>
                <?php endforeach; ?>
                <a class="text-slate-600 hover:text-primary transition-colors text-sm font-medium whitespace-nowrap <?= !empty(q('sustentavel')) ? 'text-primary font-bold border-b-2 border-primary pb-1' : '' ?>" href="<?= url('/catalogo?sustentavel=1') ?>">Sustentáveis</a>
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
                        <img alt="Novare Brindes" class="h-10 w-auto object-contain brightness-0 invert" src="https://novarebrindes.com.br/wp-content/uploads/2025/10/LOGO-IMAGEM-1.png" />
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
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/catalogo?categoria=' . rawurlencode('BOLSAS E MOCHILAS')) ?>">Mochilas e Bolsas</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/catalogo?categoria=' . rawurlencode('ESCRITA')) ?>">Canetas e Lápis</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/catalogo?categoria=' . rawurlencode('GARRAFAS E SQUEEZES')) ?>">Garrafas e Squeezes</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/catalogo?categoria=' . rawurlencode('KITS E CONJUNTOS')) ?>">Kits de Onboarding</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/catalogo?categoria=' . rawurlencode('CADERNOS E AGENDAS')) ?>">Moleskines e Cadernos</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs uppercase tracking-widest font-bold mb-6 text-white border-l-2 border-primary pl-3">Empresa & Links</h4>
                    <ul class="space-y-3">
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/sobre') ?>">Nossa História</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="https://rastreamento.correios.com.br/app/index.php" target="_blank" rel="noopener">Rastrear Entrega</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= e(whatsappLink('Olá! Gostaria de falar com o time de atendimento da Novare.')) ?>" target="_blank" rel="noopener">Fale com o Nosso Time</a></li>
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider" href="<?= url('/catalogo') ?>">Ver Todos os Produtos</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs uppercase tracking-widest font-bold mb-6 text-white border-l-2 border-primary pl-3">Contato & Briefing</h4>
                    <ul class="space-y-3">
                        <li><a class="text-slate-400 text-xs hover:text-white transition-all uppercase tracking-wider font-semibold" href="<?= e(whatsappLink('Olá! Gostaria de falar com o time consultivo da Novare.')) ?>" target="_blank" rel="noopener">WhatsApp Comercial</a></li>
                        <li class="text-slate-500 text-[10px] mt-2 uppercase tracking-wide leading-relaxed">Atendimento rápido<br>Segunda a Sexta-Feira<br>Horário Comercial</li>
                    </ul>
                </div>
            </div>
            <!-- Bottom Footer with Payments -->
            <div class="pt-8 border-t border-slate-900 flex flex-col md:flex-row justify-between items-center gap-6 select-none">
                <div class="text-[10px] text-slate-500 uppercase tracking-widest text-center md:text-left">
                    © <?= date('Y') ?> Novare Brindes. Todos os direitos reservados. Catálogo consultivo sem vendas online diretas.
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
