<?php
/**
 * View da Homepage reestruturada com design premium, slider de 5s,
 * círculos de fotos dinâmicos, ranking Top 10, grade 3x2 humanizada e sem curadoria.
 * 
 * @var array $categorias
 * @var array $destaques
 */

$pdo = Database::connection();

// Consulta otimizada para buscar a imagem de um produto real para os círculos de categorias
$stmtImg = $pdo->prepare("SELECT imagem_principal FROM produtos WHERE categoria = :cat AND ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' LIMIT 1");

// Consulta para buscar os Top 10 mais vendidos
$stmtTop = $pdo->query("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND preco_base > 0 AND imagem_principal IS NOT NULL AND imagem_principal <> '' ORDER BY id ASC LIMIT 10");
$maisVendidos = $stmtTop->fetchAll();

// Consulta para buscar os Top 10 Escritório
$stmtEscritorio = $pdo->prepare("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND preco_base > 0 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria IN ('CADERNOS E AGENDAS', 'ESCRITA') OR nome LIKE '%caderno%' OR nome LIKE '%caneta%' OR nome LIKE '%moleskine%' OR nome LIKE '%lapiseira%') ORDER BY id DESC LIMIT 10");
$stmtEscritorio->execute();
$topEscritorio = $stmtEscritorio->fetchAll();

// Consulta para buscar os Top 10 Dia-a-dia
$stmtDiaDia = $pdo->prepare("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND preco_base > 0 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria IN ('GARRAFAS E SQUEEZES', 'CANECAS E COPOS', 'CHAVEIROS E ACESSÓRIOS') OR nome LIKE '%copo%' OR nome LIKE '%garrafa%' OR nome LIKE '%squeeze%' OR nome LIKE '%caneca%' OR nome LIKE '%chaveiro%') ORDER BY id ASC LIMIT 10");
$stmtDiaDia->execute();
$topDiaDia = $stmtDiaDia->fetchAll();

// Consulta para buscar os Top 10 Produtividade
$stmtProdutividade = $pdo->prepare("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND preco_base > 0 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria IN ('BOLSAS E MOCHILAS', 'KITS E CONJUNTOS', 'TECNOLOGIA') OR nome LIKE '%mochila%' OR nome LIKE '%kit%' OR nome LIKE '%onboarding%' OR nome LIKE '%fone%' OR nome LIKE '%carregador%') ORDER BY id DESC LIMIT 10");
$stmtProdutividade->execute();
$topProdutividade = $stmtProdutividade->fetchAll();
?>

