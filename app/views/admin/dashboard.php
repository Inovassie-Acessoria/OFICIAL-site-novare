<?php
/**
 * Painel admin — seções na MESMA ordem do site.
 * @var string $csrf
 * @var string|null $flash
 * @var string $logo
 * @var array  $banners
 * @var array  $cats     map CATEGORIA => url atual
 * @var array  $tops     map chave => ['rotulo'=>..., 'produtos'=>[...]]
 * @var string $iaPersona
 * @var array  $iaArquivos
 * @var array  $regras
 */
$enc = static fn ($v) => json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Normaliza os produtos dos tops para o JS (sku, nome, imagem).
$topsJs = [];
foreach ($tops as $chave => $info) {
    $topsJs[$chave] = array_map(static fn ($p) => [
        'sku'    => $p['sku_pai'],
        'nome'   => $p['nome'],
        'imagem' => $p['imagem_principal'] ?? '',
    ], $info['produtos']);
}
?>
<!-- Barra superior -->
<header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2 font-black tracking-tight text-white">
            <span class="material-symbols-outlined text-sky-400">tune</span> Painel Novare
        </div>
        <div class="flex items-center gap-3 text-xs">
            <span class="hidden sm:inline text-slate-400"><?= e(AdminAuth::email()) ?></span>
            <a href="/" target="_blank" class="text-slate-300 hover:text-white flex items-center gap-1"><span class="material-symbols-outlined text-sm">open_in_new</span> Ver site</a>
            <a href="/settings-admin/logout" class="text-red-300 hover:text-red-200 flex items-center gap-1"><span class="material-symbols-outlined text-sm">logout</span> Sair</a>
        </div>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-8 space-y-10">
    <?php if ($flash): ?>
        <div id="flash" class="rounded-xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 text-sm px-4 py-3 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <!-- Índice -->
    <nav class="flex flex-wrap gap-2 text-[11px] font-bold">
        <a href="#logo" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Logo</a>
        <a href="#banners" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Banners</a>
        <a href="#categorias" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Categorias</a>
        <a href="#top_canetas" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Top Canetas</a>
        <a href="#top_cadernos" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Top Cadernos</a>
        <a href="#top_garrafas" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Top Garrafas</a>
        <a href="#top_mochilas" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">Top Mochilas</a>
        <a href="#ia" class="px-3 py-1.5 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-300">IA Sophia</a>
    </nav>

    <!-- ============ 1) LOGO ============ -->
    <section id="logo" class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">image</span> Logotipo</h2>
        <p class="text-xs text-slate-400 mb-4">Aparece no cabeçalho e no rodapé. Formato: <strong class="text-slate-300"><?= e($regras['logo']['rec']) ?></strong> (mín. <?= (int)$regras['logo']['min_w'] ?>×<?= (int)$regras['logo']['min_h'] ?> px).</p>
        <form method="post" action="/settings-admin/salvar" class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="secao" value="logo">
            <input type="hidden" name="logo" id="logo-url" value="<?= e($logo) ?>">
            <div class="w-40 h-20 rounded-xl bg-slate-900 border border-slate-700 flex items-center justify-center p-2 overflow-hidden">
                <img id="logo-preview" src="<?= e($logo) ?>" alt="logo" class="max-h-full max-w-full object-contain">
            </div>
            <div class="flex flex-col gap-2">
                <label class="cursor-pointer inline-flex items-center gap-2 text-sm bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg w-fit">
                    <span class="material-symbols-outlined text-base">upload</span> Inserir imagem
                    <input type="file" accept="image/*" class="hidden" data-upload="logo" data-target="#logo-url" data-preview="#logo-preview">
                </label>
                <span class="upload-msg text-[11px] text-slate-400"></span>
            </div>
            <button class="sm:ml-auto bg-sky-500 hover:bg-sky-400 text-white text-sm font-bold px-5 py-2.5 rounded-lg">Salvar logo</button>
        </form>
    </section>

    <!-- ============ 2) BANNERS ============ -->
    <section id="banners" class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">view_carousel</span> Sequência de banners</h2>
        <p class="text-xs text-slate-400 mb-1">Carrossel principal da home. Arraste para reordenar. Formato: <strong class="text-slate-300"><?= e($regras['banner']['rec']) ?></strong> — <strong>mínimo <?= (int)$regras['banner']['min_w'] ?>×<?= (int)$regras['banner']['min_h'] ?> px</strong> (imagens menores são recusadas para manter o site responsivo).</p>
        <form method="post" action="/settings-admin/salvar" id="form-banners">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="secao" value="banners">
            <input type="hidden" name="banners" id="banners-json">
            <div id="banners-list" class="space-y-4 mb-4"></div>
            <div class="flex items-center gap-3">
                <button type="button" id="add-banner" class="inline-flex items-center gap-1 text-sm bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg"><span class="material-symbols-outlined text-base">add</span> Adicionar banner</button>
                <button class="ml-auto bg-sky-500 hover:bg-sky-400 text-white text-sm font-bold px-5 py-2.5 rounded-lg">Salvar banners</button>
            </div>
        </form>
    </section>

    <!-- ============ 3) NAVEGUE PELAS CATEGORIAS ============ -->
    <section id="categorias" class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">category</span> Navegue pelas categorias</h2>
        <p class="text-xs text-slate-400 mb-4">Imagem dos círculos da home. Formato: <strong class="text-slate-300"><?= e($regras['categoria']['rec']) ?></strong> (mín. <?= (int)$regras['categoria']['min_w'] ?>×<?= (int)$regras['categoria']['min_h'] ?> px).</p>
        <form method="post" action="/settings-admin/salvar">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="secao" value="categorias">
            <input type="hidden" name="categorias_imagens" id="cats-json">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                <?php foreach ($cats as $nome => $img): ?>
                    <div class="bg-slate-900 border border-slate-700 rounded-xl p-3 flex flex-col items-center gap-2 text-center" data-cat="<?= e($nome) ?>">
                        <div class="w-16 h-16 rounded-full overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center">
                            <img class="cat-preview w-full h-full object-cover" src="<?= e((string) $img) ?>" alt="<?= e($nome) ?>">
                        </div>
                        <span class="text-[10px] font-bold text-slate-300 leading-tight"><?= e($nome) ?></span>
                        <label class="cursor-pointer text-[10px] text-sky-400 hover:text-sky-300 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">upload</span> Trocar
                            <input type="file" accept="image/*" class="hidden" data-upload="categoria" data-cat-input="<?= e($nome) ?>">
                        </label>
                        <span class="upload-msg text-[10px] text-slate-500"></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="bg-sky-500 hover:bg-sky-400 text-white text-sm font-bold px-5 py-2.5 rounded-lg">Salvar categorias</button>
        </form>
    </section>

    <!-- ============ 4..7) TOPS ============ -->
    <?php foreach (['top_canetas' => 'Top Canetas', 'top_cadernos' => 'Top Cadernos, Agendas & Moleskine', 'top_garrafas' => 'Top Garrafas', 'top_mochilas' => 'Top Mochilas'] as $chave => $rotulo): ?>
        <section id="<?= e($chave) ?>" class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
            <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">trophy</span> <?= e($rotulo) ?></h2>
            <p class="text-xs text-slate-400 mb-4">Puxe um produto pelo SKU e <strong class="text-slate-300">arraste para ordenar</strong> (Top 1, Top 2...). Vazio = a home usa a ordenação automática.</p>
            <form method="post" action="/settings-admin/salvar" data-top-form="<?= e($chave) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="secao" value="<?= e($chave) ?>">
                <input type="hidden" name="skus" class="top-json">
                <div class="flex gap-2 mb-4">
                    <input type="text" class="top-sku-input flex-grow rounded-lg bg-slate-900 border border-slate-700 text-slate-100 text-sm px-3 py-2 focus:ring-2 focus:ring-sky-500/40 outline-none" placeholder="Digite o SKU (ex.: 15150N) e clique em adicionar">
                    <button type="button" class="top-sku-add inline-flex items-center gap-1 bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg"><span class="material-symbols-outlined text-base">add</span> Puxar</button>
                </div>
                <span class="top-msg text-[11px] text-red-300 block mb-2"></span>
                <div class="top-list space-y-2 mb-4" data-top="<?= e($chave) ?>"></div>
                <button class="bg-sky-500 hover:bg-sky-400 text-white text-sm font-bold px-5 py-2.5 rounded-lg">Salvar <?= e($rotulo) ?></button>
            </form>
        </section>
    <?php endforeach; ?>

    <!-- ============ 8) IA ============ -->
    <section id="ia" class="bg-slate-800/50 border border-slate-700 rounded-2xl p-6">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">smart_toy</span> IA Sophia — prompt &amp; conhecimento</h2>
        <p class="text-xs text-slate-400 mb-4">Edite a personalidade/orientação da assistente. O formato de resposta (JSON) é mantido automaticamente pelo sistema. Anexe arquivos de texto (.txt, .md, .csv, .json) para complementar o conhecimento dela.</p>
        <form method="post" action="/settings-admin/salvar">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="secao" value="ia">
            <input type="hidden" name="ia_arquivos" id="ia-json">
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Prompt / comunicação</label>
            <textarea name="ia_prompt" rows="12" class="w-full rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm px-4 py-3 font-mono leading-relaxed focus:ring-2 focus:ring-sky-500/40 outline-none"><?= e($iaPersona) ?></textarea>

            <div class="mt-5">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Arquivos de conhecimento</label>
                <div id="ia-files" class="space-y-2 mb-3"></div>
                <label class="cursor-pointer inline-flex items-center gap-2 text-sm bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg w-fit">
                    <span class="material-symbols-outlined text-base">attach_file</span> Inserir arquivo
                    <input type="file" accept=".txt,.md,.csv,.json" class="hidden" id="ia-upload">
                </label>
                <span id="ia-msg" class="upload-msg text-[11px] text-slate-400 ml-2"></span>
            </div>

            <button class="mt-5 bg-sky-500 hover:bg-sky-400 text-white text-sm font-bold px-5 py-2.5 rounded-lg">Salvar IA</button>
        </form>
    </section>

    <p class="text-center text-[10px] text-slate-600 pt-4">Novare Brindes &copy; <?= date('Y') ?> — painel interno</p>
