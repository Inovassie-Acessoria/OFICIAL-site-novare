<?php
/**
 * Página de produto.
 *
 * Ordem do conteúdo importa: bloco de decisão (o que o comprador B2B procura
 * antes de chamar no WhatsApp) -> conteúdo PRÓPRIO -> descrição do fornecedor
 * (por último, rotulada). Sem isso a página é lida como duplicata dos
 * dezenas de revendedores que recebem o mesmo feed da XBZ.
 *
 * @var array  $produto      linha de produtos
 * @var array  $variacoes    cada: id, sku_completo, cor_sufixo, cor, cor_codigo, estoque, imagens[]
 * @var string $nome_legivel
 * @var array|null $categoria_row
 * @var array  $operacional  prazo, tecnicas, area
 * @var int|null $qtd_minima
 * @var array  $faq
 * @var array  $relacionados
 * @var string $url_produto
 * @var array  $breadcrumbs
 */
$urlProduto = Seo::canonical($url_produto);
$primeira   = $variacoes[0] ?? null;
$imgInicial = $produto['imagem_local'] ?: ($primeira['imagens'][0] ?? ($produto['imagem_principal'] ?? ''));
$nome       = $nome_legivel;
$catNome    = $categoria_row['nome'] ?? ($produto['categoria'] ?? null);
$catUrl     = $categoria_row ? Seo::urlCategoriaSlug($categoria_row['slug']) : Seo::urlHub();
$prazoTxt   = Seo::prazoTexto($operacional['prazo']);
$rotuloCat  = trim((string) ($categoria_row['rotulo_seo'] ?? '')) ?: (($catNome ?: 'Brindes') . ' Personalizados');
$descPropria = trim((string) ($produto['descricao_propria'] ?? ''));

// payload para o JS (swatches trocam galeria/sku)
$payload = array_map(static fn ($v) => [
    'sku'     => $v['sku_completo'],
    'cor'     => $v['cor'],
    'hex'     => $v['cor_codigo'],
    'imagens' => array_values(array_filter($v['imagens'] ?? [])),
], $variacoes);