<!-- Category Circles with Real Database Photos -->
<?php if ($categorias): ?>
<section class="py-10 bg-white border-b border-surface-container/30 select-none">
    <div class="max-w-7xl mx-auto px-6 flex items-center justify-between gap-6 overflow-x-auto no-scrollbar">
        <?php foreach (array_slice($categorias, 0, 9) as $cat): 
            $nomeCat = $cat['categoria'];
            
            // Busca a imagem real do produto no banco para esta categoria
            $stmtImg->execute([':cat' => $nomeCat]);
            $imgCat = $stmtImg->fetchColumn();
            
            // Fallback caso não tenha imagem de produto
            if (!$imgCat) {
                $imgCat = 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?auto=format&fit=crop&q=80&w=200';
            }
        ?>
            <a href="<?= url('/catalogo?categoria=' . rawurlencode($nomeCat)) ?>" class="flex flex-col items-center gap-2.5 min-w-[90px] group cursor-pointer text-center">
                <div class="w-16 h-16 rounded-full overflow-hidden border border-surface-container shadow-sm group-hover:border-primary/30 group-hover:shadow-md active:scale-95 transition-all flex items-center justify-center bg-surface-container-low">
                    <img class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="<?= e($nomeCat) ?>" src="<?= e($imgCat) ?>" loading="lazy" />
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-700 group-hover:text-primary transition-colors max-w-[100px] truncate"><?= e($nomeCat) ?></span>
            </a>
        <?php endforeach; ?>
        
        <!-- Botão Ver Tudo -->
        <a href="<?= url('/catalogo') ?>" class="flex flex-col items-center gap-2.5 min-w-[90px] group cursor-pointer text-center">
            <div class="w-16 h-16 rounded-full bg-slate-900 text-white border border-slate-950 shadow-sm group-hover:shadow-md active:scale-95 transition-all flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl group-hover:rotate-45 transition-transform duration-300">grid_view</span>
            </div>
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-700 group-hover:text-slate-950">Ver Tudo</span>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- Automatic 5-Second Hero Slider -->
<section class="w-full mb-16 select-none relative overflow-hidden bg-slate-950" id="hero-slider" style="margin-top: -1px;">
    <div class="relative w-full rounded-none min-h-[500px] md:min-h-[540px] overflow-hidden">
        <!-- Slide 1: Mochilas -->
        <div class="hero-slide absolute inset-0 opacity-100 z-10 transition-all duration-1000 ease-in-out">
            <img class="absolute inset-0 w-full h-full object-cover" alt="Mochilas e Bolsas Corporativas" src="<?= asset('images/banner_mochilas.png') ?>"/>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
            <div class="absolute inset-0 flex items-center w-full">
                <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
                    <div class="max-w-xl text-white py-12">
                        <span class="bg-primary/20 text-sky-400 text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block">Mochilas & Bolsas</span>
                        <h1 class="text-4xl md:text-5xl font-black leading-none tracking-tighter mb-4">
                            Praticidade corporativa de alto padrão
                        </h1>
                        <p class="text-xs md:text-sm text-slate-300 mb-8 font-medium leading-relaxed max-w-md">
                            Mochilas executivas ergonômicas and malas de viagem personalizadas. O brinde ideal para acompanhar seu time em convenções, visitas e viagens de negócios.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <a href="<?= url('/catalogo?categoria=' . rawurlencode('BOLSAS E MOCHILAS')) ?>" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                                Ver Mochilas
                            </a>
                            <a href="<?= e(whatsappLink('Olá! Gostaria de solicitar um orçamento para mochilas executivas personalizadas.')) ?>" target="_blank" rel="noopener" class="bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-white/20 px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all inline-block">
                                Falar com Equipe
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 2: Canetas -->
        <div class="hero-slide absolute inset-0 opacity-0 z-0 transition-all duration-1000 ease-in-out">
            <img class="absolute inset-0 w-full h-full object-cover" alt="Canetas Corporativas Personalizadas" src="<?= asset('images/banner_canetas.png') ?>"/>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
            <div class="absolute inset-0 flex items-center w-full">
                <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
                    <div class="max-w-xl text-white py-12">
                        <span class="bg-primary/20 text-sky-400 text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block">Escrita Refinada</span>
                        <h1 class="text-4xl md:text-5xl font-black leading-none tracking-tighter mb-4">
                            A assinatura do sucesso da sua marca
                        </h1>
                        <p class="text-xs md:text-sm text-slate-300 mb-8 font-medium leading-relaxed max-w-md">
                            Canetas metálicas sofisticadas, lapiseiras e conjuntos executivos em estojos especiais. Brindes marcantes que transmitem precisão e profissionalismo.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <a href="<?= url('/catalogo?categoria=' . rawurlencode('ESCRITA')) ?>" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                                Ver Canetas
                            </a>
                            <a href="<?= e(whatsappLink('Olá! Quero solicitar um orçamento para canetas metálicas personalizadas.')) ?>" target="_blank" rel="noopener" class="bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-white/20 px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all inline-block">
                                Falar com Equipe
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 3: Garrafas -->
        <div class="hero-slide absolute inset-0 opacity-0 z-0 transition-all duration-1000 ease-in-out">
            <img class="absolute inset-0 w-full h-full object-cover" alt="Garrafas e Squeezes Térmicos" src="<?= asset('images/banner_garrafas.png') ?>"/>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
            <div class="absolute inset-0 flex items-center w-full">
                <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
                    <div class="max-w-xl text-white py-12">
                        <span class="bg-primary/20 text-sky-400 text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block">Hidratação & Estilo</span>
                        <h1 class="text-4xl md:text-5xl font-black leading-none tracking-tighter mb-4">
                            Sua marca presente no dia a dia
                        </h1>
                        <p class="text-xs md:text-sm text-slate-300 mb-8 font-medium leading-relaxed max-w-md">
                            Squeezes de inox e garrafas térmicas com parede dupla a vácuo. Design moderno e eficiência térmica que promovem a saúde e a sustentabilidade no escritório.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <a href="<?= url('/catalogo?categoria=' . rawurlencode('GARRAFAS E SQUEEZES')) ?>" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                                Ver Garrafas
                            </a>
                            <a href="<?= e(whatsappLink('Olá! Gostaria de cotar garrafas térmicas personalizadas para minha equipe.')) ?>" target="_blank" rel="noopener" class="bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-white/20 px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all inline-block">
                                Falar com Equipe
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 4: Onboarding -->
        <div class="hero-slide absolute inset-0 opacity-0 z-0 transition-all duration-1000 ease-in-out">
            <img class="absolute inset-0 w-full h-full object-cover" alt="Kits Onboarding de Boas-Vindas" src="<?= asset('images/banner_onboarding.png') ?>"/>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
            <div class="absolute inset-0 flex items-center w-full">
                <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
                    <div class="max-w-xl text-white py-12">
                        <span class="bg-primary/20 text-sky-400 text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block">Kits Corporativos</span>
                        <h1 class="text-4xl md:text-5xl font-black leading-none tracking-tighter mb-4">
                            Acolhimento marcante desde o dia um
                        </h1>
                        <p class="text-xs md:text-sm text-slate-300 mb-8 font-medium leading-relaxed max-w-md">
                            Kits onboarding de boas-vindas completos com caixas personalizadas. Garanta que novos colaboradores e parceiros sintam-se especiais e motivados.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <a href="<?= url('/catalogo?categoria=' . rawurlencode('KITS E CONJUNTOS')) ?>" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                                Ver Kits Onboarding
                            </a>
                            <a href="<?= e(whatsappLink('Olá! Gostaria de cotar caixas de kit onboarding de boas-vindas personalizadas.')) ?>" target="_blank" rel="noopener" class="bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-white/20 px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all inline-block">
                                Falar com Equipe
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 5: Moleskines -->
        <div class="hero-slide absolute inset-0 opacity-0 z-0 transition-all duration-1000 ease-in-out">
            <img class="absolute inset-0 w-full h-full object-cover" alt="Moleskines e Cadernos" src="<?= asset('images/banner_moleskine.png') ?>"/>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
            <div class="absolute inset-0 flex items-center w-full">
                <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
                    <div class="max-w-xl text-white py-12">
                        <span class="bg-primary/20 text-sky-400 text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block">Moleskines & Agendas</span>
                        <h1 class="text-4xl md:text-5xl font-black leading-none tracking-tighter mb-4">
                            Ideias e planejamentos registrados com elegância
                        </h1>
                        <p class="text-xs md:text-sm text-slate-300 mb-8 font-medium leading-relaxed max-w-md">
                            Cadernos estilo moleskine com capa de couro, pauta inteligente e fita marcadora. Presentes executivos que transmitem requinte e sofisticação.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <a href="<?= url('/catalogo?categoria=' . rawurlencode('CADERNOS E AGENDAS')) ?>" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                                Ver Moleskines
                            </a>
                            <a href="<?= e(whatsappLink('Olá! Gostaria de cotar cadernos e agendas estilo moleskine personalizados.')) ?>" target="_blank" rel="noopener" class="bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-white/20 px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all inline-block">
                                Falar com Equipe
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slider Bullets Indicators -->
        <div class="absolute bottom-6 right-8 z-30 flex gap-2.5">
            <button class="slider-bullet w-3 h-3 rounded-full bg-white transition-all duration-300" data-slide="0" aria-label="Ir para slide 1"></button>
            <button class="slider-bullet w-3 h-3 rounded-full bg-white/40 transition-all duration-300" data-slide="1" aria-label="Ir para slide 2"></button>
            <button class="slider-bullet w-3 h-3 rounded-full bg-white/40 transition-all duration-300" data-slide="2" aria-label="Ir para slide 3"></button>
            <button class="slider-bullet w-3 h-3 rounded-full bg-white/40 transition-all duration-300" data-slide="3" aria-label="Ir para slide 4"></button>
            <button class="slider-bullet w-3 h-3 rounded-full bg-white/40 transition-all duration-300" data-slide="4" aria-label="Ir para slide 5"></button>
        </div>
    </div>
</section>

<!-- Benefícios & Diferenciais Corporativos -->
<section class="max-w-7xl mx-auto px-6 mb-16 select-none">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-6 md:gap-4 py-8 px-6 bg-white border border-surface-container rounded-3xl shadow-sm text-center">
        <!-- Benefício 1: Segurança -->
        <div class="flex flex-col items-center gap-2 p-2">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">verified_user</span>
            </div>
            <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-800 mt-1">Entrega 100% Segura</h4>
            <p class="text-[9px] text-slate-400 font-semibold uppercase leading-tight max-w-[120px]">Logística monitorada e garantida</p>
        </div>
        <!-- Benefício 2: Pix -->
        <div class="flex flex-col items-center gap-2 p-2">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">qr_code_2</span>
            </div>
            <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-800 mt-1">Pagamento no Pix</h4>
            <p class="text-[9px] text-slate-400 font-semibold uppercase leading-tight max-w-[120px]">Faturamento rápido e descontos à vista</p>
        </div>
        <!-- Benefício 3: Cartão -->
        <div class="flex flex-col items-center gap-2 p-2">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">credit_card</span>
            </div>
            <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-800 mt-1">Pagamento no Cartão</h4>
            <p class="text-[9px] text-slate-400 font-semibold uppercase leading-tight max-w-[120px]">Parcelamento sob medida</p>
        </div>
        <!-- Benefício 4: Frete Grátis -->
        <div class="flex flex-col items-center gap-2 p-2">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">local_shipping</span>
            </div>
            <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-800 mt-1">Frete Grátis *</h4>
            <p class="text-[9px] text-slate-400 font-semibold uppercase leading-tight max-w-[120px]">Consulte condições para sua região</p>
        </div>
        <!-- Benefício 5: Atendimento -->
        <div class="flex flex-col items-center gap-2 p-2 col-span-2 md:col-span-1">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-xl">support_agent</span>
            </div>
            <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-800 mt-1">Fale com a nossa equipe</h4>
            <p class="text-[9px] text-slate-400 font-semibold uppercase leading-tight max-w-[130px]">Consultores dedicados do início ao fim</p>
        </div>
    </div>