</main>

<script>
const CSRF = <?= $enc($csrf) ?>;
let BANNERS  = <?= $enc($banners) ?>;
let CATS     = <?= $enc((object) $cats) ?>;
let TOPS     = <?= $enc((object) $topsJs) ?>;
let IA_FILES = <?= $enc(array_values($iaArquivos)) ?>;

/* ---------- API ---------- */
async function apiUpload(file, tipo) {
    const fd = new FormData();
    fd.append('arquivo', file); fd.append('tipo', tipo); fd.append('csrf', CSRF);
    const r = await fetch('/settings-admin/upload', { method: 'POST', body: fd });
    return r.json();
}
async function apiSku(sku) {
    const r = await fetch('/settings-admin/sku?sku=' + encodeURIComponent(sku));
    return r.json();
}
function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

/* ---------- Uploads simples (logo + categorias) ---------- */
document.querySelectorAll('input[type=file][data-upload]').forEach(function (inp) {
    inp.addEventListener('change', async function () {
        if (!inp.files[0]) return;
        const wrap = inp.closest('label') ? inp.closest('label').parentElement : inp.parentElement;
        const msg = wrap.querySelector('.upload-msg');
        if (msg) { msg.textContent = 'Enviando...'; msg.className = 'upload-msg text-[11px] text-slate-400'; }
        const res = await apiUpload(inp.files[0], inp.dataset.upload);
        if (!res.ok) { if (msg) { msg.textContent = res.erro; msg.className = 'upload-msg text-[11px] text-red-300'; } inp.value=''; return; }
        if (msg) { msg.textContent = 'Imagem pronta (' + res.largura + '×' + res.altura + ').'; msg.className = 'upload-msg text-[11px] text-emerald-300'; }
        if (inp.dataset.target) {
            document.querySelector(inp.dataset.target).value = res.url;
            if (inp.dataset.preview) document.querySelector(inp.dataset.preview).src = res.url;
        }
        if (inp.dataset.catInput) {
            CATS[inp.dataset.catInput] = res.url;
            const card = inp.closest('[data-cat]');
            card.querySelector('.cat-preview').src = res.url;
        }
        inp.value = '';
    });
});