$pdo = Database::connection();
$top1Sku = $pdo->query("SELECT sku_pai FROM produtos WHERE ativo = 1 AND preco_base > 0 AND imagem_principal IS NOT NULL AND imagem_principal <> '' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: '';
$ehTop1 = ($top1Sku !== '' && $produto['sku_pai'] === $top1Sku);
?>
<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Breadcrumbs: links reais, com <a href> -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-8 flex-wrap" aria-label="Você está em">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span class="material-symbols-outlined text-[10px]">chevron_right</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
                <a href="<?= e($bc['url']) ?>" class="hover:text-primary transition-colors flex items-center gap-1">
                    <?php if ($i === 0): ?><span class="material-symbols-outlined text-sm">home</span><?php endif; ?><?= e($bc['name']) ?>
                </a>
            <?php else: ?>
                <span class="text-secondary font-semibold"><?= e($bc['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12"
         data-produto
         data-nome="<?= e($nome) ?>"
         data-sku="<?= e($produto['sku_pai']) ?>"
         data-url="<?= e($urlProduto) ?>"
         data-whats="<?= e(whatsappNumero()) ?>">

        <!-- Galeria: imagem principal é o LCP — sem lazy, com fetchpriority -->
        <div class="space-y-4">
            <div class="bg-white border border-surface-container rounded-2xl p-8 flex items-center justify-center aspect-square shadow-sm overflow-hidden relative">
                <?php if (!empty($produto['sustentavel'])): ?>
                    <span class="absolute top-4 left-4 bg-emerald-500 text-white text-[8px] font-black uppercase tracking-widest px-3 py-1 rounded-full shadow-sm z-10 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[10px] font-bold">eco</span> Sustentável
                    </span>
                <?php endif; ?>
                <?php if ($imgInicial !== ''): ?>
                    <img id="gallery-img" class="max-h-full max-w-full object-contain transition-all duration-300" src="<?= e($imgInicial) ?>" alt="<?= e($nome) ?><?= !empty($produto['material']) ? ' de ' . e(mb_strtolower($produto['material'], 'UTF-8')) : '' ?> personalizado com logo" width="520" height="520" fetchpriority="high" decoding="async">
                <?php else: ?>
                    <div class="text-slate-400 flex flex-col items-center gap-2">
                        <span class="material-symbols-outlined text-4xl">image_not_supported</span>
                        Sem imagem disponível
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex gap-3 overflow-x-auto no-scrollbar py-1" id="gallery-thumbs"></div>
        </div>

        <!-- Informações e ações -->
        <div class="flex flex-col justify-between">
            <div>
                <?php if ($catNome): ?>
                    <a href="<?= e($catUrl) ?>" class="bg-primary/10 text-primary text-[10px] font-extrabold uppercase tracking-widest px-3.5 py-1.5 rounded-full shadow-sm w-fit mb-4 inline-block hover:bg-primary/20 transition-colors"><?= e($catNome) ?></a>
                <?php endif; ?>

                <?php if ($ehTop1): ?>
                    <div class="bg-primary text-white py-2.5 px-5 text-center text-[10px] font-black uppercase tracking-widest shadow-sm rounded-xl mb-4 flex items-center justify-center gap-1.5 select-none animate-pulse">
                        <span class="material-symbols-outlined text-sm font-bold">workspace_premium</span>
                        🏆 Produto Top #1 de Vendas na Novare Brindes
                    </div>
                <?php endif; ?>

                <!-- H1 único: próximo do title, não idêntico -->
                <h1 class="text-3xl font-black text-on-surface tracking-tighter leading-tight mb-2"><?= e($nome) ?> <span class="text-primary">Personalizado com Logo</span></h1>

                <div class="text-xs text-slate-400 font-medium mb-6 flex flex-wrap gap-x-4 gap-y-1">
                    <span>Cód. base: <strong class="text-secondary font-semibold"><?= e($produto['sku_pai']) ?></strong></span>
                    <span>·</span>
                    <span>SKU Ativo: <strong id="sku-ativo" class="text-primary font-bold"><?= e($primeira['sku_completo'] ?? $produto['sku_pai']) ?></strong></span>
                    <?php if ($qtd_minima): ?>
                        <span>·</span>
                        <span>QTD Mínima: <strong class="text-secondary font-semibold"><?= (int) $qtd_minima ?> un.</strong></span>
                    <?php endif; ?>
                </div>

                <!-- BLOCO DE DECISÃO: as informações que o comprador B2B procura antes de chamar -->
                <div class="bg-surface-container-low border border-surface-container rounded-2xl p-6 mb-6 shadow-sm">
                    <span class="text-[10px] text-slate-400 uppercase font-extrabold tracking-wider block mb-1">Preço sob consulta</span>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-2xl">request_quote</span>
                        <span class="text-base font-black text-primary tracking-tight">Solicite seu orçamento personalizado</span>
                    </div>
                    <ul class="text-xs text-secondary space-y-1.5 mb-3">
                        <?php if ($qtd_minima): ?>
                            <li class="flex items-start gap-2"><span class="material-symbols-outlined text-sm text-primary">inventory_2</span><span><strong>Quantidade mínima:</strong> <?= (int) $qtd_minima ?> unidades</span></li>
                        <?php endif; ?>
                        <?php if ($prazoTxt): ?>
                            <li class="flex items-start gap-2"><span class="material-symbols-outlined text-sm text-primary">schedule</span><span><strong>Prazo de produção:</strong> <?= e($prazoTxt) ?> após aprovação da arte</span></li>
                        <?php endif; ?>
                        <?php if ($operacional['tecnicas']): ?>
                            <li class="flex items-start gap-2"><span class="material-symbols-outlined text-sm text-primary">brush</span><span><strong>Personalização:</strong> <?= e($operacional['tecnicas']) ?></span></li>
                        <?php endif; ?>
                        <?php if ($operacional['area']): ?>
                            <li class="flex items-start gap-2"><span class="material-symbols-outlined text-sm text-primary">crop</span><span><strong>Área de impressão:</strong> <?= e($operacional['area']) ?></span></li>
                        <?php endif; ?>
                        <li class="flex items-start gap-2"><span class="material-symbols-outlined text-sm text-primary">local_shipping</span><span><strong>Entrega:</strong> para todo o Brasil, frete calculado no orçamento</span></li>
                    </ul>
                    <p class="text-[10px] text-slate-500 leading-relaxed">
                        Valores definidos conforme a quantidade e a personalização do lote. Fale com nosso time pelo WhatsApp para uma cotação rápida e sob medida para a sua empresa.
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

                <!-- CONTEÚDO PRÓPRIO: vem ANTES da descrição do fornecedor -->
                <?php if ($descPropria !== ''): ?>
                    <div class="mb-8">
                        <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Sobre <?= e(mb_strtolower($nome, 'UTF-8')) ?> personalizado</h2>
                        <div class="text-secondary text-sm leading-relaxed"><?= nl2br(e(strip_tags($descPropria, '<b><strong><em><i><br><p><ul><ol><li>'))) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Swatches de cores -->
                <?php if (count($variacoes) > 0): ?>
                    <div class="mb-8 border-t border-surface-container/50 pt-6">
                        <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">palette</span> Cor Ativa: <span id="cor-ativa" class="text-primary font-bold lowercase"><?= e($primeira['cor'] ?? '') ?></span>
                        </h2>
                        <div class="flex flex-wrap gap-2.5" id="color-swatches">
                            <?php foreach ($variacoes as $i => $v): ?>
                                <button type="button" data-index="<?= $i ?>" class="w-8 h-8 rounded-full border border-surface-container transition-all hover:scale-105 hover:shadow-md active:ring-2 active:ring-primary [&.active]:ring-2 [&.active]:ring-primary [&.active]:scale-110 shadow-sm" title="<?= e($v['cor']) ?>" aria-label="<?= e($v['cor']) ?>" style="background:<?= e($v['cor_codigo'] ?: '#ccc') ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CTA com contexto: o atendimento não começa do zero -->
            <div class="space-y-3 pt-6 border-t border-surface-container/50">
                <a id="btn-produto-direto" data-evento="orcamento_whatsapp" data-sku="<?= e($produto['sku_pai']) ?>" class="w-full flex items-center justify-center gap-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black uppercase tracking-widest py-4 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all" href="<?= e(Seo::whatsappProdutoLink($produto)) ?>" target="_blank" rel="noopener nofollow">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20zm4.5-5.9c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.6-1.2.1-.2 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3-.7.7-.9 1.7-.6 2.8.5 1.6 1.6 3 3.1 4 .9.5 1.6.8 2.1.9.7.2 1.4.2 1.9.1.6-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1 0-.1-.2-.2-.4-.3z"/></svg>
                    Fazer Orçamento do Brinde
                </a>
                <p class="text-[10px] text-slate-400 text-center font-medium">Sem vendas diretas ou cadastro complexo. Atendimento B2B consultivo rápido no WhatsApp.</p>
            </div>
        </div>
    </div>

    <!-- Especificações: tabela — motores de IA extraem tabela com facilidade muito acima de parágrafo -->
    <section class="mt-12 border-t border-surface-container/60 pt-10">
        <h2 class="text-lg font-black text-on-surface tracking-tight mb-5">Especificações técnicas</h2>
        <div class="bg-white border border-surface-container rounded-2xl shadow-sm overflow-hidden max-w-2xl">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-surface-container">
                    <tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface w-48 bg-surface-container-low">Produto</th><td class="px-5 py-3 text-secondary"><?= e($nome) ?></td></tr>
                    <tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Código</th><td class="px-5 py-3 text-secondary"><?= e($produto['sku_pai']) ?></td></tr>
                    <?php if ($catNome): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Categoria</th><td class="px-5 py-3 text-secondary"><a class="text-primary hover:underline" href="<?= e($catUrl) ?>"><?= e($catNome) ?></a></td></tr><?php endif; ?>
                    <?php if (!empty($produto['material'])): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Material</th><td class="px-5 py-3 text-secondary"><?= e($produto['material']) ?></td></tr><?php endif; ?>
                    <tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Cores disponíveis</th><td class="px-5 py-3 text-secondary"><?= e(implode(', ', array_unique(array_map(static fn ($v) => (string) $v['cor'], $variacoes)))) ?: '—' ?></td></tr>
                    <?php if ($qtd_minima): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Quantidade mínima</th><td class="px-5 py-3 text-secondary"><?= (int) $qtd_minima ?> unidades</td></tr><?php endif; ?>
                    <?php if ($prazoTxt): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Prazo de produção</th><td class="px-5 py-3 text-secondary"><?= e($prazoTxt) ?> após aprovação da arte</td></tr><?php endif; ?>
                    <?php if ($operacional['tecnicas']): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Personalização</th><td class="px-5 py-3 text-secondary"><?= e($operacional['tecnicas']) ?></td></tr><?php endif; ?>
                    <?php if ($operacional['area']): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Área de impressão</th><td class="px-5 py-3 text-secondary"><?= e($operacional['area']) ?></td></tr><?php endif; ?>
                    <?php if (!empty($produto['sustentavel'])): ?><tr><th scope="row" class="text-left px-5 py-3 font-bold text-on-surface bg-surface-container-low">Sustentável</th><td class="px-5 py-3 text-secondary">Sim — linha ecológica</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Descrição do fornecedor: por último e rotulada -->
    <?php if (!empty($produto['descricao'])): ?>
        <section class="mt-10">
            <h2 class="text-xs uppercase tracking-wider font-extrabold text-on-surface mb-3 border-l-2 border-primary pl-2.5">Informações do fabricante</h2>
            <p class="text-secondary text-sm leading-relaxed whitespace-pre-line max-w-3xl"><?= e($produto['descricao']) ?></p>
        </section>
    <?php endif; ?>

    <!-- Blocos de resposta direta (mesmas perguntas do schema FAQPage) -->
    <?php partial('faq', ['faq' => $faq, 'titulo' => 'Perguntas frequentes sobre ' . mb_strtolower($nome, 'UTF-8') . ' personalizado']); ?>

    <!-- Relacionados com critério: mesma categoria, quantidade mínima parecida. Âncora descritiva. -->
    <?php if (!empty($relacionados)): ?>
        <section class="mt-12 border-t border-surface-container/60 pt-10">
            <h2 class="text-lg font-black text-on-surface tracking-tight mb-6">Mais <?= e(mb_strtolower($rotuloCat, 'UTF-8')) ?> com logo</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                <?php foreach ($relacionados as $p): ?>
                    <?php partial('card', ['p' => $p]); ?>
                <?php endforeach; ?>
            </div>
            <p class="mt-6 text-sm">
                <a href="<?= e($catUrl) ?>" class="text-primary font-bold hover:underline inline-flex items-center gap-1">
                    Ver a categoria completa: <?= e($rotuloCat) ?> <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </p>
        </section>
    <?php endif; ?>
</div>

<script type="application/json" id="variacoes-data"><?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
