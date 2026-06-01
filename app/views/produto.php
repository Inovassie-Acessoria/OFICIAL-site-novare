<?php
/**
 * View de Detalhes do Produto estilizada com Tailwind CSS.
 * @var array $produto    linha de produtos
 * @var array $variacoes  cada: id, sku_completo, cor_sufixo, cor, cor_codigo, estoque, imagens[]
 */
$urlProduto = urlAbsoluta('/produto/' . rawurlencode($produto['sku_pai']));
$primeira   = $variacoes[0] ?? null;
$imgInicial = $primeira['imagens'][0] ?? ($produto['imagem_principal'] ?? '');

// payload para o JS (swatches trocam galeria/sku)
$payload = array_map(static fn ($v) => [
    'sku'     => $v['sku_completo'],
    'cor'     => $v['cor'],
    'hex'     => $v['cor_codigo'],
    'imagens' => array_values(array_filter($v['imagens'] ?? [])),
], $variacoes);

// Consulta dinâmica para verificar se o SKU atual é o produto Top #1 de vendas
$pdo = Database::connection();
$top1Sku = $pdo->query("SELECT sku_pai FROM produtos WHERE ativo = 1 AND preco_base > 0 AND imagem_principal IS NOT NULL AND imagem_principal <> '' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: '';
$ehTop1 = ($top1Sku !== '' && $produto['sku_pai'] === $top1Sku);
?>
<style>
    #gallery-thumbs button {
        width: 64px;
        height: 64px;
        border: 1px solid #eeeef0;
        border-radius: 8px;
        padding: 4px;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    #gallery-thumbs button:hover {
        border-color: #006590;
    }
    #gallery-thumbs button.active {
        border-color: #006590;
        box-shadow: 0 0 0 2px rgba(0, 101, 144, 0.2);
    }
    #gallery-thumbs button img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
</style>