</section>

<!-- Partner Brands (Marcas Parceiras) -->
<section class="bg-surface-container-low py-10 border-y border-surface-container/30 mb-16 select-none">
    <div class="max-w-7xl mx-auto px-6">
        <h4 class="text-center text-[9px] uppercase tracking-[0.2em] font-extrabold text-slate-400 mb-6">Marcas parceiras na nossa seleção inteligente</h4>
        <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16 opacity-60 grayscale hover:opacity-100 hover:grayscale-0 transition-all duration-500">
            <span class="text-lg font-black tracking-tighter text-slate-500">STANLEY</span>
            <span class="text-lg font-serif italic text-slate-500">Victorinox</span>
            <span class="text-lg font-bold tracking-widest text-slate-500">PARKER</span>
            <span class="text-lg font-black tracking-tighter text-slate-500">MOLESKINE</span>
            <span class="text-lg font-bold text-slate-500">JBL</span>
            <span class="text-lg font-black text-slate-500">TRAMONTINA</span>
        </div>
    </div>
</section>

<!-- Seção "Mais Vendidos" (Ranking Top 1 a 10) -->
<?php if ($maisVendidos): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Os Favoritos das Empresas</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Mais Vendidos da Novare</h2>
            <p class="text-slate-500 text-sm mt-1">Conheça o ranking Top 10 dos produtos mais procurados no faturamento corporativo.</p>
        </div>
        <!-- Setas de Navegação Lateral -->
        <div class="flex justify-center md:justify-end gap-2.5">
            <button type="button" onclick="document.getElementById('ranking-carousel').scrollBy({left: -320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Anterior">
                <span class="material-symbols-outlined text-base font-bold">arrow_back</span>
            </button>
            <button type="button" onclick="document.getElementById('ranking-carousel').scrollBy({left: 320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Próximo">
                <span class="material-symbols-outlined text-base font-bold">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- Carrossel de Mais Vendidos -->
    <div class="flex gap-6 overflow-x-auto py-4 px-2 no-scrollbar scroll-smooth snap-x snap-mandatory" id="ranking-carousel">
        <?php foreach ($maisVendidos as $i => $p): 
            $rank = $i + 1;
            $img = $p['imagem_principal'] ?? '';
            
            // Definição dos Badges do Ranking
            if ($rank === 1) {
                $badgeClass = 'bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 text-amber-950 font-black';
                $badgeText = '🏆 Top #1';
            } elseif ($rank === 2) {
                $badgeClass = 'bg-gradient-to-r from-slate-300 via-slate-200 to-slate-400 text-slate-900 font-black';
                $badgeText = '🥈 Top #2';
            } elseif ($rank === 3) {
                $badgeClass = 'bg-gradient-to-r from-amber-700 via-amber-600 to-amber-800 text-amber-50 font-black';
                $badgeText = '🥉 Top #3';
            } else {
                $badgeClass = 'bg-primary text-white font-bold';
                $badgeText = 'Top #' . $rank;
            }
        ?>
            <!-- Card de Ranking -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="min-w-[260px] sm:min-w-[280px] snap-start bg-white border border-surface-container rounded-2xl p-6 flex flex-col justify-between group cursor-pointer hover:border-primary/10 hover:shadow-xl active:scale-[0.98] transition-all shadow-sm relative overflow-hidden">
                <!-- Badge de Ranking -->
                <div class="absolute top-4 left-4 z-20 px-3.5 py-1.5 rounded-full text-[9px] uppercase tracking-wider shadow-sm select-none <?= $badgeClass ?>">
                    <?= $badgeText ?>
                </div>

                <div class="h-44 flex items-center justify-center mb-6 bg-surface-container-low/30 rounded-xl p-4 mt-4">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full max-w-full object-contain group-hover:scale-[1.06] group-hover:rotate-1 transition-transform duration-500" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" loading="lazy" />
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Corporativo') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm truncate group-hover:text-primary transition-colors mb-2"><?= e($p['nome']) ?></h4>
                    <div class="flex items-end justify-between border-t border-surface-container-low pt-3 mt-2">
                        <div>
                            <span class="text-[8px] text-slate-400 block uppercase tracking-wider font-semibold leading-none mb-1">a partir de</span>
                            <span class="text-primary font-black text-sm"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                        </div>
                        <span class="text-[9px] font-black uppercase text-primary tracking-widest flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                            Ver <span class="material-symbols-outlined text-[12px] font-bold">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Seção "Top Escritório" (Ranking Top 1 a 10) -->