/* ---------- Drag & drop genérico (vertical) ---------- */
function getAfter(container, y) {
    const els = [...container.querySelectorAll('.sortable-item:not(.dragging)')];
    return els.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) return { offset, element: child };
        return closest;
    }, { offset: -Infinity }).element;
}
function enableSort(container, onDrop) {
    container.addEventListener('dragover', e => {
        e.preventDefault();
        const dragging = container.querySelector('.dragging');
        if (!dragging) return;
        const after = getAfter(container, e.clientY);
        if (after == null) container.appendChild(dragging); else container.insertBefore(dragging, after);
    });
    container.addEventListener('drop', e => { e.preventDefault(); onDrop && onDrop(); });
}
function wireItem(item, onDrop) {
    item.setAttribute('draggable', 'true');
    item.addEventListener('dragstart', () => item.classList.add('dragging'));
    item.addEventListener('dragend', () => { item.classList.remove('dragging'); onDrop && onDrop(); });
}

/* ---------- BANNERS ---------- */
const bannersList = document.getElementById('banners-list');
function midiaPreviewHtml(url, posX, posY) {
    if (!url) return '<div class="text-[10px] text-slate-500">Sem mídia</div>';
    const ext = url.split('.').pop().toLowerCase();
    const px = posX ?? 50;
    const py = posY ?? 50;
    if (['mp4', 'webm', 'ogg'].includes(ext)) {
        return '<video class="w-full h-full object-cover select-none pointer-events-none" style="object-position: ' + px + '% ' + py + '%;" src="' + esc(url) + '" autoplay muted loop playsinline></video>';
    }
    return '<img class="w-full h-full object-cover select-none pointer-events-none" style="object-position: ' + px + '% ' + py + '%;" src="' + esc(url) + '">';
}
function toggleCamposTexto(el, desabilitar) {
    el.querySelectorAll('[data-f]').forEach(inp => {
        inp.disabled = desabilitar;
        if (desabilitar) {
            inp.classList.add('opacity-40');
        } else {
            inp.classList.remove('opacity-40');
        }
    });
}
function renderBanners() {
    bannersList.innerHTML = '';
    BANNERS.forEach((b, i) => {
        const el = document.createElement('div');
        el.className = 'sortable-item bg-slate-900 border border-slate-700 rounded-xl p-4 flex flex-col sm:flex-row gap-4';
        el.dataset.idx = i;
        el.innerHTML =
            '<div class="flex sm:flex-col items-center gap-2">' +
                '<span class="material-symbols-outlined text-slate-500" title="Arraste">drag_indicator</span>' +
                '<span class="text-[10px] font-black text-sky-400">#' + (i + 1) + '</span>' +
            '</div>' +
            '<div class="bn-preview-wrap w-full sm:w-48 h-24 rounded-lg bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center flex-shrink-0 relative select-none" style="cursor: move;" title="Arraste para enquadrar em qualquer direção">' +
                midiaPreviewHtml(b.imagem, b.pos_x, b.pos_y) +
                '<span class="bn-pos-badge absolute bottom-1.5 right-1.5 bg-slate-900/80 text-[8px] font-mono text-sky-400 px-1 py-0.5 rounded border border-slate-700 pointer-events-none select-none">X: ' + Math.round(b.pos_x ?? 50) + '% Y: ' + Math.round(b.pos_y ?? 50) + '%</span>' +
            '</div>' +
            '<div class="flex-grow grid grid-cols-1 sm:grid-cols-2 gap-2">' +
                inputF('Etiqueta', 'tag', b.tag) +
                inputF('Título', 'titulo', b.titulo) +
                '<div class="sm:col-span-2">' + areaF('Subtítulo', 'subtitulo', b.subtitulo) + '</div>' +
                inputF('Texto do botão', 'cta_texto', b.cta_texto) +
                inputF('Link do botão', 'cta_link', b.cta_link) +
                inputNumF('Duração (segundos)', 'duracao', b.duracao ?? 5) +
                '<div class="sm:col-span-2 flex items-center gap-2 mt-1">' +
                    '<input type="checkbox" id="bn-sem-texto-' + i + '" class="bn-sem-texto rounded border-slate-700 bg-slate-800 text-sky-500 focus:ring-sky-500/40" ' + (b.sem_texto ? 'checked' : '') + '>' +
                    '<label for="bn-sem-texto-' + i + '" class="text-[10px] font-bold text-slate-400 select-none cursor-pointer">Sem textos ou link (exibe apenas a imagem/vídeo limpo)</label>' +
                '</div>' +
                '<div class="sm:col-span-2 flex items-center gap-3 mt-1">' +
                    '<label class="cursor-pointer inline-flex items-center gap-1 text-[11px] bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded-lg"><span class="material-symbols-outlined text-sm">upload</span> Inserir arquivo<input type="file" accept="image/*,video/*" class="hidden bn-file"></label>' +
                    '<span class="bn-msg text-[10px] text-slate-400"></span>' +
                    '<button type="button" class="bn-del ml-auto text-red-300 hover:text-red-200 text-[11px] inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">delete</span> Remover</button>' +
                '</div>' +
            '</div>';
        // bind fields
        el.querySelectorAll('[data-f]').forEach(f => f.addEventListener('input', () => {
            BANNERS[i][f.dataset.f] = f.dataset.f === 'duracao' ? (parseInt(f.value) || 5) : f.value;
        }));
        el.querySelector('.bn-del').addEventListener('click', () => { BANNERS.splice(i, 1); renderBanners(); });

        const chk = el.querySelector('.bn-sem-texto');
        chk.addEventListener('change', () => {
            BANNERS[i].sem_texto = chk.checked;
            toggleCamposTexto(el, chk.checked);
        });
        toggleCamposTexto(el, !!b.sem_texto);

        // Funcionalidade de enquadramento arrastável bidirecional (drag X e Y)
        const preview = el.querySelector('.bn-preview-wrap');
        const media = preview.querySelector('img, video');
        if (media) {
            let isDragging = false;
            let startX = 0;
            let startY = 0;
            let startPosX = b.pos_x ?? 50;
            let startPosY = b.pos_y ?? 50;

            const onStart = (clientX, clientY) => {
                isDragging = true;
                startX = clientX;
                startY = clientY;
                startPosX = BANNERS[i].pos_x ?? 50;
                startPosY = BANNERS[i].pos_y ?? 50;
            };

            const onMove = (clientX, clientY) => {
                if (!isDragging) return;
                const deltaX = clientX - startX;
                const deltaY = clientY - startY;
                const rect = preview.getBoundingClientRect();
                const width = rect.width || 192;
                const height = rect.height || 96;

                // Arraste horizontal
                let novaPosX = startPosX - (deltaX / width) * 100;
                novaPosX = Math.max(0, Math.min(100, novaPosX));
                BANNERS[i].pos_x = novaPosX;

                // Arraste vertical
                let novaPosY = startPosY - (deltaY / height) * 100;
                novaPosY = Math.max(0, Math.min(100, novaPosY));
                BANNERS[i].pos_y = novaPosY;

                media.style.objectPosition = novaPosX + '% ' + novaPosY + '%';

                const badge = preview.querySelector('.bn-pos-badge');
                if (badge) badge.textContent = 'X: ' + Math.round(novaPosX) + '% Y: ' + Math.round(novaPosY) + '%';
            };

            const onEnd = () => { isDragging = false; };

            preview.addEventListener('mousedown', e => { onStart(e.clientX, e.clientY); e.preventDefault(); });
            window.addEventListener('mousemove', e => onMove(e.clientX, e.clientY));
            window.addEventListener('mouseup', onEnd);

            preview.addEventListener('touchstart', e => onStart(e.touches[0].clientX, e.touches[0].clientY));
            preview.addEventListener('touchmove', e => { onMove(e.touches[0].clientX, e.touches[0].clientY); e.preventDefault(); }, { passive: false });
            preview.addEventListener('touchend', onEnd);
        }

        el.querySelector('.bn-file').addEventListener('change', async function () {
            if (!this.files[0]) return;
            const msg = el.querySelector('.bn-msg'); msg.textContent = 'Enviando...'; msg.className='bn-msg text-[10px] text-slate-400';
            const res = await apiUpload(this.files[0], 'banner');
            if (!res.ok) { msg.textContent = res.erro; msg.className='bn-msg text-[10px] text-red-300'; return; }
            BANNERS[i].imagem = res.url;
            BANNERS[i].pos_x = 50; // reseta
            BANNERS[i].pos_y = 50; // reseta
            renderBanners();
        });
        wireItem(el, syncBannersOrder);
        bannersList.appendChild(el);
    });
}
function inputF(label, key, val) {
    return '<label class="block"><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">' + label + '</span>' +
        '<input data-f="' + key + '" value="' + esc(val) + '" class="w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5 outline-none focus:ring-1 focus:ring-sky-500/40"></label>';
}
function inputNumF(label, key, val) {
    return '<label class="block"><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">' + label + '</span>' +
        '<input type="number" min="1" step="1" data-f="' + key + '" value="' + esc(val) + '" class="w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5 outline-none focus:ring-1 focus:ring-sky-500/40"></label>';
}
function areaF(label, key, val) {
    return '<label class="block"><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">' + label + '</span>' +
        '<textarea data-f="' + key + '" rows="2" class="w-full rounded-lg bg-slate-800 border border-slate-700 text-slate-100 text-xs px-2.5 py-1.5 outline-none focus:ring-1 focus:ring-sky-500/40">' + esc(val) + '</textarea></label>';
}
function syncBannersOrder() {
    const order = [...bannersList.querySelectorAll('.sortable-item')].map(el => BANNERS[+el.dataset.idx]);
    BANNERS = order; renderBanners();
}
document.getElementById('add-banner').addEventListener('click', () => {
    BANNERS.push({ imagem: '', tag: '', titulo: '', subtitulo: '', cta_texto: 'Ver produtos', cta_link: '/catalogo', duracao: 5 });
    renderBanners();
});
enableSort(bannersList, syncBannersOrder);
document.getElementById('form-banners').addEventListener('submit', () => {
    document.getElementById('banners-json').value = JSON.stringify(BANNERS);
});
renderBanners();

