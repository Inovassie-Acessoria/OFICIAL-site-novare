<?php
/**
 * Painel de estoque (/settings-admin/estoque).
 * @var string     $csrf
 * @var array|null $flash      ['msg' => string, 'ok' => bool]
 * @var array|null $pendente   última simulação (simulada, bloqueada ou expirada)
 * @var array|null $previa     mudanças previstas da simulação, por tipo
 * @var int        $aplicada   id recém aplicado (mostra o atalho do relatório)
 * @var array      $historico
 * @var int        $fora_hoje
 * @var array      $trava      percentual, minimo, validade
 */
$btn    = 'inline-flex items-center gap-1.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg';
$btnSec = 'inline-flex items-center gap-1.5 bg-slate-700 hover:bg-slate-600 text-slate-100 text-xs font-bold px-3 py-1.5 rounded-lg';
$card   = 'bg-slate-800/50 border border-slate-700 rounded-2xl p-6';
$inp    = 'w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500';
$hid    = static fn (string $acao, int $id = 0) => '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="acao" value="' . e($acao) . '"><input type="hidden" name="id" value="' . $id . '">';
$dt     = static fn (?string $d) => $d ? date('d/m/Y H:i', strtotime($d)) : '';
$n      = static fn ($v) => number_format((int) $v, 0, ',', '.');
$rotuloStatus = ['simulada' => 'Simulação', 'aplicada' => 'Aplicada', 'descartada' => 'Descartada', 'bloqueada' => 'Bloqueada', 'erro' => 'Erro'];
$expirada = $pendente && !empty($pendente['expirada']);
$podeAplicar = $pendente && $pendente['status'] === 'simulada' && !$expirada;
?>
<style>
    .est-aviso { border-radius: .75rem; padding: .75rem 1rem; font-size: .875rem; display: flex; gap: .5rem; align-items: flex-start; }
    .est-ok    { background: rgba(16,185,129,.15); border: 1px solid rgba(16,185,129,.4); color: #6ee7b7; }
    .est-erro  { background: rgba(239,68,68,.15);  border: 1px solid rgba(239,68,68,.4);  color: #fca5a5; }
    .est-trava { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.45); color: #fcd34d; }
    .est-num   { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    @media (min-width: 640px) { .est-num { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .est-num div { background: #0f172a; border: 1px solid #334155; border-radius: .75rem; padding: .75rem 1rem; }
    .est-num b { display: block; font-size: 1.5rem; font-weight: 900; color: #fff; line-height: 1.2; }
    .est-num span { font-size: .7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
    .est-tab { width: 100%; font-size: .75rem; border-collapse: collapse; }
    .est-tab th { text-align: left; color: #94a3b8; font-weight: 700; padding: .4rem .5rem; border-bottom: 1px solid #334155; white-space: nowrap; }
    .est-tab td { padding: .4rem .5rem; border-bottom: 1px solid #1e293b; color: #e2e8f0; vertical-align: top; }
    .est-scroll { overflow-x: auto; }
    .est-pill { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; background: #334155; color: #e2e8f0; }
    .est-pill.aplicada { background: #065f46; color: #a7f3d0; }
    .est-pill.bloqueada, .est-pill.erro { background: #7f1d1d; color: #fecaca; }
    .est-pill.simulada { background: #075985; color: #bae6fd; }
    .est-btn-aplicar { background: #059669; }
    .est-btn-aplicar:hover { background: #10b981; }
    .est-btn-aplicar[disabled] { opacity: .45; cursor: not-allowed; }
    .est-carregando { display: none; }
    form.enviando .est-carregando { display: inline; }
    form.enviando button { opacity: .6; pointer-events: none; }
</style>

<header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur border-b border-slate-800">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2 font-black tracking-tight text-white">
            <span class="material-symbols-outlined text-sky-400">inventory_2</span> Estoque · Novare
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
        <div class="est-aviso <?= $flash['ok'] ? 'est-ok' : 'est-erro' ?>">
            <span class="material-symbols-outlined text-base"><?= $flash['ok'] ? 'check_circle' : 'error' ?></span>
            <span><?= e($flash['msg']) ?><?php if ($aplicada): ?> <a class="text-white font-bold" href="/settings-admin/estoque/relatorio?id=<?= (int) $aplicada ?>" target="_blank">Abrir relatório para o comercial</a><?php endif; ?></span>
        </div>
    <?php endif; ?>

    <!-- ============ 1) CONFERIR ============ -->
    <section class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">sync</span> Atualizar estoque do catálogo</h2>
        <p class="text-xs text-slate-400 mb-4">
            Lê o estoque de cada cor na XBZ. Regra: cor com estoque abaixo do pedido mínimo do produto some do seletor; quando todas as cores ficam abaixo, o produto sai das listagens e da busca, a página continua abrindo com aviso de indisponível e fica fora do Google até voltar. Nada é apagado do banco.
            São duas etapas: a conferência mostra o que mudaria sem tocar no site; aplicar lê a XBZ de novo e só tira do ar o que as duas leituras confirmarem.
        </p>
        <form method="post" action="/settings-admin/estoque" onsubmit="this.classList.add('enviando')">
            <?= $hid('simular') ?>
            <button class="<?= $btn ?>"><span class="material-symbols-outlined text-sm">fact_check</span> 1. Conferir estoque na XBZ</button>
            <span class="est-carregando text-xs text-slate-400" style="margin-left:.5rem">Lendo a XBZ, pode levar até 2 minutos. Não feche a página.</span>
        </form>
        <p class="text-xs text-slate-500 mt-3">Fora do site hoje: <strong class="text-slate-300"><?= $n($fora_hoje) ?> produtos</strong>
            <?php if ($fora_hoje): ?> · <a class="text-sky-400" target="_blank" href="/settings-admin/estoque/relatorio?tipo=fora">lista completa para o comercial</a><?php endif; ?></p>
    </section>

    <!-- ============ 2) RESULTADO DA CONFERÊNCIA ============ -->
    <?php if ($pendente): ?>
        <section class="<?= $card ?>">
            <h2 class="text-lg font-black text-white flex items-center gap-2 mb-1"><span class="material-symbols-outlined text-sky-400">preview</span> Conferência #<?= (int) $pendente['id'] ?> · <?= e($dt($pendente['criado_em'])) ?></h2>
            <p class="text-xs text-slate-400 mb-4">XBZ devolveu <?= $n($pendente['itens_api']) ?> cores de produto · <?= e(number_format((float) $pendente['cobertura'], 1, ',', '.')) ?>% das cores do site foram encontradas · <?= $n($pendente['produtos_no_ar']) ?> produtos estão no ar hoje.</p>

            <?php if ($pendente['status'] === 'bloqueada'): ?>
                <div class="est-aviso est-erro mb-4"><span class="material-symbols-outlined text-base">block</span><span><strong>Bloqueada pela trava de segurança.</strong> <?= e((string) $pendente['mensagem']) ?></span></div>
            <?php elseif ($expirada): ?>
                <div class="est-aviso est-trava mb-4"><span class="material-symbols-outlined text-base">schedule</span><span>Esta conferência passou de <?= (int) $trava['validade'] ?> minutos ou já existe uma mais nova. Rode a conferência de novo antes de aplicar.</span></div>
            <?php endif; ?>

            <div class="est-num mb-5">
                <div><b><?= $n($pendente['saem']) ?></b><span>produtos saem</span></div>
                <div><b><?= $n($pendente['voltam']) ?></b><span>produtos voltam</span></div>
                <div><b><?= $n($pendente['cores_saem']) ?></b><span>cores saem</span></div>
                <div><b><?= $n($pendente['cores_voltam']) ?></b><span>cores voltam</span></div>
                <div><b><?= $n($pendente['fora_api']) ?></b><span>fora da API</span></div>
                <div><b><?= $n($pendente['incertos']) ?></b><span>leitura incerta</span></div>
            </div>

            <?php
            $blocos = [
                'saiu'       => 'Produtos que sairiam do site',
                'voltou'     => 'Produtos que voltariam',
                'cor_saiu'   => 'Cores que sairiam (produto continua no ar)',
                'cor_voltou' => 'Cores que voltariam',
                'fora_api'   => 'Fora da API (continuam no site, conferir manualmente)',
                'incerto'    => 'Leitura incerta (continuam no site)',
            ];
            foreach ($blocos as $tipo => $titulo):
                $linhas = $previa[$tipo] ?? [];
                if (!$linhas) {
                    continue;
                }
                $cor = in_array($tipo, ['cor_saiu', 'cor_voltou'], true);
                ?>
                <details class="mb-3" <?= $tipo === 'saiu' ? 'open' : '' ?>>
                    <summary class="text-sm font-bold text-slate-100 cursor-pointer mb-2"><?= e($titulo) ?> (<?= $n(count($linhas)) ?>)</summary>
                    <div class="est-scroll">
                        <table class="est-tab">
                            <thead><tr><th>Código</th><th>Produto</th><?php if ($cor): ?><th>Cor</th><th>Estoque</th><?php else: ?><th>Categoria</th><?php endif; ?><th>Mínimo</th><?php if (!$cor): ?><th>Estoque por cor</th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($linhas, 0, 50) as $l): ?>
                                <tr>
                                    <td><?= e($cor ? (string) $l['sku_completo'] : (string) $l['sku_pai']) ?></td>
                                    <td><?= e((string) $l['nome']) ?></td>
                                    <?php if ($cor): ?>
                                        <td><?= e((string) $l['cor']) ?></td><td><?= $n($l['estoque_anterior']) ?> → <?= $n($l['estoque_atual']) ?></td>
                                    <?php else: ?>
                                        <td><?= e((string) $l['categoria']) ?></td>
                                    <?php endif; ?>
                                    <td><?= $n($l['quantidade_minima']) ?></td>
                                    <?php if (!$cor): ?><td class="text-slate-400"><?= e((string) $l['detalhe']) ?></td><?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($linhas) > 50): ?><p class="text-xs text-slate-500 mt-2">Mostrando 50 de <?= $n(count($linhas)) ?>. A lista completa está no relatório.</p><?php endif; ?>
                </details>
            <?php endforeach; ?>

            <div class="flex flex-wrap items-center gap-3 mt-5">
                <a href="/settings-admin/estoque/relatorio?id=<?= (int) $pendente['id'] ?>" target="_blank" class="<?= $btnSec ?>"><span class="material-symbols-outlined text-sm">print</span> Prévia do relatório</a>
                <?php if ($pendente['status'] !== 'descartada'): ?>
                    <form method="post" action="/settings-admin/estoque"><?= $hid('descartar', (int) $pendente['id']) ?><button class="<?= $btnSec ?>"><span class="material-symbols-outlined text-sm">close</span> Descartar</button></form>
                <?php endif; ?>
            </div>

            <?php if ($podeAplicar): ?>
                <form method="post" action="/settings-admin/estoque" class="mt-6 border-t border-slate-700 pt-6" onsubmit="this.classList.add('enviando')">
                    <?= $hid('aplicar', (int) $pendente['id']) ?>
                    <?php if ((int) $pendente['exige_confirmacao'] === 1): ?>
                        <div class="est-aviso est-trava mb-4">
                            <span class="material-symbols-outlined text-base">warning</span>
                            <span><strong>Trava de segurança:</strong> esta atualização tira <?= $n($pendente['saem']) ?> de <?= $n($pendente['produtos_no_ar']) ?> produtos do ar (acima de <?= e(number_format((float) $trava['percentual'], 0)) ?>%). Confira a lista acima. Se estiver certo, marque a caixa e digite CONFIRMO.</span>
                        </div>
                        <label class="text-xs text-slate-300 flex items-center gap-2 mb-3"><input type="checkbox" name="confirmar" value="1" required> Conferi a lista e quero tirar esses produtos do site</label>
                        <label class="text-xs text-slate-300 block mb-4" style="max-width: 16rem">Digite CONFIRMO<input name="palavra" autocomplete="off" required class="<?= $inp ?> mt-1"></label>
                    <?php endif; ?>
                    <button class="<?= $btn ?> est-btn-aplicar"><span class="material-symbols-outlined text-sm">published_with_changes</span> 2. Aplicar no site</button>
                    <span class="est-carregando text-xs text-slate-400" style="margin-left:.5rem">Fazendo a segunda leitura da XBZ e aplicando. Não feche a página.</span>
                    <p class="text-xs text-slate-500 mt-2">Vale por <?= (int) $trava['validade'] ?> minutos a partir da conferência. Produto ou cor só sai se a segunda leitura confirmar.</p>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ============ 3) HISTÓRICO ============ -->
    <section class="<?= $card ?>">
        <h2 class="text-lg font-black text-white flex items-center gap-2 mb-4"><span class="material-symbols-outlined text-sky-400">history</span> Histórico</h2>
        <?php if (!$historico): ?>
            <p class="text-xs text-slate-400">Nenhuma atualização ainda.</p>
        <?php else: ?>
            <div class="est-scroll">
                <table class="est-tab">
                    <thead><tr><th>#</th><th>Data</th><th>Status</th><th>Saíram</th><th>Voltaram</th><th>Cores saíram</th><th>Cores voltaram</th><th>Fora da API</th><th>Quem</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($historico as $h): ?>
                        <tr>
                            <td><?= (int) $h['id'] ?></td>
                            <td><?= e($dt($h['aplicado_em'] ?: $h['criado_em'])) ?></td>
                            <td><span class="est-pill <?= e($h['status']) ?>"><?= e($rotuloStatus[$h['status']] ?? $h['status']) ?></span><?= (int) $h['divergentes'] ? ' <span class="text-slate-400">(' . (int) $h['divergentes'] . ' não confirmados)</span>' : '' ?></td>
                            <td><?= $n($h['saem']) ?></td>
                            <td><?= $n($h['voltam']) ?></td>
                            <td><?= $n($h['cores_saem']) ?></td>
                            <td><?= $n($h['cores_voltam']) ?></td>
                            <td><?= $n($h['fora_api']) ?></td>
                            <td class="text-slate-400"><?= e((string) $h['usuario']) ?></td>
                            <td><?php if (in_array($h['status'], ['aplicada', 'simulada'], true)): ?><a class="text-sky-400" target="_blank" href="/settings-admin/estoque/relatorio?id=<?= (int) $h['id'] ?>">Relatório</a><?php elseif ($h['mensagem']): ?><span class="text-slate-500" title="<?= e((string) $h['mensagem']) ?>">motivo</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
