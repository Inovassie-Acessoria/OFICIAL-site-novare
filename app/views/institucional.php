<?php
/**
 * View Institucional (sobre, atendimento, fidelidade) estilizada com Tailwind CSS.
 * @var string $pagina sobre|atendimento|fidelidade
 */
$conteudos = [
    'sobre' => [
        'titulo' => 'Sobre a Novare Brindes',
        'lead'   => 'Seleção sob medida. Elevando a percepção de valor da sua marca através de brindes corporativos e presentes personalizados com excelência técnica.',
        'blocos' => [
            ['Nossa missão', 'Ajudar empresas a fortalecer relacionamentos estratégicos — com colaboradores, clientes e parceiros — por meio de brindes de alto padrão.'],
            ['Parceria consultiva', 'Selecionamos apenas itens altamente duráveis e de alto valor percebido de fornecedores auditados e marcas parceiras de confiança.'],
            ['Logística e Execução', 'Do briefing à gravação e logística de entrega nacional, cuidamos de toda a cadeia de execução com excelência operacional.'],
        ],
    ],
    'atendimento' => [
        'titulo' => 'Atendimento Consultivo B2B',
        'lead'   => 'Acompanhamento ponta a ponta. Um consultor comercial dedicado acompanha todo o fluxo da sua marca.',
        'blocos' => [
            ['1. Briefing e Conceito', 'Sua empresa nos conta a ocasião, público-alvo, prazo e limite de custos. Nós alinhamos e desenhamos a sugestão ideal.'],
            ['2. Proposta e Layouts', 'Apresentamos maquetes e layouts digitais virtuais dos produtos personalizados para sua aprovação visual antes do lote.'],
            ['3. Produção e Distribuição', 'Cuidamos de gravação técnica de alta resolução e coordenamos a entrega nos escritórios da sua empresa ou direto nos endereços dos colaboradores.'],
        ],
    ],
    'fidelidade' => [
        'titulo' => 'Fidelidade Corporativa',
        'lead'   => 'Benefícios técnicos de recompra e condições financeiras diferenciadas para pedidos corporativos recorrentes.',
        'blocos' => [
            ['Tabelas Diferenciadas', 'Empresas parceiras que mantêm recorrência de compras obtêm faturamentos sob condições de volume e prazos especiais.'],
            ['Gabaritos Prontos', 'Armazenamos seus vetores, arquivos de identidade visual e especificações de embalagens para recompras imediatas em menos de 48h.'],
            ['Acesso Prioritário', 'Acesso prioritário a lançamentos ecológicos e novidades exclusivas de presentes corporativos antes do catálogo público.'],
        ],
    ],
];
$c = $conteudos[$pagina] ?? $conteudos['sobre'];
?>
<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400 mb-8" aria-label="Breadcrumb">
        <a href="<?= url('/') ?>" class="hover:text-primary transition-colors flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">home</span> Início
        </a>
        <span class="material-symbols-outlined text-[10px]">chevron_right</span>
        <span class="text-secondary font-semibold"><?= e($c['titulo']) ?></span>
    </nav>

    <!-- Hero Header -->
    <section class="mb-12">
        <div class="primary-gradient text-white rounded-2xl p-10 md:p-16 shadow-md relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 opacity-10 text-white select-none pointer-events-none">
                <span class="material-symbols-outlined text-[180px]">workspace_premium</span>
            </div>
            <div class="relative z-10 max-w-2xl">
                <span class="text-white/80 font-black uppercase tracking-[0.2em] text-[10px] mb-3 block">Novare Brindes B2B</span>
                <h1 class="text-3xl md:text-4xl font-black tracking-tighter mb-4"><?= e($c['titulo']) ?></h1>
                <p class="text-sm text-white/90 leading-relaxed font-medium"><?= e($c['lead']) ?></p>
            </div>
        </div>
    </section>

    <!-- Blocos Grid -->
    <section class="py-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($c['blocos'] as [$t, $d]): ?>
                <div class="bg-white border border-surface-container rounded-2xl p-8 shadow-sm hover:shadow-md transition-shadow">
                    <h3 class="text-sm font-extrabold text-on-surface uppercase tracking-wider mb-4 border-l-2 border-primary pl-3"><?= e($t) ?></h3>
                    <p class="text-secondary text-xs leading-relaxed"><?= e($d) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- WhatsApp CTA -->
        <div class="text-center mt-16">
            <a href="<?= e(whatsappLink('Olá! Vim através do site e gostaria de fazer um orçamento.')) ?>" target="_blank" rel="noopener" class="primary-gradient text-white px-10 py-4 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg hover:opacity-95 transition-opacity inline-flex items-center gap-2.5">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20zm4.5-5.9c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.6-1.2.1-.2 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3-.7.7-.9 1.7-.6 2.8.5 1.6 1.6 3 3.1 4 .9.5 1.6.8 2.1.9.7.2 1.4.2 1.9.1.6-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1 0-.1-.2-.2-.4-.3z"/></svg>
                Falar com um Consultor Comercial
            </a>
            <p class="text-[9px] text-slate-400 mt-2 font-medium">Resposta rápida em horário comercial. Projetos sob medida.</p>
        </div>
    </section>
</div>