<?php if ($topEscritorio): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Organização e Foco</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Escritório da Novare</h2>
            <p class="text-slate-500 text-sm mt-1">Os 10 brindes de papelaria, escrita e escritório mais requisitados por empresas.</p>
        </div>
        <!-- Setas de Navegação Lateral -->
        <div class="flex justify-center md:justify-end gap-2.5">
            <button type="button" onclick="document.getElementById('escritorio-carousel').scrollBy({left: -320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Anterior">
                <span class="material-symbols-outlined text-base font-bold">arrow_back</span>
            </button>
            <button type="button" onclick="document.getElementById('escritorio-carousel').scrollBy({left: 320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Próximo">
                <span class="material-symbols-outlined text-base font-bold">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- Carrossel de Top Escritório -->
    <div class="flex gap-6 overflow-x-auto py-4 px-2 no-scrollbar scroll-smooth snap-x snap-mandatory" id="escritorio-carousel">
        <?php foreach ($topEscritorio as $i => $p): 
            $rank = $i + 1;
            $img = $p['imagem_principal'] ?? '';
            
            // Definição dos Badges do Ranking
            if ($rank === 1) {
                $badgeClass = 'bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 text-amber-950 font-black';
                $badgeText = '🏆 Top #1';
            } elseif ($rank === 2) {
                $badgeClass = 'bg-gradient-to-r from-slate-300 via-slate-200 to-slate-400 text-slate-900 font-black';
                $badgeText = '🥈 Top #2';
            } elseif ($rank === 3) {
                $badgeClass = 'bg-gradient-to-r from-amber-700 via-amber-600 to-amber-800 text-amber-50 font-black';
                $badgeText = '🥉 Top #3';
            } else {
                $badgeClass = 'bg-primary text-white font-bold';
                $badgeText = 'Top #' . $rank;
            }
        ?>
            <!-- Card de Ranking -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="min-w-[260px] sm:min-w-[280px] snap-start bg-white border border-surface-container rounded-2xl p-6 flex flex-col justify-between group cursor-pointer hover:border-primary/10 hover:shadow-xl active:scale-[0.98] transition-all shadow-sm relative overflow-hidden">
                <!-- Badge de Ranking -->
                <div class="absolute top-4 left-4 z-20 px-3.5 py-1.5 rounded-full text-[9px] uppercase tracking-wider shadow-sm select-none <?= $badgeClass ?>">
                    <?= $badgeText ?>
                </div>

                <div class="h-44 flex items-center justify-center mb-6 bg-surface-container-low/30 rounded-xl p-4 mt-4">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full max-w-full object-contain group-hover:scale-[1.06] group-hover:rotate-1 transition-transform duration-500" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" loading="lazy" />
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Papelaria') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm truncate group-hover:text-primary transition-colors mb-2"><?= e($p['nome']) ?></h4>
                    <div class="flex items-end justify-between border-t border-surface-container-low pt-3 mt-2">
                        <div>
                            <span class="text-[8px] text-slate-400 block uppercase tracking-wider font-semibold leading-none mb-1">a partir de</span>
                            <span class="text-primary font-black text-sm"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                        </div>
                        <span class="text-[9px] font-black uppercase text-primary tracking-widest flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                            Ver <span class="material-symbols-outlined text-[12px] font-bold">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Seção "Top Dia-a-dia" (Ranking Top 1 a 10) -->
<?php if ($topDiaDia): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Utilidades Práticas</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Dia a Dia da Novare</h2>
            <p class="text-slate-500 text-sm mt-1">As 10 opções mais vendidas de hidratação, copos e utilidades para o cotidiano.</p>
        </div>
        <!-- Setas de Navegação Lateral -->
        <div class="flex justify-center md:justify-end gap-2.5">
            <button type="button" onclick="document.getElementById('diadia-carousel').scrollBy({left: -320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Anterior">
                <span class="material-symbols-outlined text-base font-bold">arrow_back</span>
            </button>
            <button type="button" onclick="document.getElementById('diadia-carousel').scrollBy({left: 320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Próximo">
                <span class="material-symbols-outlined text-base font-bold">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- Carrossel de Top Dia-a-dia -->
    <div class="flex gap-6 overflow-x-auto py-4 px-2 no-scrollbar scroll-smooth snap-x snap-mandatory" id="diadia-carousel">
        <?php foreach ($topDiaDia as $i => $p): 
            $rank = $i + 1;
            $img = $p['imagem_principal'] ?? '';
            
            // Definição dos Badges do Ranking
            if ($rank === 1) {
                $badgeClass = 'bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 text-amber-950 font-black';
                $badgeText = '🏆 Top #1';
            } elseif ($rank === 2) {
                $badgeClass = 'bg-gradient-to-r from-slate-300 via-slate-200 to-slate-400 text-slate-900 font-black';
                $badgeText = '🥈 Top #2';
            } elseif ($rank === 3) {
                $badgeClass = 'bg-gradient-to-r from-amber-700 via-amber-600 to-amber-800 text-amber-50 font-black';
                $badgeText = '🥉 Top #3';
            } else {
                $badgeClass = 'bg-primary text-white font-bold';
                $badgeText = 'Top #' . $rank;
            }
        ?>
            <!-- Card de Ranking -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="min-w-[260px] sm:min-w-[280px] snap-start bg-white border border-surface-container rounded-2xl p-6 flex flex-col justify-between group cursor-pointer hover:border-primary/10 hover:shadow-xl active:scale-[0.98] transition-all shadow-sm relative overflow-hidden">
                <!-- Badge de Ranking -->
                <div class="absolute top-4 left-4 z-20 px-3.5 py-1.5 rounded-full text-[9px] uppercase tracking-wider shadow-sm select-none <?= $badgeClass ?>">
                    <?= $badgeText ?>
                </div>

                <div class="h-44 flex items-center justify-center mb-6 bg-surface-container-low/30 rounded-xl p-4 mt-4">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full max-w-full object-contain group-hover:scale-[1.06] group-hover:rotate-1 transition-transform duration-500" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" loading="lazy" />
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Dia a Dia') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm truncate group-hover:text-primary transition-colors mb-2"><?= e($p['nome']) ?></h4>
                    <div class="flex items-end justify-between border-t border-surface-container-low pt-3 mt-2">
                        <div>
                            <span class="text-[8px] text-slate-400 block uppercase tracking-wider font-semibold leading-none mb-1">a partir de</span>
                            <span class="text-primary font-black text-sm"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                        </div>
                        <span class="text-[9px] font-black uppercase text-primary tracking-widest flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                            Ver <span class="material-symbols-outlined text-[12px] font-bold">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Seção "Top Produtividade" (Ranking Top 1 a 10) -->
<?php if ($topProdutividade): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Performance e Viagem</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Produtividade da Novare</h2>
            <p class="text-slate-500 text-sm mt-1">Os 10 itens de alta performance: mochilas executivas, tecnologia e kits completos.</p>
        </div>
        <!-- Setas de Navegação Lateral -->
        <div class="flex justify-center md:justify-end gap-2.5">
            <button type="button" onclick="document.getElementById('produtividade-carousel').scrollBy({left: -320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Anterior">
                <span class="material-symbols-outlined text-base font-bold">arrow_back</span>
            </button>
            <button type="button" onclick="document.getElementById('produtividade-carousel').scrollBy({left: 320, behavior: 'smooth'})" class="w-10 h-10 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white" aria-label="Próximo">
                <span class="material-symbols-outlined text-base font-bold">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- Carrossel de Top Produtividade -->
    <div class="flex gap-6 overflow-x-auto py-4 px-2 no-scrollbar scroll-smooth snap-x snap-mandatory" id="produtividade-carousel">
        <?php foreach ($topProdutividade as $i => $p): 
            $rank = $i + 1;
            $img = $p['imagem_principal'] ?? '';
            
            // Definição dos Badges do Ranking
            if ($rank === 1) {
                $badgeClass = 'bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500 text-amber-950 font-black';
                $badgeText = '🏆 Top #1';
            } elseif ($rank === 2) {
                $badgeClass = 'bg-gradient-to-r from-slate-300 via-slate-200 to-slate-400 text-slate-900 font-black';
                $badgeText = '🥈 Top #2';
            } elseif ($rank === 3) {
                $badgeClass = 'bg-gradient-to-r from-amber-700 via-amber-600 to-amber-800 text-amber-50 font-black';
                $badgeText = '🥉 Top #3';
            } else {
                $badgeClass = 'bg-primary text-white font-bold';
                $badgeText = 'Top #' . $rank;
            }
        ?>
            <!-- Card de Ranking -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="min-w-[260px] sm:min-w-[280px] snap-start bg-white border border-surface-container rounded-2xl p-6 flex flex-col justify-between group cursor-pointer hover:border-primary/10 hover:shadow-xl active:scale-[0.98] transition-all shadow-sm relative overflow-hidden">
                <!-- Badge de Ranking -->
                <div class="absolute top-4 left-4 z-20 px-3.5 py-1.5 rounded-full text-[9px] uppercase tracking-wider shadow-sm select-none <?= $badgeClass ?>">
                    <?= $badgeText ?>
                </div>

                <div class="h-44 flex items-center justify-center mb-6 bg-surface-container-low/30 rounded-xl p-4 mt-4">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full max-w-full object-contain group-hover:scale-[1.06] group-hover:rotate-1 transition-transform duration-500" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" loading="lazy" />
                    <?php endif; ?>
                </div>
                <div>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Produtividade') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm truncate group-hover:text-primary transition-colors mb-2"><?= e($p['nome']) ?></h4>
                    <div class="flex items-end justify-between border-t border-surface-container-low pt-3 mt-2">
                        <div>
                            <span class="text-[8px] text-slate-400 block uppercase tracking-wider font-semibold leading-none mb-1">a partir de</span>
                            <span class="text-primary font-black text-sm"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                        </div>
                        <span class="text-[9px] font-black uppercase text-primary tracking-widest flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                            Ver <span class="material-symbols-outlined text-[12px] font-bold">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Destaques Bento (Primeiros 4) -->
