<?php
/**
 * Painel de SEO (/settings-admin/seo).
 * @var string $csrf
 * @var string|null $flash
 * @var bool   $portao_ativo
 * @var array|null $stats
 * @var array  $operacional  prazo, tecnicas, area
 * @var array  $entidade
 * @var array|null $produto  (com 'imagens' e 'avaliacao')
 * @var string $sku_busca
 * @var array  $categorias, $faqs, $ocasioes, $facetas, $redirects, $materiais
 * @var string $indexnow_key
 * @var array|null $indexnow_ultimo
 * @var array  $imagens  pendentes, feitas, gd
 */
$inp = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500';
$btn = 'inline-flex items-center gap-1.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg';
$btnSec = 'inline-flex items-center gap-1.5 bg-slate-700 hover:bg-slate-600 text-slate-100 text-xs font-bold px-3 py-1.5 rounded-lg';
$card = 'bg-slate-800/50 border border-slate-700 rounded-2xl p-6';
$hid = static fn (string $acao, string $ancora, string $volta = '') => '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="acao" value="' . e($acao) . '"><input type="hidden" name="ancora" value="' . e($ancora) . '"><input type="hidden" name="volta" value="' . e($volta) . '">';
$taxa = $stats && $stats['avaliados'] ? round($stats['indexaveis'] / $stats['avaliados'] * 100, 1) : null;
?>
<header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2 font-black tracking-tight text-white">
            <span class="material-symbols-outlined text-sky-400">travel_explore</span> SEO · Novare
        </div>
        <div class="flex items-center gap-3 text-xs">
            <a href="/settings-admin" class="text-slate-300 hover:text-white flex items-center gap-1"><span class="material-symbols-outlined text-sm">tune</span> Painel</a>
            <a href="/" target="_blank" class="text-slate-300 hover:text-white flex items-center gap-1"><span class="material-symbols-outlined text-sm">open_in_new</span> Ver site</a>
            <a href="/settings-admin/logout" class="text-red-300 hover:text-red-200 flex items-center gap-1"><span class="material-symbols-outlined text-sm">logout</span> Sair</a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-8 space-y-10">
    <?php if ($flash): ?>
        <div class="rounded-xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 text-sm px-4 py-3 flex items-center gap-2 break-all">
            <span class="material-symbols-outlined text-base">check_circle</span> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 text-[11px] font-bold">
        <?php foreach (['portao' => 'Portão', 'operacional' => 'Operacional', 'entidade' => 'Empresa', 'produto' => 'Produto', 'categorias' => 'Categorias', 'faq' => 'FAQ', 'ocasioes' => 'Ocasiões', 'facetas' => 'Facetas', 'redirects' => 'Redirects', 'indexnow' => 'IndexNow', 'imagens' => 'Imagens'] as $a => $r): ?>
            <a href="#<?= $a ?>" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300"><?= $r ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- ============ PORTÃO DE QUALIDADE ============ -->
    <section id="portao" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">verified</span> Portão de qualidade</h2>
        <p class="text-xs text-slate-400 mb-4">Decide quais produtos merecem o índice do Google. Desligado, só reporta (todos os produtos com imagem seguem indexáveis). Ligado, produto reprovado vira <code>noindex,follow</code> e sai do sitemap. <strong class="text-amber-300">Não ligue antes de ter conteúdo próprio: hoje a taxa de aprovação é o número abaixo.</strong></p>
        <?php if ($stats): ?>
            <div class="grid sm:grid-cols-3 gap-3 mb-4">
                <div class="bg-slate-900 rounded-xl p-4"><div class="text-[10px] uppercase text-slate-500 font-bold">Avaliados</div><div class="text-2xl font-black text-white"><?= (int) $stats['avaliados'] ?></div></div>
                <div class="bg-slate-900 rounded-xl p-4"><div class="text-[10px] uppercase text-slate-500 font-bold">Aprovados</div><div class="text-2xl font-black <?= $taxa >= 40 ? 'text-emerald-400' : 'text-amber-300' ?>"><?= (int) $stats['indexaveis'] ?> <span class="text-sm font-bold">(<?= $taxa ?>%)</span></div></div>
                <div class="bg-slate-900 rounded-xl p-4"><div class="text-[10px] uppercase text-slate-500 font-bold">Última avaliação</div><div class="text-sm font-bold text-slate-200"><?= e($stats['em'] ?? '') ?></div></div>
            </div>
            <div class="text-xs text-slate-300 mb-4">
                <div class="font-bold text-slate-400 uppercase text-[10px] mb-1">Gargalos (o dado escolhe onde investir, não o palpite)</div>
                <?php foreach ($stats['motivos'] ?? [] as $m => $n): ?>
                    <div class="flex justify-between border-b border-slate-800 py-1"><span class="font-mono"><?= e($m) ?></span><span class="font-bold"><?= (int) $n ?></span></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-xs text-amber-300 mb-4">Ainda não avaliado. Clique em "Reavaliar agora".</p>
        <?php endif; ?>
        <div class="flex flex-wrap gap-3 items-center">
            <form method="post" action="/settings-admin/seo"><?= $hid('reavaliar', 'portao') ?><button class="<?= $btnSec ?>"><span class="material-symbols-outlined text-sm">refresh</span> Reavaliar agora</button></form>
            <form method="post" action="/settings-admin/seo" class="flex items-center gap-2"><?= $hid('portao', 'portao') ?>
                <label class="flex items-center gap-2 text-xs text-slate-200"><input type="checkbox" name="ativo" value="1" <?= $portao_ativo ? 'checked' : '' ?> class="rounded bg-slate-900 border-slate-600 text-sky-500"> Portão ATIVO (controla o índice)</label>
                <button class="<?= $btn ?>">Salvar</button>
            </form>
        </div>
    </section>

    <!-- ============ OPERACIONAL ============ -->
    <section id="operacional" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">schedule</span> Dados operacionais (padrão para todos os produtos)</h2>
        <p class="text-xs text-slate-400 mb-4">Aparecem no bloco de decisão de cada produto, no <code>llms.txt</code> e no schema. Cada produto pode sobrescrever. Vazio = não aparece (nunca inventamos dado).</p>
        <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-3 gap-3"><?= $hid('operacional', 'operacional') ?>
            <label class="text-xs text-slate-300">Prazo de produção<input name="prazo" value="<?= e($operacional['prazo'] ?? '') ?>" placeholder="ex.: 10 a 15 dias úteis" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Técnicas de personalização<input name="tecnicas" value="<?= e($operacional['tecnicas'] ?? '') ?>" placeholder="ex.: silk-screen, laser, UV, bordado" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Área de impressão (padrão)<input name="area" value="<?= e($operacional['area'] ?? '') ?>" placeholder="ex.: conforme o produto" class="<?= $inp ?> mt-1"></label>
            <div class="sm:col-span-3"><button class="<?= $btn ?>">Salvar</button></div>
        </form>
    </section>

    <!-- ============ ENTIDADE ============ -->
    <section id="entidade" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">apartment</span> Empresa (entidade)</h2>
        <p class="text-xs text-slate-400 mb-4">Vai para o schema <code>Organization</code> e para o rodapé. Razão social, CNPJ e endereço idênticos em todos os lugares, caractere por caractere. Perfis = uma URL por linha (Instagram, LinkedIn, Google Business).</p>
        <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-3 gap-3"><?= $hid('entidade', 'entidade') ?>
            <label class="text-xs text-slate-300 sm:col-span-2">Razão social<input name="razao_social" value="<?= e($entidade['razao_social'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">CNPJ<input name="cnpj" value="<?= e($entidade['cnpj'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300 sm:col-span-3">Endereço (rua, número, bairro)<input name="endereco" value="<?= e($entidade['endereco'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Cidade<input name="cidade" value="<?= e($entidade['cidade'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">UF<input name="uf" maxlength="2" value="<?= e($entidade['uf'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">CEP<input name="cep" value="<?= e($entidade['cep'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300 sm:col-span-3">Perfis oficiais (sameAs), um por linha<textarea name="perfis" rows="3" class="<?= $inp ?> mt-1"><?= e($entidade['perfis'] ?? '') ?></textarea></label>
            <div class="sm:col-span-3"><button class="<?= $btn ?>">Salvar</button></div>
        </form>
    </section>

    <!-- ============ PRODUTO ============ -->
    <section id="produto" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">edit_note</span> Conteúdo próprio por produto</h2>
        <p class="text-xs text-slate-400 mb-4">Comece pelos 200 produtos de maior demanda (termos de busca das campanhas). Descrição própria com 60+ palavras, composta a partir de dado real (material, uso, técnica, mínimo). Texto genérico com o nome trocado é duplicata com máscara.</p>
        <form method="get" action="/settings-admin/seo" class="flex gap-2 mb-5">
            <input name="sku" value="<?= e($sku_busca) ?>" placeholder="SKU (ex.: 15016) ou slug" class="<?= $inp ?> max-w-xs">
            <button class="<?= $btnSec ?>"><span class="material-symbols-outlined text-sm">search</span> Buscar</button>
        </form>
        <?php if ($sku_busca !== '' && !$produto): ?>
            <p class="text-xs text-red-300">Produto não encontrado.</p>
        <?php elseif ($produto): ?>
            <div class="flex items-start gap-4 mb-4">
                <?php if (!empty($produto['imagem_principal'])): ?><img src="<?= e($produto['imagem_local'] ?: $produto['imagem_principal']) ?>" class="w-20 h-20 object-contain bg-white rounded-lg" alt=""><?php endif; ?>
                <div class="text-xs text-slate-300">
                    <div class="text-base font-black text-white"><?= e(Seo::nomeLegivel($produto['nome'])) ?></div>
                    <div>SKU <?= e($produto['sku_pai']) ?> · <?= e($produto['categoria'] ?? '') ?> · <?= e($produto['material'] ?? '—') ?> · mín. <?= (int) $produto['quantidade_minima'] ?> un. · <?= (int) $produto['imagens'] ?> imagens</div>
                    <div class="mt-1"><a class="text-sky-400 hover:underline" target="_blank" href="<?= e(Seo::urlProduto($produto)) ?>"><?= e(Seo::urlProduto($produto)) ?></a></div>
                    <div class="mt-2 font-bold <?= $produto['avaliacao']['indexavel'] ? 'text-emerald-400' : 'text-amber-300' ?>">Portão: <?= $produto['avaliacao']['indexavel'] ? 'APROVADO' : 'reprovado — ' . e($produto['avaliacao']['motivo']) ?></div>
                </div>
            </div>
            <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-3 gap-3"><?= $hid('produto', 'produto', 'sku=' . rawurlencode($sku_busca)) ?>
                <input type="hidden" name="id" value="<?= (int) $produto['id'] ?>">
                <label class="text-xs text-slate-300 sm:col-span-3">Descrição própria (vem ANTES da descrição da XBZ; mínimo 60 palavras para passar no portão)
                    <textarea name="descricao_propria" rows="7" class="<?= $inp ?> mt-1"><?= e($produto['descricao_propria'] ?? '') ?></textarea></label>
                <label class="text-xs text-slate-300 sm:col-span-2">Title (máx. 60; vazio = "<?= e(Seo::nomeLegivel($produto['nome'])) ?> Personalizado")<input name="seo_title" maxlength="70" value="<?= e($produto['seo_title'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                <label class="text-xs text-slate-300">Prazo (override)<input name="prazo_producao" value="<?= e($produto['prazo_producao'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                <label class="text-xs text-slate-300 sm:col-span-3">Meta description (máx. 158)<input name="seo_description" maxlength="180" value="<?= e($produto['seo_description'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                <label class="text-xs text-slate-300 sm:col-span-2">Técnicas (override)<input name="tecnicas_personalizacao" value="<?= e($produto['tecnicas_personalizacao'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                <label class="text-xs text-slate-300">Área de impressão<input name="area_impressao" value="<?= e($produto['area_impressao'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                <div class="sm:col-span-3"><button class="<?= $btn ?>">Salvar produto</button></div>
            </form>
            <div class="mt-5 text-xs text-slate-400">FAQ deste produto: use a seção <a href="#faq" class="text-sky-400">FAQ</a> com entidade <code>produto</code> e id <code><?= e($produto['sku_pai']) ?></code>.</div>
        <?php endif; ?>
    </section>

    <!-- ============ CATEGORIAS ============ -->
    <section id="categorias" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">category</span> Categorias</h2>
        <p class="text-xs text-slate-400 mb-4">Categoria é onde está o volume de busca comercial. Intro própria de 80–150 palavras com dado real. Categoria com menos de 8 produtos fica noindex automaticamente.</p>
        <div class="space-y-3">
            <?php foreach ($categorias as $c): ?>
                <details class="bg-slate-900 rounded-xl border border-slate-800">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-slate-100 flex justify-between items-center">
                        <span><?= e($c['nome']) ?> <span class="text-slate-500 font-normal">/brindes/<?= e($c['slug']) ?> · <?= (int) $c['total'] ?> produtos</span></span>
                        <span class="text-[10px] font-bold <?= $c['seo_indexavel'] && $c['total'] >= 8 ? 'text-emerald-400' : 'text-amber-300' ?>"><?= $c['seo_indexavel'] && $c['total'] >= 8 ? 'indexável' : 'noindex' ?><?= trim((string) $c['intro_seo']) === '' ? ' · sem intro' : '' ?></span>
                    </summary>
                    <form method="post" action="/settings-admin/seo" class="p-4 grid sm:grid-cols-2 gap-3 border-t border-slate-800"><?= $hid('categoria', 'categorias') ?>
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <label class="text-xs text-slate-300 sm:col-span-2">Intro própria<textarea name="intro_seo" rows="5" class="<?= $inp ?> mt-1"><?= e($c['intro_seo'] ?? '') ?></textarea></label>
                        <label class="text-xs text-slate-300">Rótulo SEO (title/H1, gênero certo)<input name="rotulo_seo" maxlength="120" value="<?= e($c['rotulo_seo'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                        <label class="text-xs text-slate-300">Title (vazio = rótulo SEO)<input name="seo_title" maxlength="70" value="<?= e($c['seo_title'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                        <label class="text-xs text-slate-300">Meta description<input name="seo_description" maxlength="180" value="<?= e($c['seo_description'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
                        <label class="flex items-center gap-2 text-xs text-slate-200"><input type="checkbox" name="seo_indexavel" value="1" <?= $c['seo_indexavel'] ? 'checked' : '' ?> class="rounded bg-slate-900 border-slate-600 text-sky-500"> Indexável</label>
                        <div class="text-right"><button class="<?= $btn ?>">Salvar</button></div>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section id="faq" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">quiz</span> Perguntas e respostas (AEO/GEO)</h2>
        <p class="text-xs text-slate-400 mb-4">H2 como a pergunta real, resposta completa em 40–60 palavras. Repita o sujeito ("a Novare Brindes", "a caneca de bambu"), nunca "nós"/"esse produto". Melhor fonte: perguntas reais do WhatsApp. Entidade <code>global</code> aparece na home/sobre/catálogo e como fallback nos produtos.</p>
        <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-4 gap-3 mb-6"><?= $hid('faq_add', 'faq') ?>
            <label class="text-xs text-slate-300">Entidade<select name="entidade" class="<?= $inp ?> mt-1"><?php foreach (['global', 'produto', 'categoria', 'ocasiao', 'faceta'] as $en): ?><option value="<?= $en ?>"><?= $en ?></option><?php endforeach; ?></select></label>
            <label class="text-xs text-slate-300">ID (SKU / slug categoria / slug ocasião)<input name="entidade_id" class="<?= $inp ?> mt-1" placeholder="vazio para global"></label>
            <label class="text-xs text-slate-300">Ordem<input name="ordem" type="number" value="0" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300 sm:col-span-4">Pergunta<input name="pergunta" maxlength="255" class="<?= $inp ?> mt-1" required></label>
            <label class="text-xs text-slate-300 sm:col-span-4">Resposta<textarea name="resposta" rows="3" class="<?= $inp ?> mt-1" required></textarea></label>
            <div class="sm:col-span-4"><button class="<?= $btn ?>">Adicionar pergunta</button></div>
        </form>
        <div class="space-y-2 text-xs">
            <?php if (!$faqs): ?><p class="text-slate-500">Nenhuma pergunta cadastrada — o site usa as 4 perguntas padrão do código.</p><?php endif; ?>
            <?php foreach ($faqs as $f): ?>
                <div class="bg-slate-900 rounded-lg px-4 py-3 flex justify-between gap-3">
                    <div><span class="text-[10px] font-bold uppercase text-sky-400"><?= e($f['entidade']) ?><?= $f['entidade_id'] ? ' · ' . e($f['entidade_id']) : '' ?></span><div class="font-bold text-slate-100"><?= e($f['pergunta']) ?></div><div class="text-slate-400"><?= e(mb_substr($f['resposta'], 0, 160)) ?>…</div></div>
                    <form method="post" action="/settings-admin/seo"><?= $hid('faq_del', 'faq') ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="text-red-300 hover:text-red-200" title="Remover"><span class="material-symbols-outlined text-base">delete</span></button></form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============ OCASIÕES ============ -->
    <section id="ocasioes" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">celebration</span> Landings de ocasião e segmento</h2>
        <p class="text-xs text-slate-400 mb-4">Onde a concorrência é fraca e a intenção é alta (nenhum revendedor cobre, porque não vem no catálogo do fornecedor). Contrato mínimo: intro de 120–200 palavras, 8–15 SKUs curados, 4–6 FAQ. URL = <code>/{slug}</code>. Sugestões: brindes-para-sipat, brindes-para-cipa, kit-boas-vindas-novo-colaborador, brindes-de-fim-de-ano-para-empresas, brindes-para-feira-e-evento, brindes-sustentaveis-para-empresas, kit-home-office-personalizado.</p>
        <?php $edit = null; foreach ($ocasioes as $o) { if ((string) $o['id'] === (string) q('ocasiao_id', '')) { $edit = $o; } } ?>
        <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-3 gap-3 mb-6"><?= $hid('ocasiao_salvar', 'ocasioes') ?>
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label class="text-xs text-slate-300">Slug (URL)<input name="slug" value="<?= e($edit['slug'] ?? '') ?>" placeholder="brindes-para-sipat" class="<?= $inp ?> mt-1" required></label>
            <label class="text-xs text-slate-300">Título (menu/links)<input name="titulo" value="<?= e($edit['titulo'] ?? '') ?>" placeholder="Brindes para SIPAT" class="<?= $inp ?> mt-1" required></label>
            <label class="text-xs text-slate-300">H1<input name="h1" value="<?= e($edit['h1'] ?? '') ?>" placeholder="Brindes para SIPAT: kits que engajam" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300 sm:col-span-2">Title<input name="seo_title" maxlength="70" value="<?= e($edit['seo_title'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Meta description<input name="seo_description" maxlength="180" value="<?= e($edit['seo_description'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300 sm:col-span-3">Intro (120–200 palavras)<textarea name="intro" rows="4" class="<?= $inp ?> mt-1" required><?= e($edit['intro'] ?? '') ?></textarea></label>
            <label class="text-xs text-slate-300 sm:col-span-3">Corpo (HTML simples: h2, p, ul, table)<textarea name="corpo" rows="6" class="<?= $inp ?> mt-1"><?= e($edit['corpo'] ?? '') ?></textarea></label>
            <label class="text-xs text-slate-300 sm:col-span-2">SKUs curados (separados por vírgula, 8–15)<input name="produtos_skus" value="<?= e($edit['produtos_skus'] ?? '') ?>" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Categorias relacionadas (slugs, vírgula)<input name="categorias" value="<?= e($edit['categorias'] ?? '') ?>" placeholder="canetas,kits-e-conjuntos" class="<?= $inp ?> mt-1"></label>
            <label class="flex items-center gap-2 text-xs text-slate-200"><input type="checkbox" name="seo_indexavel" value="1" <?= !empty($edit['seo_indexavel']) ? 'checked' : '' ?> class="rounded bg-slate-900 border-slate-600 text-sky-500"> Indexável (precisa de 4+ produtos)</label>
            <div class="sm:col-span-2 text-right"><button class="<?= $btn ?>"><?= $edit ? 'Salvar alterações' : 'Criar landing' ?></button> <?php if ($edit): ?><a href="/settings-admin/seo#ocasioes" class="<?= $btnSec ?>">Cancelar</a><?php endif; ?></div>
        </form>
        <div class="space-y-2 text-xs">
            <?php foreach ($ocasioes as $o): ?>
                <div class="bg-slate-900 rounded-lg px-4 py-3 flex justify-between gap-3 items-center">
                    <div><span class="font-bold text-slate-100"><?= e($o['titulo']) ?></span> <a class="text-sky-400" target="_blank" href="/<?= e($o['slug']) ?>">/<?= e($o['slug']) ?></a> <span class="text-slate-500">· <?= $o['seo_indexavel'] ? 'indexável' : 'noindex' ?> · <?= count(array_filter(explode(',', (string) $o['produtos_skus']))) ?> SKUs</span></div>
                    <div class="flex gap-2">
                        <a href="/settings-admin/seo?ocasiao_id=<?= (int) $o['id'] ?>#ocasioes" class="<?= $btnSec ?>">Editar</a>
                        <form method="post" action="/settings-admin/seo" onsubmit="return confirm('Remover esta landing?')"><?= $hid('ocasiao_del', 'ocasioes') ?><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><button class="text-red-300 hover:text-red-200"><span class="material-symbols-outlined text-base">delete</span></button></form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============ FACETAS ============ -->
    <section id="facetas" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">filter_alt</span> Facetas promovidas (categoria × material)</h2>
        <p class="text-xs text-slate-400 mb-4">Só vira URL indexável (<code>/brindes/canecas/material/ceramica</code>) combinação com demanda comprovada, 8+ produtos e intro própria. O resto continua como filtro livre (noindex).</p>
        <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-4 gap-3 mb-6"><?= $hid('faceta_salvar', 'facetas') ?>
            <label class="text-xs text-slate-300">Categoria<select name="categoria_slug" class="<?= $inp ?> mt-1"><?php foreach ($categorias as $c): ?><option value="<?= e($c['slug']) ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select></label>
            <label class="text-xs text-slate-300">Material (valor do banco)<select name="valor" class="<?= $inp ?> mt-1"><?php foreach ($materiais as $m): ?><option value="<?= e($m['material']) ?>"><?= e($m['material']) ?> (<?= (int) $m['n'] ?>)</option><?php endforeach; ?></select></label>
            <label class="text-xs text-slate-300">Rótulo<input name="valor_label" placeholder="ex.: Porcelana" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Volume de busca/mês<input name="volume_busca" type="number" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300 sm:col-span-3">Intro própria (80+ palavras)<textarea name="intro_seo" rows="3" class="<?= $inp ?> mt-1"></textarea></label>
            <label class="flex items-center gap-2 text-xs text-slate-200"><input type="checkbox" name="seo_indexavel" value="1" class="rounded bg-slate-900 border-slate-600 text-sky-500"> Indexável</label>
            <div class="sm:col-span-4"><button class="<?= $btn ?>">Salvar faceta</button></div>
        </form>
        <div class="space-y-2 text-xs">
            <?php foreach ($facetas as $f): ?>
                <div class="bg-slate-900 rounded-lg px-4 py-3 flex justify-between gap-3 items-center">
                    <div><a class="text-sky-400" target="_blank" href="/brindes/<?= e($f['categoria_slug']) ?>/material/<?= e($f['valor_slug']) ?>">/brindes/<?= e($f['categoria_slug']) ?>/material/<?= e($f['valor_slug']) ?></a> <span class="text-slate-500">· <?= e($f['valor_label']) ?> · <?= $f['seo_indexavel'] ? 'indexável' : 'noindex' ?><?= $f['volume_busca'] ? ' · ' . (int) $f['volume_busca'] . '/mês' : '' ?></span></div>
                    <form method="post" action="/settings-admin/seo"><?= $hid('faceta_del', 'facetas') ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="text-red-300 hover:text-red-200"><span class="material-symbols-outlined text-base">delete</span></button></form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============ REDIRECTS ============ -->
    <section id="redirects" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">alt_route</span> Redirects 301 / 410</h2>
        <p class="text-xs text-slate-400 mb-4">Produto descontinuado vai para o substituto (301), nunca para a home. Se sumiu de vez, 410. As URLs antigas (<code>/produto/SKU</code>, <code>/catalogo?categoria=</code>) já redirecionam sozinhas.</p>
        <form method="post" action="/settings-admin/seo" class="grid sm:grid-cols-4 gap-3 mb-6"><?= $hid('redirect_add', 'redirects') ?>
            <label class="text-xs text-slate-300 sm:col-span-2">Origem (path)<input name="origem" placeholder="/brindes/produto/caneta-antiga-12345" class="<?= $inp ?> mt-1" required></label>
            <label class="text-xs text-slate-300">Destino<input name="destino" placeholder="/brindes/produto/caneta-nova-67890" class="<?= $inp ?> mt-1"></label>
            <label class="text-xs text-slate-300">Status<select name="status" class="<?= $inp ?> mt-1"><option value="301">301</option><option value="302">302</option><option value="410">410 (removido)</option></select></label>
            <div class="sm:col-span-4"><button class="<?= $btn ?>">Salvar redirect</button></div>
        </form>
        <div class="space-y-1 text-xs font-mono">
            <?php foreach ($redirects as $r): ?>
                <div class="bg-slate-900 rounded-lg px-4 py-2 flex justify-between gap-3 items-center">
                    <span class="text-slate-300"><?= e($r['origem']) ?> → <?= e($r['destino'] ?? '(410)') ?> <span class="text-slate-500">[<?= (int) $r['status'] ?>]</span></span>
                    <form method="post" action="/settings-admin/seo"><?= $hid('redirect_del', 'redirects') ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="text-red-300 hover:text-red-200"><span class="material-symbols-outlined text-base">delete</span></button></form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============ INDEXNOW ============ -->
    <section id="indexnow" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">bolt</span> IndexNow (Bing, Yandex, Seznam)</h2>
        <p class="text-xs text-slate-400 mb-3">Chave gerada automaticamente e publicada em <a class="text-sky-400" target="_blank" href="/<?= e($indexnow_key) ?>.txt">/<?= e($indexnow_key) ?>.txt</a>. A sync da XBZ já envia sozinha o que mudou. Último envio: <span class="text-slate-200"><?= $indexnow_ultimo ? e(($indexnow_ultimo['em'] ?? '') . ' · ' . ($indexnow_ultimo['urls'] ?? 0) . ' URLs · ' . json_encode($indexnow_ultimo['lotes'] ?? [])) : 'nunca' ?></span></p>
        <form method="post" action="/settings-admin/seo"><?= $hid('indexnow', 'indexnow') ?><button class="<?= $btnSec ?>"><span class="material-symbols-outlined text-sm">send</span> Enviar alterações dos últimos 7 dias</button></form>
    </section>

    <!-- ============ IMAGENS ============ -->
    <section id="imagens" class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">image</span> Imagens locais (WebP no nosso domínio)</h2>
        <p class="text-xs text-slate-400 mb-3">Imagem puxada do domínio da XBZ atrasa o LCP e não entra no Google Imagens. Cada clique converte um lote de 40 produtos. <?= $imagens['gd'] ? '' : '<strong class="text-red-300">GD/WebP indisponível neste PHP — ative a extensão gd no hPanel.</strong>' ?></p>
        <div class="flex items-center gap-4">
            <div class="text-xs text-slate-300"><strong class="text-emerald-400"><?= (int) $imagens['feitas'] ?></strong> convertidas · <strong class="text-amber-300"><?= (int) $imagens['pendentes'] ?></strong> pendentes</div>
            <form method="post" action="/settings-admin/seo"><?= $hid('imagens', 'imagens') ?><button class="<?= $btn ?>" <?= $imagens['gd'] ? '' : 'disabled' ?>><span class="material-symbols-outlined text-sm">play_arrow</span> Processar próximo lote</button></form>
        </div>
    </section>
</main>