/* ---------- CATEGORIAS (serializa no submit) ---------- */
document.querySelector('#categorias form').addEventListener('submit', () => {
    document.getElementById('cats-json').value = JSON.stringify(CATS);
});

/* ---------- TOPS ---------- */
document.querySelectorAll('[data-top]').forEach(function (listEl) {
    const chave = listEl.dataset.top;
    const form = listEl.closest('form');
    const jsonInp = form.querySelector('.top-json');
    const msgEl = form.querySelector('.top-msg');

    function render() {
        listEl.innerHTML = '';
        (TOPS[chave] || []).forEach((p, i) => {
            const el = document.createElement('div');
            el.className = 'sortable-item bg-slate-900 border border-slate-700 rounded-xl p-2.5 flex items-center gap-3';
            el.dataset.sku = p.sku;
            el.innerHTML =
                '<span class="material-symbols-outlined text-slate-500" title="Arraste">drag_indicator</span>' +
                '<span class="text-[10px] font-black text-sky-400 w-10">Top ' + (i + 1) + '</span>' +
                '<img src="' + esc(p.imagem) + '" class="w-10 h-10 rounded-lg object-contain bg-slate-800 border border-slate-700">' +
                '<div class="flex-grow min-w-0"><strong class="block text-xs text-slate-100 truncate">' + esc(p.nome) + '</strong><span class="text-[10px] text-slate-500">SKU: ' + esc(p.sku) + '</span></div>' +
                '<button type="button" class="rm text-red-300 hover:text-red-200"><span class="material-symbols-outlined text-base">close</span></button>';
            el.querySelector('.rm').addEventListener('click', () => { TOPS[chave].splice(i, 1); render(); });
            wireItem(el, syncOrder);
            listEl.appendChild(el);
        });
    }
    function syncOrder() {
        const order = [...listEl.querySelectorAll('.sortable-item')].map(el => el.dataset.sku);
        TOPS[chave] = order.map(sku => (TOPS[chave] || []).find(p => p.sku === sku)).filter(Boolean);
        render();
    }
    enableSort(listEl, syncOrder);

    const input = form.querySelector('.top-sku-input');
    async function add() {
        const sku = input.value.trim();
        if (!sku) return;
        msgEl.textContent = 'Buscando...'; msgEl.className = 'top-msg text-[11px] text-slate-400 block mb-2';
        const res = await apiSku(sku);
        if (!res.ok) { msgEl.textContent = res.erro; msgEl.className = 'top-msg text-[11px] text-red-300 block mb-2'; return; }
        TOPS[chave] = TOPS[chave] || [];
        if (TOPS[chave].some(p => p.sku === res.produto.sku_pai)) { msgEl.textContent = 'Esse produto já está na lista.'; return; }
        TOPS[chave].push({ sku: res.produto.sku_pai, nome: res.produto.nome, imagem: res.produto.imagem });
        input.value = ''; msgEl.textContent = ''; render();
    }
    form.querySelector('.top-sku-add').addEventListener('click', add);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); add(); } });

    form.addEventListener('submit', () => { jsonInp.value = JSON.stringify((TOPS[chave] || []).map(p => p.sku)); });
    render();
});

