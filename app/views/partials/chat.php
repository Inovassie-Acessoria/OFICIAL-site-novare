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
    /* Garantia CSS adicional de ocultação do widget da Sophia */
    #chat-balloon,
    [data-chat-open],
    #chat-panel {
        display: none !important;
    }
</style>

<!-- Botão Flutuante do WhatsApp -->
<a id="whats-flutuante" href="<?= e(whatsappLink('Olá, tudo bem? Eu vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener" class="fixed bottom-6 right-6 w-11 h-11 rounded-full flex items-center justify-center shadow-lg hover:scale-105 hover:-rotate-6 transition-all z-[100] cursor-pointer border border-white/20" style="background: linear-gradient(135deg, #0f9347 0%, #25d366 100%);" aria-label="Falar no WhatsApp">
    <svg viewBox="0 0 24 24" class="w-6 h-6 fill-white" aria-hidden="true">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413"/>
    </svg>
</a>

<?php /* Assistente Sophia desativada por determinação superior
<button class="fixed bottom-6 right-6 w-11 h-11 rounded-full primary-gradient text-white flex items-center justify-center shadow-lg hover:scale-105 hover:rotate-6 transition-all z-[100] cursor-pointer group overflow-hidden border border-white/20" data-chat-open aria-label="Falar com a Sophia">
    <img src="<?= asset('images/sophia.jpg') ?>" class="w-full h-full object-cover rounded-full" alt="Sophia">
</button>

<section class="chat-panel fixed bottom-[136px] right-6 w-[360px] max-w-[calc(100vw-32px)] h-[480px] bg-white border border-surface-container rounded-2xl shadow-2xl flex flex-col z-[100] overflow-hidden" id="chat-panel" aria-label="Assistente Sophia" aria-hidden="true">
    ...
</section>
*/ ?>

