<?php
/**
 * Tela de login do painel oculto. Responsiva (mobile-first).
 * @var string|null $erro
 * @var string $csrf
 */
?>
<main class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8 select-none">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-sky-500/10 border border-sky-500/30 mb-4">
                <span class="material-symbols-outlined text-sky-400 text-3xl">lock</span>
            </div>
            <h1 class="text-xl font-black tracking-tight text-white">Painel Novare</h1>
            <p class="text-xs text-slate-400 mt-1">Acesso restrito — área administrativa</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="mb-5 flex items-center gap-2 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm px-4 py-3">
                <span class="material-symbols-outlined text-base">error</span>
                <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/settings-admin/login" class="bg-slate-800/60 border border-slate-700 rounded-2xl p-6 shadow-xl space-y-4" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div>
                <label for="email" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">E-mail</label>
                <input id="email" name="email" type="email" required autofocus inputmode="email"
                       class="w-full rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm px-4 py-3 focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500 outline-none"
                       placeholder="seu@email.com">
            </div>
            <div>
                <label for="senha" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Senha</label>
                <input id="senha" name="senha" type="password" required
                       class="w-full rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-sm px-4 py-3 focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500 outline-none"
                       placeholder="••••••••">
            </div>
            <button type="submit"
                    class="w-full rounded-xl bg-sky-500 hover:bg-sky-400 active:scale-[0.99] text-white text-sm font-bold py-3 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">login</span> Entrar
            </button>
        </form>

        <p class="text-center text-[10px] text-slate-600 mt-6 select-none">Novare Brindes &copy; <?= date('Y') ?> — uso interno</p>
    </div>
</main>