/* ---------- IA arquivos ---------- */
const iaFilesEl = document.getElementById('ia-files');
function renderIaFiles() {
    iaFilesEl.innerHTML = '';
    IA_FILES.forEach((a, i) => {
        const el = document.createElement('div');
        el.className = 'flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2';
        el.innerHTML = '<span class="material-symbols-outlined text-sky-400 text-base">description</span>' +
            '<span class="flex-grow text-xs text-slate-200 truncate">' + esc(a.nome) + '</span>' +
            '<span class="text-[10px] text-slate-500">' + (a.tipo || '') + '</span>' +
            '<button type="button" class="rm text-red-300 hover:text-red-200"><span class="material-symbols-outlined text-base">close</span></button>';
        el.querySelector('.rm').addEventListener('click', () => { IA_FILES.splice(i, 1); renderIaFiles(); });
        iaFilesEl.appendChild(el);
    });
}
document.getElementById('ia-upload').addEventListener('change', async function () {
    if (!this.files[0]) return;
    const msg = document.getElementById('ia-msg'); msg.textContent = 'Enviando...'; msg.className='upload-msg text-[11px] text-slate-400 ml-2';
    const res = await apiUpload(this.files[0], 'ia');
    if (!res.ok) { msg.textContent = res.erro; msg.className='upload-msg text-[11px] text-red-300 ml-2'; this.value=''; return; }
    IA_FILES.push({ nome: res.nome, arquivo: res.arquivo, tipo: res.tipo, tamanho: res.tamanho });
    msg.textContent = 'Arquivo anexado.'; msg.className='upload-msg text-[11px] text-emerald-300 ml-2'; this.value=''; renderIaFiles();
});
document.querySelector('#ia form').addEventListener('submit', () => { document.getElementById('ia-json').value = JSON.stringify(IA_FILES); });
renderIaFiles();
</script>