<?php if ($destaques): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16">
    <div class="mb-10 text-center md:text-left">
        <span class="text-primary font-bold uppercase tracking-wider text-xs">Recomendados para sua Empresa</span>
        <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Nossa seleção recomendada</h2>
        <p class="text-slate-500 text-sm mt-1">Soluções personalizadas em brindes práticos e inovadores.</p>
    </div>

    <!-- Bento Grid (First 4 highlights) -->
    <div class="grid grid-cols-1 md:grid-cols-4 grid-rows-none md:grid-rows-2 gap-6 h-auto md:h-[600px] mb-16">
        <?php if (isset($destaques[0])): 
            $p = $destaques[0];
            $img = $p['imagem_principal'] ?? '';
        ?>
            <!-- Large Featured -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="md:col-span-2 md:row-span-2 bg-white rounded-3xl p-8 flex flex-col justify-between group cursor-pointer border border-surface-container hover:border-primary/10 hover:shadow-xl transition-all shadow-sm">
                <div>
                    <span class="bg-primary-container text-on-primary-container px-3.5 py-1.5 rounded-full text-[9px] font-bold uppercase tracking-wider mb-4 inline-block shadow-sm">Destaque Principal</span>
                    <h3 class="text-2xl font-black text-on-surface tracking-tighter group-hover:text-primary transition-colors leading-tight"><?= e($p['nome']) ?></h3>
                    <p class="text-slate-500 text-xs mt-1 font-medium"><?= e($p['categoria'] ?? 'Corporativo') ?></p>
                </div>
                <div class="my-6 flex justify-center items-center h-48">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-500" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" />
                    <?php endif; ?>
                </div>
                <div class="flex justify-between items-end">
                    <div>
                        <span class="text-slate-400 text-[10px] block uppercase tracking-wider font-semibold">a partir de</span>
                        <span class="text-primary font-bold text-lg"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                    </div>
                    <button class="text-primary font-bold uppercase text-[10px] tracking-widest flex items-center gap-1.5 group-hover:translate-x-1 transition-transform">
                        Detalhes <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($destaques[1])): 
            $p = $destaques[1];
            $img = $p['imagem_principal'] ?? '';
        ?>
            <!-- Small Featured 1 (horizontal card) -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="md:col-span-2 bg-secondary-container/10 rounded-3xl p-6 flex items-center justify-between gap-6 group cursor-pointer border border-transparent hover:border-primary/10 hover:shadow-lg transition-all shadow-sm">
                <div class="flex-1">
                    <span class="bg-secondary text-white px-3 py-1 rounded-full text-[8px] font-bold uppercase tracking-wider mb-3 inline-block shadow-sm">Novidade</span>
                    <h4 class="text-lg font-black text-on-surface group-hover:text-primary transition-colors line-clamp-2 leading-snug"><?= e($p['nome']) ?></h4>
                    <span class="text-xs text-primary font-extrabold block mt-2"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                    <span class="mt-4 text-[10px] font-bold uppercase tracking-wider text-primary inline-block underline underline-offset-4">Ver Detalhes</span>
                </div>
                <div class="w-32 h-32 flex-shrink-0 flex items-center justify-center bg-white rounded-2xl p-3 shadow-sm">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full max-w-full object-contain group-hover:rotate-3 transition-transform duration-300" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" />
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($destaques[2])): 
            $p = $destaques[2];
            $img = $p['imagem_principal'] ?? '';
        ?>
            <!-- Small Featured 2 -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="md:col-span-1 bg-white border border-surface-container rounded-3xl p-6 flex flex-col justify-between group cursor-pointer hover:shadow-lg transition-all shadow-sm">
                <div class="h-28 flex items-center justify-center mb-4">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full object-contain group-hover:scale-105 transition-transform" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" />
                    <?php endif; ?>
                </div>
                <div>
                    <h4 class="font-extrabold text-on-surface mt-2 text-xs truncate group-hover:text-primary transition-colors"><?= e($p['nome']) ?></h4>
                    <span class="text-[10px] text-slate-400 mt-1 block uppercase tracking-wider font-semibold"><?= e($p['categoria'] ?? 'Geral') ?></span>
                    <span class="text-xs text-primary font-semibold mt-1 block"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($destaques[3])): 
            $p = $destaques[3];
            $img = $p['imagem_principal'] ?? '';
        ?>
            <!-- Small Featured 3 -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="md:col-span-1 bg-white border border-surface-container rounded-3xl p-6 flex flex-col justify-between group cursor-pointer hover:shadow-lg transition-all shadow-sm">
                <div class="h-28 flex items-center justify-center mb-4">
                    <?php if ($img !== ''): ?>
                        <img class="max-h-full object-contain group-hover:scale-105 transition-transform" alt="<?= e($p['nome']) ?>" src="<?= e($img) ?>" />
                    <?php endif; ?>
                </div>
                <div>
                    <h4 class="font-extrabold text-on-surface mt-2 text-xs truncate group-hover:text-primary transition-colors"><?= e($p['nome']) ?></h4>
                    <span class="text-[10px] text-slate-400 mt-1 block uppercase tracking-wider font-semibold"><?= e($p['categoria'] ?? 'Geral') ?></span>
                    <span class="text-xs text-primary font-semibold mt-1 block"><?= e(preco($p['preco_base'] ?? 0)) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Grade 3x2 de Categorias Humanizadas -->
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 select-none">
    <div class="mb-10 text-center md:text-left">
        <span class="text-primary font-bold uppercase tracking-wider text-xs">Cenários Reais & Utilidades</span>
        <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Navegue Pelas Nossas Categorias</h2>
        <p class="text-slate-500 text-sm mt-1">Encontre brindes corporativos perfeitos contextualizados em situações cotidianas da empresa.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Categoria 1: Moleskines -->
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('CADERNOS E AGENDAS')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
            <div class="absolute inset-0 bg-gradient-to-t from-[#006590]/50 via-[#006590]/15 to-transparent z-10"></div>
            <img class="w-full h-full object-cover group-hover:scale-[1.05] transition-transform duration-700" alt="Moleskines e Agendas" src="<?= asset('images/cat_moleskine.png') ?>" loading="lazy" />
            <div class="absolute bottom-6 left-6 right-6 z-20 text-white">
                <span class="text-[8px] bg-white/10 px-3 py-1 rounded-none uppercase tracking-widest font-bold inline-block mb-2 border border-white/10">Escritório & Foco</span>
                <h4 class="text-xl font-extrabold tracking-tight leading-none mb-1">Cadernos & Moleskines</h4>
                <p class="text-[9px] text-white/80 font-medium uppercase tracking-wider mt-1.5 flex items-center gap-1">Ideias registradas com elegância executiva <span class="text-xs">&rarr;</span></p>
            </div>
        </div>

        <!-- Categoria 2: Garrafas -->
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('GARRAFAS E SQUEEZES')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
            <div class="absolute inset-0 bg-gradient-to-t from-[#006590]/50 via-[#006590]/15 to-transparent z-10"></div>
            <img class="w-full h-full object-cover group-hover:scale-[1.05] transition-transform duration-700" alt="Garrafas Térmicas e Squeezes" src="<?= asset('images/cat_garrafas.png') ?>" loading="lazy" />
            <div class="absolute bottom-6 left-6 right-6 z-20 text-white">
                <span class="text-[8px] bg-white/10 px-3 py-1 rounded-none uppercase tracking-widest font-bold inline-block mb-2 border border-white/10">Dia a Dia & Estilo</span>
                <h4 class="text-xl font-extrabold tracking-tight leading-none mb-1">Garrafas & Squeezes</h4>
                <p class="text-[9px] text-white/80 font-medium uppercase tracking-wider mt-1.5 flex items-center gap-1">Hidratação inteligente para sua equipe <span class="text-xs">&rarr;</span></p>
            </div>
        </div>

        <!-- Categoria 3: Mochilas -->
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('BOLSAS E MOCHILAS')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
            <div class="absolute inset-0 bg-gradient-to-t from-[#006590]/50 via-[#006590]/15 to-transparent z-10"></div>
            <img class="w-full h-full object-cover group-hover:scale-[1.05] transition-transform duration-700" alt="Mochilas Executivas" src="<?= asset('images/cat_mochilas.png') ?>" loading="lazy" />
            <div class="absolute bottom-6 left-6 right-6 z-20 text-white">
                <span class="text-[8px] bg-white/10 px-3 py-1 rounded-none uppercase tracking-widest font-bold inline-block mb-2 border border-white/10">Mobilidade & Viagem</span>
                <h4 class="text-xl font-extrabold tracking-tight leading-none mb-1">Mochilas & Malas</h4>
                <p class="text-[9px] text-white/80 font-medium uppercase tracking-wider mt-1.5 flex items-center gap-1">Praticidade e conforto executivo em trânsito <span class="text-xs">&rarr;</span></p>
            </div>
        </div>

        <!-- Categoria 4: Kit Onboarding -->
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('KITS E CONJUNTOS')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
            <div class="absolute inset-0 bg-gradient-to-t from-[#006590]/50 via-[#006590]/15 to-transparent z-10"></div>
            <img class="w-full h-full object-cover group-hover:scale-[1.05] transition-transform duration-700" alt="Kit Onboarding Boas-Vindas" src="<?= asset('images/cat_onboarding.png') ?>" loading="lazy" />
            <div class="absolute bottom-6 left-6 right-6 z-20 text-white">
                <span class="text-[8px] bg-white/10 px-3 py-1 rounded-none uppercase tracking-widest font-bold inline-block mb-2 border border-white/10">Endomarketing & Acolhimento</span>
                <h4 class="text-xl font-extrabold tracking-tight leading-none mb-1">Kits de Onboarding</h4>
                <p class="text-[9px] text-white/80 font-medium uppercase tracking-wider mt-1.5 flex items-center gap-1">Acolha novos talentos com experiência única <span class="text-xs">&rarr;</span></p>
            </div>
        </div>

        <!-- Categoria 5: Canecas -->
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('CANECAS E COPOS')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
            <div class="absolute inset-0 bg-gradient-to-t from-[#006590]/50 via-[#006590]/15 to-transparent z-10"></div>
            <img class="w-full h-full object-cover group-hover:scale-[1.05] transition-transform duration-700" alt="Canecas e Copos Personalizados" src="<?= asset('images/cat_canecas.png') ?>" loading="lazy" />
            <div class="absolute bottom-6 left-6 right-6 z-20 text-white">
                <span class="text-[8px] bg-white/10 px-3 py-1 rounded-none uppercase tracking-widest font-bold inline-block mb-2 border border-white/10">Estação de Trabalho & Conforto</span>
                <h4 class="text-xl font-extrabold tracking-tight leading-none mb-1">Canecas & Copos</h4>
                <p class="text-[9px] text-white/80 font-medium uppercase tracking-wider mt-1.5 flex items-center gap-1">Sua marca presente nos momentos de pausa <span class="text-xs">&rarr;</span></p>
            </div>
        </div>

        <!-- Categoria 6: Canetas (Escrita) -->
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('ESCRITA')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
            <div class="absolute inset-0 bg-gradient-to-t from-[#006590]/50 via-[#006590]/15 to-transparent z-10"></div>
            <img class="w-full h-full object-cover group-hover:scale-[1.05] transition-transform duration-700" alt="Canetas de Luxo Executivas" src="<?= asset('images/cat_canetas.png') ?>" loading="lazy" />
            <div class="absolute bottom-6 left-6 right-6 z-20 text-white">
                <span class="text-[8px] bg-white/10 px-3 py-1 rounded-none uppercase tracking-widest font-bold inline-block mb-2 border border-white/10">Escrita Executiva & Assinaturas</span>
                <h4 class="text-xl font-extrabold tracking-tight leading-none mb-1">Canetas & Lapiseiras</h4>
                <p class="text-[9px] text-white/80 font-medium uppercase tracking-wider mt-1.5 flex items-center gap-1">Elegância e precisão para assinar momentos <span class="text-xs">&rarr;</span></p>
            </div>
        </div>
    </div>