<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-8" aria-label="Breadcrumb">
        <a href="<?= url('/') ?>" class="hover:text-primary transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">home</span> Início
        </a>
        <span class="material-symbols-outlined text-[10px]">chevron_right</span>
        <a href="<?= url('/catalogo' . (!empty($produto['categoria']) ? '?categoria=' . rawurlencode($produto['categoria']) : '')) ?>" class="hover:text-primary transition-colors"><?= e($produto['categoria'] ?? 'Catálogo') ?></a>
        <span class="material-symbols-outlined text-[10px]">chevron_right</span>
        <span class="text-secondary font-semibold"><?= e($produto['nome']) ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12"
         data-produto
         data-nome="<?= e($produto['nome']) ?>"
         data-url="<?= e($urlProduto) ?>"
         data-whats="<?= e(whatsappNumero()) ?>">

        <!-- Galeria de Imagens -->
        <div class="space-y-4">
            <div class="bg-white border border-surface-container rounded-2xl p-8 flex items-center justify-center aspect-square shadow-sm overflow-hidden relative">
                <?php if (!empty($produto['sustentavel'])): ?>
                    <span class="absolute top-4 left-4 bg-emerald-500 text-white text-[8px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-sm z-10 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[10px] font-bold">eco</span> Sustentável
                    </span>
                <?php endif; ?>
                <?php if ($imgInicial !== ''): ?>
                    <img id="gallery-img" class="max-h-full max-w-full object-contain transition-all duration-300" src="<?= e($imgInicial) ?>" alt="<?= e($produto['nome']) ?>" width="520" height="520">
                <?php else: ?>
                    <div class="text-slate-400 flex flex-col items-center gap-2">
                        <span class="material-symbols-outlined text-4xl">image_not_supported</span>
                        Sem imagem disponível
                    </div>
                <?php endif; ?>
            </div>
            <!-- Thumbs (geradas e gerenciadas via JS) -->
            <div class="flex gap-3 overflow-x-auto no-scrollbar py-1" id="gallery-thumbs"></div>
        </div>

        <!-- Informações e Ações do Produto -->
        <div class="flex flex-col justify-between">
            <div>
                <?php if (!empty($produto['categoria'])): ?>
                    <span class="bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-widest px-3.5 py-1.5 rounded-full shadow-sm w-fit mb-4 inline-block"><?= e($produto['categoria']) ?></span>
                <?php endif; ?>
                
                <?php if ($ehTop1): ?>
                    <div class="bg-primary text-white py-2.5 px-5 text-center text-[10px] font-black uppercase tracking-widest shadow-sm rounded-xl mb-4 flex items-center justify-center gap-1.5 select-none animate-pulse">
                        <span class="material-symbols-outlined text-sm font-bold">workspace_premium</span>
                        🏆 Produto Top #1 de Vendas na Novare Brindes
                    </div>
                <?php endif; ?>
                
                <h1 class="text-3xl font-black text-on-surface tracking-tighter leading-tight mb-2"><?= e($produto['nome']) ?></h1>
                
                <!-- SKU e Códigos -->
                <div class="text-xs text-slate-400 font-medium mb-6 flex flex-wrap gap-x-4 gap-y-1">
                    <span>Cód. base: <strong class="text-secondary font-semibold"><?= e($produto['sku_pai']) ?></strong></span>
                    <span>·</span>
                    <span>SKU Ativo: <strong id="sku-ativo" class="text-primary font-bold"><?= e($primeira['sku_completo'] ?? $produto['sku_pai']) ?></strong></span>
                </div>

                <!-- Preço Estimado -->
                <div class="bg-surface-container-low border border-surface-container rounded-2xl p-6 mb-6 shadow-sm">
                    <span class="text-[10px] text-slate-400 uppercase font-extrabold tracking-wider block mb-1">Preço sob consulta</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-primary"><?= e(preco($produto['preco_base'] ?? 0)) ?></span>
                        <span class="text-xs text-slate-400 font-bold">/ a partir de</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-2 leading-relaxed">
                        Preço unitário estimado para o lote base. Quanto maior o lote solicitado para sua empresa, menor o preço final unitário.
                    </p>
                </div>

                <!-- Atributos -->
                <div class="flex flex-wrap gap-3 mb-8">
                    <?php if (!empty($produto['material'])): ?>
                        <span class="bg-white border border-surface-container px-3.5 py-1.5 rounded-xl text-xs font-semibold text-secondary flex items-center gap-1.5 shadow-sm">
                            <span class="material-symbols-outlined text-sm">texture</span> <strong>Material:</strong> <?= e($produto['material']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($produto['sustentavel'])): ?>
                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3.5 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-sm">
                            <span class="material-symbols-outlined text-sm font-bold">eco</span> Ecológico
                        </span>
                    <?php endif; ?>
                    <span class="bg-white border border-surface-container px-3.5 py-1.5 rounded-xl text-xs font-semibold text-secondary flex items-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-sm">palette</span> <strong>Cores:</strong> <?= count($variacoes) ?>
                    </span>
                </div>

                <!-- Descrição -->
                <?php if (!empty($produto['descricao'])): ?>
                    <div class="mb-8">
                        <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Descrição Detalhada do Brinde</h3>
                        <p class="text-secondary text-sm leading-relaxed whitespace-pre-line"><?= e($produto['descricao']) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Swatches de Cores -->
                <?php if (count($variacoes) > 0): ?>
                    <div class="mb-8 border-t border-surface-container/50 pt-6">
                        <h3 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">palette</span> Cor Ativa: <span id="cor-ativa" class="text-primary font-bold lowercase"><?= e($primeira['cor'] ?? '') ?></span>
                        </h3>
                        <div class="flex flex-wrap gap-2.5" id="color-swatches">
                            <?php foreach ($variacoes as $i => $v): ?>
                                <button type="button" data-index="<?= $i ?>" class="w-8 h-8 rounded-full border border-surface-container transition-all hover:scale-105 hover:shadow-md active:ring-2 active:ring-primary [&.active]:ring-2 [&.active]:ring-primary [&.active]:scale-110 shadow-sm" title="<?= e($v['cor']) ?>" aria-label="<?= e($v['cor']) ?>" style="background:<?= e($v['cor_codigo'] ?: '#ccc') ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CTA Stack -->
            <div class="space-y-3 pt-6 border-t border-surface-container/50">
                <a id="cta-whats" class="w-full flex items-center justify-center gap-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black uppercase tracking-widest py-4 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all" href="<?= e(whatsappLink(whatsappProduto($produto['nome'], $primeira['cor'] ?? '', $primeira['sku_completo'] ?? $produto['sku_pai'], $urlProduto))) ?>" target="_blank" rel="noopener">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20zm4.5-5.9c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.6-1.2.1-.2 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3-.7.7-.9 1.7-.6 2.8.5 1.6 1.6 3 3.1 4 .9.5 1.6.8 2.1.9.7.2 1.4.2 1.9.1.6-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1 0-.1-.2-.2-.4-.3z"/></svg>
                    Fazer Orçamento do Brinde
                </a>
                <p class="text-[10px] text-slate-400 text-center font-medium">Sem vendas diretas ou cadastro complexo. Atendimento B2B consultivo rápido no WhatsApp.</p>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="variacoes-data"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
