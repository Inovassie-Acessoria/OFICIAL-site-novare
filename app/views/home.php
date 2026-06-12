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

// Top 10 Canetas (Escrita)
$stmtCanetas = $pdo->query("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria = 'CANETAS' OR categoria = 'ESCRITA' OR nome LIKE '%caneta%' OR nome LIKE '%lapiseira%' OR nome LIKE '%roller%') ORDER BY id ASC LIMIT 10");
$topCanetas = $stmtCanetas->fetchAll();

// Top 10 Cadernos / Agendas / Moleskine
$stmtCadernos = $pdo->query("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria = 'MOLESKINE & CADERNOS' OR categoria = 'CADERNOS E AGENDAS' OR nome LIKE '%caderno%' OR nome LIKE '%caderneta%' OR nome LIKE '%agenda%' OR nome LIKE '%moleskine%' OR nome LIKE '%planner%') ORDER BY id ASC LIMIT 10");
$topCadernos = $stmtCadernos->fetchAll();

// Top 10 Garrafas
$stmtGarrafas = $pdo->query("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria = 'GARRAFAS E SQUEEZES' OR nome LIKE '%garrafa%' OR nome LIKE '%squeeze%') ORDER BY id ASC LIMIT 10");
$topGarrafas = $stmtGarrafas->fetchAll();

// Top 10 Mochilas
$stmtMochilas = $pdo->query("SELECT sku_pai, nome, preco_base, imagem_principal, categoria FROM produtos WHERE ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' AND (categoria = 'BOLSAS E MOCHILAS' OR nome LIKE '%mochila%') ORDER BY id ASC LIMIT 10");
$topMochilas = $stmtMochilas->fetchAll();

// Ranking MANUAL do painel admin tem prioridade (arrastar/soltar por SKU).
// Se houver SKUs fixados, eles substituem a ordenação automática acima.
$repoTops = ProductRepository::create();
foreach (['top_canetas' => 'topCanetas', 'top_cadernos' => 'topCadernos', 'top_garrafas' => 'topGarrafas', 'top_mochilas' => 'topMochilas'] as $chaveCfg => $var) {
    $skusManuais = SiteContent::topSkus($chaveCfg);
    if ($skusManuais) {
        $manual = $repoTops->porSkus($skusManuais);
        if ($manual) {
            $$var = $manual;
        }
    }
}

// Mapeamento e ordenação explícita das categorias na Home
$ordemCategorias = [
    'CANETAS', 
    'MOLESKINE & CADERNOS', 
    'BOLSAS E MOCHILAS', 
    'GARRAFAS E SQUEEZES', 
    'CANECAS E COPOS', 
    'TECNOLOGIA', 
    'MOUSE PADS',
    'CARTEIRAS',
    'DIVERSOS'
];

$categoriasOrdenadas = [];
if ($categorias) {
    $categoriasMap = [];
    foreach ($categorias as $cat) {
        $categoriasMap[strtoupper($cat['categoria'])] = $cat;
    }
    // Adiciona na ordem solicitada
    foreach ($ordemCategorias as $nomeOrdem) {
        if (isset($categoriasMap[$nomeOrdem])) {
            $categoriasOrdenadas[] = $categoriasMap[$nomeOrdem];
        } else {
            $categoriasOrdenadas[] = ['categoria' => $nomeOrdem];
        }
    }
    // Adiciona o restante se houver
    foreach ($categoriasMap as $nomeMap => $cat) {
        if (!in_array($nomeMap, $ordemCategorias)) {
            $categoriasOrdenadas[] = $cat;
        }
    }
}
?>