</section>

<!-- Marcas que Confiam em Nós (Carrossel Infinito) -->
<style>
@keyframes infinite-scroll {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
}
.animate-infinite-scroll {
    display: flex;
    animation: infinite-scroll 32s linear infinite;
    width: max-content;
}
.animate-infinite-scroll:hover {
    animation-play-state: paused;
}
</style>
<section class="w-full bg-white py-14 border-t border-b border-surface-container/20 overflow-hidden select-none relative mb-16">
    <div class="max-w-7xl mx-auto px-6 mb-10 text-center">
        <h3 class="text-sm uppercase tracking-[0.25em] font-black text-slate-800">Empresas que confiam em nós</h3>
    </div>
    <!-- Contêiner do Carrossel com Máscara Gradiente nas Laterais -->
    <div class="relative w-full flex overflow-hidden py-4" style="mask-image: linear-gradient(to right, transparent, white 15%, white 85%, transparent); -webkit-mask-image: linear-gradient(to right, transparent, white 15%, white 85%, transparent);">
        <div class="flex gap-28 items-center animate-infinite-scroll pr-28">
            <!-- Baker Hughes -->
            <div class="flex items-center gap-2.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-sm font-black tracking-tighter uppercase">Baker Hughes</span>
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                    <path d="M4 4h4l8 8-8 8H4l8-8zM10 4h4l6 6-6 6h-4l6-6z"/>
                </svg>
            </div>
            <!-- União Química -->
            <div class="flex items-center gap-2.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-12S17.52 2 12 2zm1 15h-2v-4H7v-2h4V7h2v4h4v2h-4v4z"/>
                </svg>
                <div class="flex flex-col leading-none">
                    <span class="text-xs font-black tracking-tight uppercase">União Química</span>
                    <span class="text-[6px] tracking-widest uppercase font-bold text-slate-400">Hospitalar</span>
                </div>
            </div>
            <!-- Sesc -->
            <div class="flex items-center gap-2 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-lg font-black italic tracking-tighter text-slate-500 uppercase leading-none">Sesc</span>
                <svg class="w-7.5 h-4 fill-current" viewBox="0 0 24 12" style="width: 30px; height: 16px;">
                    <path d="M0 6c4-6 8-6 12 0s8 6 12 0v2c-4 6-8 6-12 0S4 2 0 8z"/>
                </svg>
            </div>
            <!-- LABORSAN -->
            <div class="flex items-center gap-2 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <div class="flex flex-col leading-none text-right">
                    <span class="text-xs font-black tracking-tighter uppercase">LABORSAN</span>
                    <span class="text-[6px] tracking-widest uppercase font-black text-slate-400">AGRO</span>
                </div>
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-12S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
            </div>
            <!-- Cebrace -->
            <div class="flex items-center text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300 border border-slate-200 px-3.5 py-1.5 rounded bg-slate-50/50">
                <span class="text-sm font-black tracking-tight lowercase">cebrace</span>
                <svg class="w-4.5 h-4.5 ml-1.5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    <path d="M2 12h20"/>
                </svg>
            </div>
            <!-- Nestlé -->
            <div class="flex items-center gap-1.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-sm font-extrabold tracking-tight uppercase">Nestlé</span>
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                    <path d="M12 3c-1.2 0-2.4.4-3.4 1.1-.5-.3-1.1-.5-1.7-.5-1.9 0-3.4 1.5-3.4 3.4 0 1.2.6 2.2 1.5 2.8C4.4 10.8 4 12.1 4 13.5c0 3.6 2.9 6.5 6.5 6.5 2 0 3.8-1 4.9-2.5 1.1 1.5 2.9 2.5 4.9 2.5 3.6 0 6.5-2.9 6.5-6.5 0-1.4-.4-2.7-1.1-3.7.9-.6 1.5-1.6 1.5-2.8 0-1.9-1.5-3.4-3.4-3.4-.6 0-1.2.2-1.7.5C21.1 3.4 19.9 3 18.7 3c-2.3 0-4.3 1.3-5.3 3.2-1-1.9-3-3.2-5.3-3.2z"/>
                </svg>
            </div>
            <!-- Stanley -->
            <div class="flex items-center gap-1.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-sm font-black tracking-widest uppercase">STANLEY</span>
                <svg class="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <path d="M12 2L2 22h20L12 2zm0 4l6.5 13h-13L12 6z"/>
                </svg>
            </div>
            <!-- Moleskine -->
            <div class="flex items-center text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-xs font-black tracking-[0.25em] uppercase">MOLESKINE</span>
            </div>
            <!-- Tramontina -->
            <div class="flex items-center gap-2 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-xs font-black tracking-widest uppercase font-serif">TRAMONTINA</span>
                <svg class="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
        </div>
        <!-- Duplicação idêntica para loop contínuo impecável -->
        <div class="flex gap-28 items-center animate-infinite-scroll pr-28" aria-hidden="true">
            <!-- Baker Hughes -->
            <div class="flex items-center gap-2.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-sm font-black tracking-tighter uppercase">Baker Hughes</span>
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                    <path d="M4 4h4l8 8-8 8H4l8-8zM10 4h4l6 6-6 6h-4l6-6z"/>
                </svg>
            </div>
            <!-- União Química -->
            <div class="flex items-center gap-2.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-12S17.52 2 12 2zm1 15h-2v-4H7v-2h4V7h2v4h4v2h-4v4z"/>
                </svg>
                <div class="flex flex-col leading-none">
                    <span class="text-xs font-black tracking-tight uppercase">União Química</span>
                    <span class="text-[6px] tracking-widest uppercase font-bold text-slate-400">Hospitalar</span>
                </div>
            </div>
            <!-- Sesc -->
            <div class="flex items-center gap-2 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-lg font-black italic tracking-tighter text-slate-500 uppercase leading-none">Sesc</span>
                <svg class="w-7.5 h-4 fill-current" viewBox="0 0 24 12" style="width: 30px; height: 16px;">
                    <path d="M0 6c4-6 8-6 12 0s8 6 12 0v2c-4 6-8 6-12 0S4 2 0 8z"/>
                </svg>
            </div>
            <!-- LABORSAN -->
            <div class="flex items-center gap-2 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <div class="flex flex-col leading-none text-right">
                    <span class="text-xs font-black tracking-tighter uppercase">LABORSAN</span>
                    <span class="text-[6px] tracking-widest uppercase font-black text-slate-400">AGRO</span>
                </div>
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-12S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
            </div>
            <!-- Cebrace -->
            <div class="flex items-center text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300 border border-slate-200 px-3.5 py-1.5 rounded bg-slate-50/50">
                <span class="text-sm font-black tracking-tight lowercase">cebrace</span>
                <svg class="w-4.5 h-4.5 ml-1.5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    <path d="M2 12h20"/>
                </svg>
            </div>
            <!-- Nestlé -->
            <div class="flex items-center gap-1.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-sm font-extrabold tracking-tight uppercase">Nestlé</span>
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" style="width: 20px; height: 20px;">
                    <path d="M12 3c-1.2 0-2.4.4-3.4 1.1-.5-.3-1.1-.5-1.7-.5-1.9 0-3.4 1.5-3.4 3.4 0 1.2.6 2.2 1.5 2.8C4.4 10.8 4 12.1 4 13.5c0 3.6 2.9 6.5 6.5 6.5 2 0 3.8-1 4.9-2.5 1.1 1.5 2.9 2.5 4.9 2.5 3.6 0 6.5-2.9 6.5-6.5 0-1.4-.4-2.7-1.1-3.7.9-.6 1.5-1.6 1.5-2.8 0-1.9-1.5-3.4-3.4-3.4-.6 0-1.2.2-1.7.5C21.1 3.4 19.9 3 18.7 3c-2.3 0-4.3 1.3-5.3 3.2-1-1.9-3-3.2-5.3-3.2z"/>
                </svg>
            </div>
            <!-- Stanley -->
            <div class="flex items-center gap-1.5 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-sm font-black tracking-widest uppercase">STANLEY</span>
                <svg class="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <path d="M12 2L2 22h20L12 2zm0 4l6.5 13h-13L12 6z"/>
                </svg>
            </div>
            <!-- Moleskine -->
            <div class="flex items-center text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-xs font-black tracking-[0.25em] uppercase">MOLESKINE</span>
            </div>
            <!-- Tramontina -->
            <div class="flex items-center gap-2 text-slate-400 hover:text-slate-800 hover:scale-105 transition-all duration-300">
                <span class="text-xs font-black tracking-widest uppercase font-serif">TRAMONTINA</span>
                <svg class="w-4.5 h-4.5 fill-current" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
        </div>
    </div>
