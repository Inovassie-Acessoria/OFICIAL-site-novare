<?php /** Widget do assistente de IA Sophia. A lógica vive em novare.js -> /api/agent.php */ ?>
<style>
    .chat-panel {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0;
        transform: translateY(16px);
        pointer-events: none;
    }
    .chat-panel.open {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    .chat-body {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
        overflow-y: auto;
        flex-grow: 1;
    }
    .chat-msg {
        max-width: 85%;
        padding: 10px 14px;
        font-size: 12px;
        line-height: 1.5;
        border-radius: 16px;
    }
    .chat-msg.bot {
        background-color: #f3f3f6;
        color: #1a1c1e;
        align-self: flex-start;
        border-bottom-left-radius: 4px;
    }
    .chat-msg.user {
        background: linear-gradient(135deg, #006590 0%, #24a1e0 100%);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
    }
    .chat-msg.user img {
        border-radius: 8px;
        max-width: 100%;
        max-height: 140px;
        object-fit: contain;
        margin-top: 6px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .chat-typing {
        display: flex;
        align-items: center;
        padding: 4px 8px;
    }
    .chat-typing span {
        display: inline-block;
        width: 6px;
        height: 6px;
        background-color: #999;
        border-radius: 50%;
        margin-right: 4px;
        animation: chatTyping 1.4s infinite both;
    }
    .chat-typing span:nth-child(2) { animation-delay: .2s; }
    .chat-typing span:nth-child(3) { animation-delay: .4s; }
    @keyframes chatTyping {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
    }
    .chat-suggestions {
        background: transparent !important;
        align-self: flex-start !important;
        padding: 0 !important;
        max-width: 100% !important;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .chat-prod {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #eeeef0;
        border-radius: 12px;
        padding: 8px;
        text-decoration: none;
        transition: all 0.2s;
    }
    .chat-prod:hover {
        border-color: #006590;
        box-shadow: 0 4px 12px rgba(0, 101, 144, 0.08);
        transform: translateY(-1px);
    }
    .chat-prod img {
        width: 44px;
        height: 44px;
        object-fit: contain;
        border-radius: 8px;
        flex-shrink: 0;
        background: #f9f9fc;
        border: 1px solid #eeeef0;
    }
    .chat-prod .info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .chat-prod .info strong {
        font-size: 11px;
        font-weight: 700;
        color: #1a1c1e;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .chat-prod .info span {
        font-size: 10px;
        color: #006590;
        font-weight: 800;
        margin-top: 1px;
    }
</style>

<!-- Balão de Apoio Lateral "não achou o produto? clique aqui" (rounded-xl) -->
<button type="button" data-chat-open id="chat-balloon" class="fixed bottom-[26px] right-[76px] z-[100] bg-white border border-surface-container/80 text-[9px] font-bold text-slate-700 tracking-tight uppercase px-4 py-2.5 rounded-xl hover:bg-slate-50 active:scale-95 transition-all shadow-md flex items-center gap-1.5 cursor-pointer select-none opacity-0 translate-x-[10px] pointer-events-none transition-all duration-500">
    <span class="material-symbols-outlined text-xs text-primary font-bold">search</span>
    Não achou o produto? Clique aqui
</button>

<!-- FAB Button con Robot (Reduzido de w-14 h-14 para w-11 h-11) -->
<button class="fixed bottom-6 right-6 w-11 h-11 rounded-full primary-gradient text-white flex items-center justify-center shadow-lg hover:scale-105 hover:rotate-6 transition-all z-[100] cursor-pointer group overflow-hidden border border-white/20" data-chat-open aria-label="Falar com a Sophia">
    <img src="<?= asset('images/sophia.jpg') ?>" class="w-full h-full object-cover rounded-full" alt="Sophia">
</button>

<!-- Chat Panel Sophia -->
<section class="chat-panel fixed bottom-20 right-6 w-[360px] max-w-[calc(100vw-32px)] h-[480px] bg-white border border-surface-container rounded-2xl shadow-2xl flex flex-col z-[100] overflow-hidden" id="chat-panel" aria-label="Assistente Sophia" aria-hidden="true">
    <!-- Header -->
    <div class="primary-gradient text-white px-5 py-4 flex items-center justify-between shadow-sm select-none">
        <div class="flex items-center gap-3">
            <div class="relative w-9 h-9 rounded-full bg-white/10 flex items-center justify-center border border-white/20 overflow-hidden">
                <img src="<?= asset('images/sophia.jpg') ?>" class="w-full h-full object-cover rounded-full" alt="Sophia">
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 border border-white"></span>
            </div>
            <div>
                <strong class="text-sm font-black tracking-tight block">Sophia</strong>
                <span class="text-[10px] text-white/80 font-bold uppercase tracking-wider block">Assistente Novare</span>
            </div>
        </div>
        <!-- Close Button ("X" Proeminente com realce de transição) -->
        <button type="button" id="chat-close" class="text-white/80 hover:text-white hover:bg-white/15 rounded-full w-8 h-8 flex items-center justify-center transition-all cursor-pointer hover:rotate-90" aria-label="Fechar chat">
            <span class="material-symbols-outlined text-xl font-bold">close</span>
        </button>
    </div>

    <!-- Body -->
    <div class="chat-body flex-grow" id="chat-body" aria-live="polite">
        <div class="chat-msg bot">
            Olá! 👋 Eu sou a Sophia, sua consultora de brindes. 
            Como posso ajudar a encontrar os brindes perfeitos hoje? 
            Se tiver a imagem ou o print de um produto de referência, basta clicar no ícone de imagem para anexar ou **colar direto (Ctrl+V)** aqui! Eu vou encontrar as opções equivalentes. 😊
        </div>
    </div>

    <!-- Preview Container de Imagem Oculta (Acima do input) -->
    <div id="chat-preview-container" class="hidden border-t border-surface-container p-3 bg-slate-50 flex items-center gap-3 select-none">
        <div class="relative w-12 h-12 rounded-lg border border-surface-container overflow-hidden bg-white flex-shrink-0 flex items-center justify-center">
            <img id="chat-preview-img" class="max-w-full max-h-full object-contain" src="" alt="Preview">
        </div>
        <div class="flex-grow min-w-0">
            <span class="text-[9px] text-primary font-black uppercase block leading-none">Imagem anexada</span>
            <span id="chat-preview-name" class="text-[10px] text-slate-700 font-bold block truncate mt-1">imagem.png</span>
        </div>
        <button type="button" id="chat-preview-cancel" class="w-7 h-7 rounded-full bg-slate-200 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition-colors text-slate-500 cursor-pointer" aria-label="Remover anexo">
            <span class="material-symbols-outlined text-base font-bold">close</span>
        </button>
    </div>

    <!-- Form / Input -->
    <form class="flex flex-col border-t border-surface-container bg-white" id="chat-form">
        <div class="flex items-center p-3 gap-2 w-full">
            <div class="relative flex-grow flex items-center">
                <!-- Ícone de Anexo à Esquerda Dentro do Campo de Texto (Aprovado em /grill-me) -->
                <button type="button" id="chat-attach-btn" class="absolute left-3 w-8 h-8 rounded-full text-slate-400 hover:text-primary hover:bg-slate-100 flex items-center justify-center transition-colors cursor-pointer" aria-label="Anexar imagem/print">
                    <span class="material-symbols-outlined text-lg">image</span>
                </button>
                <input type="file" id="chat-file" accept="image/*" class="hidden">
                
                <input type="text" id="chat-input" placeholder="Pergunte ou cole um print (Ctrl+V)..." autocomplete="off" aria-label="Mensagem" class="w-full text-xs border border-surface-container rounded-xl pl-12 pr-4 py-3 bg-surface focus:ring-1 focus:ring-primary/40 focus:bg-white outline-none">
            </div>
            <button type="submit" aria-label="Enviar" class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary-container shadow-md transition-all flex-shrink-0 cursor-pointer">
                <span class="material-symbols-outlined text-lg">send</span>
            </button>
        </div>
    </form>
</section>