<!-- Category Circles with Real Database Photos -->
<?php if ($categoriasOrdenadas): ?>
<section class="py-10 bg-white border-b border-surface-container/30 select-none">
    <div class="max-w-7xl mx-auto px-6 flex items-center justify-between gap-6 overflow-x-auto no-scrollbar">
        <?php foreach (array_slice($categoriasOrdenadas, 0, 9) as $cat): 
            $nomeCat = $cat['categoria'];
            
            // Busca a imagem real do produto no banco para esta categoria com forçados específicos
            if (strtoupper($nomeCat) === 'CANETAS') {
                $imgCat = 'https://cdn.xbzbrindes.com.br/img/produtos/3/12172-PRE-Caneta-Bambu-222.jpg';
            } elseif (strtoupper($nomeCat) === 'MOLESKINE & CADERNOS') {
                $imgCat = 'https://cdn.xbzbrindes.com.br/img/produtos/3/Caderneta-em-PU-PRETO-22045-1737734750.jpg';
            } elseif (strtoupper($nomeCat) === 'TECNOLOGIA') {
                $imgCat = 'https://cdn.xbzbrindes.com.br/img/produtos/3/Mouse-Sem-Fio-PRETO-26133-1763485910.jpg';
            } elseif (strtoupper($nomeCat) === 'MOUSE PADS') {
                $imgCat = asset('images/cat_mousepads.png');
            } elseif (strtoupper($nomeCat) === 'CARTEIRAS') {
                $imgCat = asset('images/cat_carteiras.png');
            } elseif (strtoupper($nomeCat) === 'BOLSAS E MOCHILAS') {
                // Força imagem que contenha mochila no nome para a categoria correspondente
                $stmtImgMochila = $pdo->prepare("SELECT imagem_principal FROM produtos WHERE categoria = 'BOLSAS E MOCHILAS' AND nome LIKE '%mochila%' AND ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> '' LIMIT 1");
                $stmtImgMochila->execute();
                $imgCat = $stmtImgMochila->fetchColumn();
            } else {
                $stmtImg->execute([':cat' => $nomeCat]);
                $imgCat = $stmtImg->fetchColumn();
            }
            
            // Fallback caso não tenha imagem no banco ou esteja vazio
            $fallbacksCategorias = [
                'CANETAS' => 'cat_canetas.png',
                'MOLESKINE & CADERNOS' => 'cat_moleskine.png',
                'BOLSAS E MOCHILAS' => 'cat_mochilas.png',
                'GARRAFAS E SQUEEZES' => 'cat_garrafas.png',
                'CANECAS E COPOS' => 'cat_canecas.png',
                'TECNOLOGIA' => 'cat_onboarding.png',
                'MOUSE PADS' => 'cat_mousepads.png',
                'CARTEIRAS' => 'cat_carteiras.png',
                'DIVERSOS' => 'banner_presentes.png'
            ];
            
            if (!$imgCat || $imgCat === '') {
                $upperCat = strtoupper($nomeCat);
                if (isset($fallbacksCategorias[$upperCat])) {
                    $imgCat = asset('images/' . $fallbacksCategorias[$upperCat]);
                } else {
                    $imgCat = 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?auto=format&fit=crop&q=80&w=200';
                }
            }

            // Override do painel admin tem prioridade sobre a imagem do banco/fallback.
            $imgCatAdmin = SiteContent::categoriaImagem($nomeCat);
            if ($imgCatAdmin) {
                $imgCat = $imgCatAdmin;
            }
        ?>
            <a href="<?= url('/catalogo?categoria=' . rawurlencode($nomeCat)) ?>" class="flex flex-col items-center gap-2.5 min-w-[90px] group cursor-pointer text-center">
                <div class="w-16 h-16 rounded-full overflow-hidden border border-surface-container shadow-sm group-hover:border-primary/30 group-hover:shadow-md active:scale-95 transition-all flex items-center justify-center bg-surface-container-low">
                    <img class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="<?= e($nomeCat) ?>" src="<?= e($imgCat) ?>" loading="lazy" />
                </div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-700 group-hover:text-primary transition-colors max-w-[110px] whitespace-normal block leading-tight"><?= e($nomeCat) ?></span>
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