</section>

<!-- Segundo Banner Corporativo de Fechamento (Antes dos Depoimentos) -->
<section class="w-full mb-16 select-none relative overflow-hidden bg-slate-950 min-h-[380px] flex items-center">
    <div class="absolute inset-0 z-0">
        <img class="w-full h-full object-cover" alt="Brindes e Presentes Corporativos Personalizados" src="<?= asset('images/banner_presentes.png') ?>"/>
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/65 to-transparent"></div>
    </div>
    <div class="relative z-10 w-full">
        <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
            <div class="max-w-xl py-12 text-white">
                <span class="bg-primary/20 text-sky-400 text-[8px] font-black uppercase tracking-[0.25em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block w-fit">Presentes Corporativos</span>
                <h2 class="text-3xl md:text-4xl font-black leading-tight tracking-tighter mb-4">
                    Muito mais que brinde, é presente com a cara da sua marca
                </h2>
                <p class="text-xs text-slate-300 mb-8 font-medium leading-relaxed max-w-md">
                    Personalização refinada sob medida para datas comemorativas, convenções, feiras corporativas e campanhas de endomarketing. Atendemos soluções econômicas de grande volume e brindes executivos com o mesmo rigor.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="<?= e(whatsappLink('Olá! Gostaria de cotar presentes corporativos personalizados com a minha logo.')) ?>" target="_blank" rel="noopener" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                        Fazer Briefing no WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials / Depoimentos (Sem Curadoria) -->