<!-- Automatic 5-Second Hero Slider (gerenciado pelo painel admin) -->
<?php $banners = SiteContent::banners(); ?>
<?php if ($banners): ?>
<section class="w-full mb-16 select-none relative overflow-hidden bg-slate-950" id="hero-slider" style="margin-top: -1px;">
    <div class="relative w-full rounded-none min-h-[500px] md:min-h-[540px] overflow-hidden">
        <?php foreach ($banners as $bi => $b):
            $primeiro = $bi === 0;
            $bImg     = (string) ($b['imagem'] ?? '');
            $bTag     = (string) ($b['tag'] ?? '');
            $bTitulo  = (string) ($b['titulo'] ?? '');
            $bSub     = (string) ($b['subtitulo'] ?? '');
            $bCtaTxt  = (string) ($b['cta_texto'] ?? 'Ver produtos');
            $bCtaLink = (string) ($b['cta_link'] ?? '/catalogo');
            $semTexto = !empty($b['sem_texto']);
            
            // Detecta se a URL da mídia é de um vídeo baseado na extensão
            $isExtVideo = false;
            if ($bImg !== '') {
                $ext = strtolower(pathinfo(parse_url($bImg, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
                $isExtVideo = in_array($ext, ['mp4', 'webm', 'ogg'], true);
            }
        ?>
        <?php 
            $posX = isset($b['pos_x']) ? (int) $b['pos_x'] : 50; 
            $posY = isset($b['pos_y']) ? (int) $b['pos_y'] : 50; 
            $duracao = isset($b['duracao']) ? (int) $b['duracao'] : 5;
            if ($duracao <= 0) $duracao = 5;
        ?>
        <div class="hero-slide absolute inset-0 <?= $primeiro ? 'opacity-100 z-10' : 'opacity-0 z-0' ?> transition-all duration-1000 ease-in-out" data-duration="<?= $duracao * 1000 ?>">
            <?php if ($bImg !== ''): ?>
                <?php if ($isExtVideo): ?>
                    <video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline src="<?= e($bImg) ?>" style="object-position: <?= $posX ?>% <?= $posY ?>%;"></video>
                <?php else: ?>
                    <img class="absolute inset-0 w-full h-full object-cover" alt="<?= e($bTitulo ?: 'Banner Novare') ?>" src="<?= e($bImg) ?>" style="object-position: <?= $posX ?>% <?= $posY ?>%;"/>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!$semTexto): ?>
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent"></div>
                <div class="absolute inset-0 flex items-center w-full">
                    <div class="max-w-7xl mx-auto px-6 w-full flex justify-start">
                        <div class="max-w-xl text-white py-12">
                            <?php if ($bTag !== ''): ?>
                                <span class="bg-primary/20 text-sky-400 text-[10px] font-black uppercase tracking-[0.2em] px-4 py-1.5 rounded-full border border-sky-400/30 mb-4 inline-block"><?= e($bTag) ?></span>
                            <?php endif; ?>
                            <h1 class="text-4xl md:text-5xl font-black leading-none tracking-tighter mb-4"><?= e($bTitulo) ?></h1>
                            <?php if ($bSub !== ''): ?>
                                <p class="text-xs md:text-sm text-slate-300 mb-8 font-medium leading-relaxed max-w-md"><?= e($bSub) ?></p>
                            <?php endif; ?>
                            <div class="flex flex-wrap gap-4">
                                <?php if ($bCtaTxt !== '' && $bCtaLink !== ''): ?>
                                    <a href="<?= e(url($bCtaLink)) ?>" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                                        <?= e($bCtaTxt) ?>
                                    </a>
                                <?php endif; ?>
                                <a href="<?= e(whatsappLink('Olá! Vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener" class="bg-white/10 backdrop-blur-md border border-white/20 text-white hover:bg-white/20 px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all inline-block">
                                    Falar com Equipe
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Slider Bullets Indicators -->
        <div class="absolute bottom-6 right-8 z-30 flex gap-2.5">
            <?php foreach ($banners as $bi => $b): ?>
                <button class="slider-bullet w-3 h-3 rounded-full <?= $bi === 0 ? 'bg-white' : 'bg-white/40' ?> transition-all duration-300" data-slide="<?= (int) $bi ?>" aria-label="Ir para slide <?= (int) $bi + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Benefícios & Diferenciais Corporativos -->
<section class="max-w-7xl mx-auto px-6 mb-16 select-none">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-8 md:gap-6 py-12 px-8 bg-white border border-surface-container rounded-3xl shadow-md text-center">
        <!-- Benefício 1: Segurança -->
        <div class="flex flex-col items-center gap-3 p-2">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 flex items-center justify-center text-primary transition-transform hover:scale-110 duration-300">
                <span class="material-symbols-outlined text-3xl">verified_user</span>
            </div>
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 mt-1">Entrega 100% Segura</h4>
            <p class="text-[10px] text-slate-400 font-semibold uppercase leading-tight max-w-[140px]">Logística monitorada e garantida</p>
        </div>
        <!-- Benefício 2: Pix -->
        <div class="flex flex-col items-center gap-3 p-2">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 flex items-center justify-center text-primary transition-transform hover:scale-110 duration-300">
                <span class="material-symbols-outlined text-3xl">qr_code_2</span>
            </div>
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 mt-1">Pagamento no Pix</h4>
            <p class="text-[10px] text-slate-400 font-semibold uppercase leading-tight max-w-[140px]">Faturamento rápido e descontos à vista</p>
        </div>
        <!-- Benefício 3: Cartão -->
        <div class="flex flex-col items-center gap-3 p-2">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 flex items-center justify-center text-primary transition-transform hover:scale-110 duration-300">
                <span class="material-symbols-outlined text-3xl">credit_card</span>
            </div>
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 mt-1">Pagamento no Cartão</h4>
            <p class="text-[10px] text-slate-400 font-semibold uppercase leading-tight max-w-[140px]">Parcelamento sob medida</p>
        </div>
        <!-- Benefício 4: Frete Grátis -->
        <div class="flex flex-col items-center gap-3 p-2">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 flex items-center justify-center text-primary transition-transform hover:scale-110 duration-300">
                <span class="material-symbols-outlined text-3xl">local_shipping</span>
            </div>
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 mt-1">Frete Grátis *</h4>
            <p class="text-[10px] text-slate-400 font-semibold uppercase leading-tight max-w-[140px]">Consulte condições para sua região</p>
        </div>
        <!-- Benefício 5: Atendimento -->
        <div class="flex flex-col items-center gap-3 p-2 col-span-2 md:col-span-1">
            <div class="w-14 h-14 rounded-2xl bg-sky-50 flex items-center justify-center text-primary transition-transform hover:scale-110 duration-300">
                <span class="material-symbols-outlined text-3xl">support_agent</span>
            </div>
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 mt-1">Fale com a nossa equipe</h4>
            <p class="text-[10px] text-slate-400 font-semibold uppercase leading-tight max-w-[150px]">Consultores dedicados do início ao fim</p>
        </div>
    </div>
</section>

<!-- Seção "Mais Vendidos" (Ranking Top 1 a 10) -->
<?php if ($topCanetas): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Escrita Executiva</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Canetas</h2>
            <p class="text-slate-500 text-sm mt-1">As 10 canetas e itens de escrita mais procurados para presentear e personalizar.</p>
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
        <?php foreach ($topCanetas as $i => $p):
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
                    <?php if (!empty($p['sku_pai'])): ?>
                        <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                    <?php endif; ?>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Corporativo') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm group-hover:text-primary transition-colors mb-2 leading-snug"><?= e($p['nome']) ?></h4>
                    <div class="flex items-center justify-between border-t border-surface-container-low pt-3 mt-2 w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Seção "Top Escritório" (Ranking Top 1 a 10) -->
<?php if ($topCadernos): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Organização e Foco</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Cadernos, Agendas & Moleskine</h2>
            <p class="text-slate-500 text-sm mt-1">Os 10 cadernos, agendas e moleskines mais requisitados pelas empresas.</p>
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
        <?php foreach ($topCadernos as $i => $p):
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
                    <?php if (!empty($p['sku_pai'])): ?>
                        <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                    <?php endif; ?>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Papelaria') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm group-hover:text-primary transition-colors mb-2 leading-snug"><?= e($p['nome']) ?></h4>
                    <div class="flex items-center justify-between border-t border-surface-container-low pt-3 mt-2 w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Seção "Top Dia-a-dia" (Ranking Top 1 a 10) -->
<?php if ($topGarrafas): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Hidratação & Estilo</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Garrafas</h2>
            <p class="text-slate-500 text-sm mt-1">As 10 garrafas e squeezes mais vendidos para a sua equipe se manter hidratada.</p>
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
        <?php foreach ($topGarrafas as $i => $p):
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
                    <?php if (!empty($p['sku_pai'])): ?>
                        <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                    <?php endif; ?>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Dia a Dia') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm group-hover:text-primary transition-colors mb-2 leading-snug"><?= e($p['nome']) ?></h4>
                    <div class="flex items-center justify-between border-t border-surface-container-low pt-3 mt-2 w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Seção "Top Produtividade" (Ranking Top 1 a 10) -->
<?php if ($topMochilas): ?>
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 overflow-hidden">
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4 select-none">
        <div class="text-center md:text-left">
            <span class="text-primary font-bold uppercase tracking-wider text-xs">Mobilidade & Viagem</span>
            <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Top Mochilas</h2>
            <p class="text-slate-500 text-sm mt-1">As 10 mochilas executivas mais procuradas para o dia a dia e viagens corporativas.</p>
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
        <?php foreach ($topMochilas as $i => $p):
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
                    <?php if (!empty($p['sku_pai'])): ?>
                        <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                    <?php endif; ?>
                    <span class="text-primary font-bold text-[8px] uppercase tracking-wider block mb-1"><?= e($p['categoria'] ?? 'Produtividade') ?></span>
                    <h4 class="font-extrabold text-on-surface text-sm group-hover:text-primary transition-colors mb-2 leading-snug"><?= e($p['nome']) ?></h4>
                    <div class="flex items-center justify-between border-t border-surface-container-low pt-3 mt-2 w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
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
        <h2 class="text-3xl font-black text-on-surface tracking-tighter mt-1">Nossa seleção premium</h2>
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
                <div class="flex items-center justify-between pt-3 border-t border-surface-container-low mt-auto w-full">
                    <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                        Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($destaques[1])): 
            $p = $destaques[1];
            $img = $p['imagem_principal'] ?? '';
        ?>
            <!-- Small Featured 1 (horizontal card) -->
            <div onclick="location.href='<?= url('/produto/' . rawurlencode($p['sku_pai'])) ?>'" class="md:col-span-2 bg-secondary-container/10 rounded-3xl p-6 flex items-center justify-between gap-6 group cursor-pointer border border-transparent hover:border-primary/10 hover:shadow-lg transition-all shadow-sm">
                <div class="flex-grow flex flex-col justify-between">
                    <div class="mb-4">
                        <?php if (!empty($p['sku_pai'])): ?>
                            <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                        <?php endif; ?>
                        <span class="bg-secondary text-white px-3 py-1 rounded-full text-[8px] font-bold uppercase tracking-wider mb-3 inline-block shadow-sm w-fit">Novidade</span>
                        <h4 class="text-lg font-black text-on-surface group-hover:text-primary transition-colors leading-snug"><?= e($p['nome']) ?></h4>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-surface-container-low mt-auto w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </span>
                    </div>
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
                    <?php if (!empty($p['sku_pai'])): ?>
                        <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                    <?php endif; ?>
                    <h4 class="font-extrabold text-on-surface mt-2 text-xs group-hover:text-primary transition-colors leading-snug"><?= e($p['nome']) ?></h4>
                    <span class="text-[10px] text-slate-400 mt-1 block uppercase tracking-wider font-semibold"><?= e($p['categoria'] ?? 'Geral') ?></span>
                    <div class="flex items-center justify-between pt-3 border-t border-surface-container-low mt-3 w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </span>
                    </div>
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
                    <?php if (!empty($p['sku_pai'])): ?>
                        <span class="text-[8px] font-medium text-slate-400 uppercase tracking-widest block mb-0.5">SKU: <?= e($p['sku_pai']) ?></span>
                    <?php endif; ?>
                    <h4 class="font-extrabold text-on-surface mt-2 text-xs group-hover:text-primary transition-colors leading-snug"><?= e($p['nome']) ?></h4>
                    <span class="text-[10px] text-slate-400 mt-1 block uppercase tracking-wider font-semibold"><?= e($p['categoria'] ?? 'Geral') ?></span>
                    <div class="flex items-center justify-between pt-3 border-t border-surface-container-low mt-3 w-full">
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary transition-colors uppercase tracking-wider flex items-center gap-1">
                            Ver produto <span class="material-symbols-outlined text-[12px] font-bold group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Grade 3x2 de Categorias Humanizadas -->
<section class="max-w-7xl mx-auto px-6 py-6 mb-16 select-none">
    <div class="mb-10 text-center md:text-left">
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
        <div onclick="location.href='<?= url('/catalogo?categoria=' . rawurlencode('CANETAS')) ?>'" class="relative overflow-hidden rounded-none h-[440px] group cursor-pointer shadow-sm hover:shadow-lg border border-surface-container/50 transition-all">
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
                    <a href="<?= e(whatsappLink('Olá! Vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener" class="primary-gradient text-white px-8 py-3.5 rounded-full font-black uppercase tracking-wider text-[10px] hover:scale-105 active:scale-95 transition-all shadow-lg inline-block">
                        Fazer Briefing no WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Avaliações / Depoimentos (Estilo Google Reviews) -->
<?php
// Link para o perfil de empresa da Novare Brindes no Google (abre em nova guia).
$googleReviewsUrl = 'https://www.google.com/search?q=Novare+Brindes+Corporativos+avalia%C3%A7%C3%B5es';

// 8 depoimentos reais com avatares humanos (Unsplash), nome, empresa e tempo decorrido.
$depoimentos = [
    [
        'nome'  => 'Mariana Costa',
        'empresa' => 'Gerente de RH',
        'tempo' => 'há 2 semanas',
        'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Montaram nosso kit de onboarding com identidade impecável e entregaram no prazo apertado da convenção. Os novos colaboradores amaram!',
    ],
    [
        'nome'  => 'Rafael Almeida',
        'empresa' => 'Coordenador de Marketing',
        'tempo' => 'há 1 mês',
        'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Atendimento ágil e consultivo: sugeriram alternativas econômicas dentro do orçamento mantendo alta durabilidade. Recomendo de olhos fechados.',
    ],
    [
        'nome'  => 'Juliana Ferreira',
        'empresa' => 'Analista de Eventos',
        'tempo' => 'há 3 semanas',
        'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Catálogo amplo e atendimento altamente qualificado. Viramos clientes recorrentes para todos os nossos eventos corporativos.',
    ],
    [
        'nome'  => 'Bruno Carvalho',
        'empresa' => 'Diretor Comercial',
        'tempo' => 'há 2 meses',
        'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Brindes de altíssima qualidade e a personalização ficou idêntica à nossa marca. A logística foi monitorada do início ao fim.',
    ],
    [
        'nome'  => 'Camila Rodrigues',
        'empresa' => 'Gerente de Endomarketing',
        'tempo' => 'há 1 semana',
        'avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Pedimos garrafas térmicas e canecas para a campanha interna e o resultado superou as expectativas. Equipe super atenciosa!',
    ],
    [
        'nome'  => 'Eduardo Martins',
        'empresa' => 'Sócio',
        'tempo' => 'há 1 mês',
        'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Cotação rápida, prazos cumpridos e acabamento premium nos cadernos personalizados. Parceria que virou recorrente.',
    ],
    [
        'nome'  => 'Patrícia Lima',
        'empresa' => 'Compras',
        'tempo' => 'há 5 dias',
        'avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Excelente custo-benefício para grandes volumes sem perder qualidade. O time consultivo entendeu exatamente o que precisávamos.',
    ],
    [
        'nome'  => 'Felipe Souza',
        'empresa' => 'Gerente de Projetos',
        'tempo' => 'há 3 meses',
        'avatar' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=96&h=96&q=80',
        'texto' => 'Mochilas executivas impecáveis para a nossa convenção anual. Profissionalismo do orçamento à entrega. Nota dez!',
    ],
];
?>
<section class="max-w-7xl mx-auto px-6 py-10 border-t border-surface-container/50 select-none mb-8" id="reviews-section">
    <!-- Cabeçalho estilo Google Reviews -->
    <div class="text-center mb-12">
        <h2 class="text-3xl font-black text-on-surface tracking-tighter">O que nossos clientes dizem</h2>
        <div class="flex items-center justify-center gap-2 mt-3 flex-wrap">
            <span class="flex gap-0.5 text-rose-400">
                <span class="material-symbols-outlined text-xl font-fill" style="font-variation-settings: 'FILL' 1;">star</span>
                <span class="material-symbols-outlined text-xl font-fill" style="font-variation-settings: 'FILL' 1;">star</span>
                <span class="material-symbols-outlined text-xl font-fill" style="font-variation-settings: 'FILL' 1;">star</span>
                <span class="material-symbols-outlined text-xl font-fill" style="font-variation-settings: 'FILL' 1;">star</span>
                <span class="material-symbols-outlined text-xl font-fill" style="font-variation-settings: 'FILL' 1;">star</span>
            </span>
            <span class="text-sm font-bold text-slate-700"><strong class="font-black">4,9</strong> no Google</span>
            <span class="text-slate-300">•</span>
            <span class="text-sm font-semibold text-slate-500">44 avaliações verificadas</span>
        </div>
    </div>

    <!-- Carrossel horizontal de depoimentos -->
    <div class="flex gap-6 overflow-x-auto py-4 px-1 no-scrollbar scroll-smooth snap-x snap-mandatory" id="reviews-carousel">
        <?php foreach ($depoimentos as $d): ?>
            <div class="min-w-[300px] sm:min-w-[340px] max-w-[340px] snap-start bg-white border border-surface-container rounded-3xl p-7 shadow-sm hover:shadow-md transition-shadow flex flex-col">
                <!-- Topo: avatar + nome + selo Google -->
                <div class="flex items-center gap-3 mb-4">
                    <img src="<?= e($d['avatar']) ?>" alt="<?= e($d['nome']) ?>" class="w-11 h-11 rounded-full object-cover border border-surface-container flex-shrink-0" loading="lazy" width="44" height="44">
                    <div class="min-w-0 flex-grow">
                        <strong class="block text-sm font-black text-slate-800 truncate"><?= e($d['nome']) ?></strong>
                        <span class="block text-[10px] text-slate-400 font-semibold truncate"><?= e($d['empresa']) ?></span>
                    </div>
                    <svg viewBox="0 0 24 24" class="w-5 h-5 flex-shrink-0" aria-label="Google" role="img">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.26 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/>
                    </svg>
                </div>
                <!-- Estrelas + tempo -->
                <div class="flex items-center gap-2 mb-3">
                    <span class="flex gap-0.5 text-rose-400">
                        <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">star</span>
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold"><?= e($d['tempo']) ?></span>
                </div>
                <!-- Texto -->
                <p class="text-slate-600 text-xs leading-relaxed"><?= e($d['texto']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Navegação: setas circulares + botão central coral -->
    <div class="flex items-center justify-center gap-4 mt-10">
        <button type="button" onclick="document.getElementById('reviews-carousel').scrollBy({left: -360, behavior: 'smooth'})" class="w-11 h-11 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white text-slate-600" aria-label="Avaliações anteriores">
            <span class="material-symbols-outlined text-lg font-bold">arrow_back</span>
        </button>
        <a href="<?= e($googleReviewsUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-white text-[11px] font-black uppercase tracking-wider px-7 py-3.5 rounded-full shadow-md hover:scale-105 active:scale-95 transition-all" style="background: linear-gradient(135deg, #ff6f61 0%, #ff8a65 100%);">
            <span class="material-symbols-outlined text-base" style="font-variation-settings: 'FILL' 1;">reviews</span>
            Ver todas as avaliações no Google
        </a>
        <button type="button" onclick="document.getElementById('reviews-carousel').scrollBy({left: 360, behavior: 'smooth'})" class="w-11 h-11 rounded-full border border-surface-container hover:bg-slate-50 active:scale-95 flex items-center justify-center cursor-pointer transition-all shadow-sm bg-white text-slate-600" aria-label="Próximas avaliações">
            <span class="material-symbols-outlined text-lg font-bold">arrow_forward</span>
        </button>
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
            if (slides.length <= 1) return;
            const delay = parseInt(slides[current].getAttribute('data-duration')) || 5000;
            timer = setTimeout(function() {
                nextSlide();
                startTimer();
            }, delay);
        }

        function stopTimer() {
            if (timer) clearTimeout(timer);
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