<section class="max-w-7xl mx-auto px-6 py-10 border-t border-surface-container/50 select-none">
    <div class="text-center mb-12">
        <span class="text-primary font-bold uppercase tracking-wider text-xs">Casos de Sucesso</span>
        <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Por que escolher a Novare Brindes?</h2>
        <p class="text-slate-500 text-sm mt-1">Quem já comprou kits e brindes personalizados conosco aprova a qualidade e a pontualidade técnica.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="bg-white border border-surface-container rounded-3xl p-8 shadow-sm hover:shadow-md transition-shadow">
            <div class="text-sky-500 mb-4 flex gap-0.5">
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
            </div>
            <p class="text-slate-600 text-xs leading-relaxed mb-6 italic">“Montaram nosso kit de onboarding com identidade impecável e entregaram no prazo apertado da convenção.”</p>
            <div class="text-[9px] font-black text-slate-800 uppercase tracking-widest border-t border-slate-100 pt-4 block">— Gerência de RH, multinacional de tecnologia</div>
        </div>
        <div class="bg-white border border-surface-container rounded-3xl p-8 shadow-sm hover:shadow-md transition-shadow">
            <div class="text-sky-500 mb-4 flex gap-0.5">
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
            </div>
            <p class="text-slate-600 text-xs leading-relaxed mb-6 italic">“Atendimento ágil e consultivo: sugeriram alternativas econômicas dentro do orçamento mantendo alta durabilidade.”</p>
            <div class="text-[9px] font-black text-slate-800 uppercase tracking-widest border-t border-slate-100 pt-4 block">— Marketing, rede varejista</div>
        </div>
        <div class="bg-white border border-surface-container rounded-3xl p-8 shadow-sm hover:shadow-md transition-shadow">
            <div class="text-sky-500 mb-4 flex gap-0.5">
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
                <span class="material-symbols-outlined text-2xl font-fill">star</span>
            </div>
            <p class="text-slate-600 text-xs leading-relaxed mb-6 italic">“Catálogo amplo e atendimento altamente qualificado e ágil. Viramos clientes recorrentes para todos os eventos.”</p>
            <div class="text-[9px] font-black text-slate-800 uppercase tracking-widest border-t border-slate-100 pt-4 block">— Eventos, indústria farmacêutica</div>
        </div>
    </div>
</section>


<!-- JavaScript do Slider Automático de 5 Segundos e Rolagem por Mouse Wheel -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Lógica do Slider
    const slides = document.querySelectorAll('.hero-slide');
    const bullets = document.querySelectorAll('.slider-bullet');
    let current = 0;
    let timer = null;

    if (slides.length > 0) {
        function showSlide(idx) {
            slides[current].classList.remove('opacity-100', 'z-10');
            slides[current].classList.add('opacity-0', 'z-0');
            bullets[current].classList.remove('bg-white');
            bullets[current].classList.add('bg-white/40');

            current = idx;

            slides[current].classList.remove('opacity-0', 'z-0');
            slides[current].classList.add('opacity-100', 'z-10');
            bullets[current].classList.remove('bg-white/40');
            bullets[current].classList.add('bg-white');
        }

        function nextSlide() {
            let next = (current + 1) % slides.length;
            showSlide(next);
        }

        function startTimer() {
            stopTimer();
            timer = setInterval(nextSlide, 5000);
        }

        function stopTimer() {
            if (timer) clearInterval(timer);
        }

        bullets.forEach(function (b, idx) {
            b.addEventListener('click', function () {
                showSlide(idx);
                startTimer(); // Reinicia o timer ao interagir
            });
        });

        // Pausa no hover para leitura do usuário
        const container = document.getElementById('hero-slider');
        if (container) {
            container.addEventListener('mouseenter', stopTimer);
            container.addEventListener('mouseleave', startTimer);
        }

        startTimer();
    }

});
</script>
