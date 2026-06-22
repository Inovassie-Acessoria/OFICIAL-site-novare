<?php

declare(strict_types=1);

require_once __DIR__ . '/Settings.php';

/**
 * Conteúdo editável do site com PADRÕES embutidos.
 *
 * Centraliza o que o painel /settings-admin controla, mesclando o valor salvo
 * (Settings) com o padrão atual do site. Tanto as views públicas quanto o
 * admin leem AQUI — é o que garante a sincronia "o que está no site é o que
 * aparece no admin".
 */
final class SiteContent
{
    // Logo transparente (PNG RGBA) empacotado no próprio site (versionado no Git,
    // sempre presente após o deploy). É o padrão e também o destino do onerror do
    // <img> — se o logo configurado falhar, cai aqui em vez de num host externo.
    public const LOGO_PADRAO = '/assets/images/logo-novare.png';

    /** Logo do cabeçalho/rodapé. */
    public static function logo(): string
    {
        $v = Settings::get('logo', null);
        if (is_string($v) && trim($v) !== '') {
            $v = trim($v);
            // Logo enviado pelo painel é um caminho local sob /assets/. Se o arquivo
            // não existir mais (ex.: removido em um deploy), usa o empacotado em vez
            // de quebrar e cair no fallback — é o que causava o "logo sumindo".
            if (str_starts_with($v, '/assets/') && !is_file(APP_ROOT . '/public' . $v)) {
                return self::LOGO_PADRAO;
            }
            return $v;
        }
        return self::LOGO_PADRAO;
    }

    /** As 7 categorias da seção "Navegue pelas categorias" (rótulos fixos). */
    public static function categoriasPainel(): array
    {
        return [
            'CANETAS', 'MOLESKINE & CADERNOS', 'BOLSAS E MOCHILAS',
            'GARRAFAS E SQUEEZES', 'CANECAS E COPOS', 'TECNOLOGIA', 'DIVERSOS',
            'MOUSE PADS', 'CARTEIRAS',
        ];
    }

    /**
     * Mapeamento de nome de exibição → categoria real no banco de dados.
     * Categorias de exibição na home/footer podem ter nomes diferentes dos
     * valores gravados no banco. Este método traduz para o filtro correto.
     */
    private const CATEGORIA_ALIASES = [
        'CANETAS'             => 'ESCRITA',
        'MOLESKINE & CADERNOS' => 'CADERNOS E AGENDAS',
    ];

    /**
     * Retorna o valor de categoria que deve ser usado no filtro do catálogo.
     * Se houver um alias, retorna a categoria real; senão, retorna o nome original.
     */
    public static function categoriaFiltro(string $nomeExibicao): string
    {
        $upper = mb_strtoupper(trim($nomeExibicao), 'UTF-8');
        return self::CATEGORIA_ALIASES[$upper] ?? $nomeExibicao;
    }

    /** Imagem sobrescrita de uma categoria (ou null para usar a do banco/fallback). */
    public static function categoriaImagem(string $categoria): ?string
    {
        $map = Settings::get('categorias_imagens', []);
        if (!is_array($map)) {
            return null;
        }
        $chave = mb_strtoupper(trim($categoria), 'UTF-8');
        foreach ($map as $k => $v) {
            if (mb_strtoupper((string) $k, 'UTF-8') === $chave && is_string($v) && trim($v) !== '') {
                return $v;
            }
        }
        return null;
    }

    /** Banners do hero (sequência). Cada item: imagem, tag, titulo, subtitulo, cta_texto, cta_link. */
    public static function banners(): array
    {
        $b = Settings::get('banners', null);
        if (is_array($b) && $b) {
            return $b;
        }
        return self::bannersPadrao();
    }

    public static function bannersPadrao(): array
    {
        return [
            [
                'imagem'    => '/assets/images/banner_mochilas.png',
                'tag'       => 'Mochilas & Bolsas',
                'titulo'    => 'Praticidade corporativa de alto padrão',
                'subtitulo' => 'Mochilas executivas ergonômicas e malas de viagem personalizadas. O brinde ideal para acompanhar seu time em convenções, visitas e viagens de negócios.',
                'cta_texto' => 'Ver Mochilas',
                'cta_link'  => '/catalogo?categoria=BOLSAS E MOCHILAS',
            ],
            [
                'imagem'    => '/assets/images/banner_canetas.png',
                'tag'       => 'Escrita Refinada',
                'titulo'    => 'A assinatura do sucesso da sua marca',
                'subtitulo' => 'Canetas metálicas sofisticadas, lapiseiras e conjuntos executivos em estojos especiais. Brindes marcantes que transmitem precisão e profissionalismo.',
                'cta_texto' => 'Ver Canetas',
                'cta_link'  => '/catalogo?categoria=ESCRITA',
            ],
            [
                'imagem'    => '/assets/images/banner_garrafas.png',
                'tag'       => 'Hidratação & Estilo',
                'titulo'    => 'Sua marca presente no dia a dia',
                'subtitulo' => 'Squeezes de inox e garrafas térmicas com parede dupla a vácuo. Design moderno e eficiência térmica que promovem a saúde e a sustentabilidade no escritório.',
                'cta_texto' => 'Ver Garrafas',
                'cta_link'  => '/catalogo?categoria=GARRAFAS E SQUEEZES',
            ],
            [
                'imagem'    => '/assets/images/banner_onboarding.png',
                'tag'       => 'Kits Corporativos',
                'titulo'    => 'Acolhimento marcante desde o dia um',
                'subtitulo' => 'Kits onboarding de boas-vindas completos com caixas personalizadas. Garanta que novos colaboradores e parceiros sintam-se especiais e motivados.',
                'cta_texto' => 'Ver Kits Onboarding',
                'cta_link'  => '/catalogo?categoria=KITS E CONJUNTOS',
            ],
            [
                'imagem'    => '/assets/images/banner_moleskine.png',
                'tag'       => 'Moleskines & Agendas',
                'titulo'    => 'Ideias e planejamentos registrados com elegância',
                'subtitulo' => 'Cadernos estilo moleskine com capa de couro, pauta inteligente e fita marcadora. Presentes executivos que transmitem requinte e sofisticação.',
                'cta_texto' => 'Ver Moleskines',
                'cta_link'  => '/catalogo?categoria=CADERNOS E AGENDAS',
            ],
        ];
    }

    /** Lista ordenada de SKUs fixados manualmente para um bloco "Top" (ou vazio). */
    public static function topSkus(string $chave): array
    {
        $v = Settings::get($chave, []);
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $v), static fn ($s) => $s !== ''));
    }

    /** Persona/comportamento editável da IA (sem o formato JSON, que é fixo). */
    public static function iaPersona(): string
    {
        $p = Settings::get('ia_prompt', null);
        return is_string($p) && trim($p) !== '' ? $p : self::iaPersonaPadrao();
    }

    public static function iaPersonaPadrao(): string
    {
        return <<<'TXT'
# IDENTIDADE
Você é a Sophia, consultora de brindes corporativos da Novare Brindes (Brasil).
Você é simpática, atenciosa e fala como uma pessoa real — nunca como um robô que lê script.

# OBJETIVO
Entender de forma consultiva e profunda o que o cliente precisa, fazer perguntas de briefing (uma de cada vez) e recomendar produtos apropriados e REAIS do catálogo. Quando houver interesse real, conduzir naturalmente para a geração de um lead (contato via WhatsApp).

# REGRA DE OURO
Você SEMPRE responde como uma pessoa primeiro. Nunca ignore o que o cliente disse: se ele deu "bom dia", cumprimente de volta; se agradeceu, responda ao agradecimento. Seja empática e investigativa.

# PROIBIÇÃO DE PREÇOS (CRÍTICO)
Você NUNCA deve mencionar preços, valores monetários (R$), custos ou estimativas financeiras em suas respostas de texto. Se o cliente perguntar o preço de um produto ou lote, explique cordialmente que todos os brindes são sob consulta comercial no WhatsApp e ofereça encaminhá-lo para a equipe de vendas.

# BRIEFING CONSULTIVO E DEEP DISCOVERY
Não seja direta ao ponto sugerindo produtos de forma apressada. Antes de indicar opções de brindes, atue como uma consultora dedicada e realize um briefing consultivo profundo fazendo perguntas curtas (uma ou duas por vez) para descobrir:
- O tipo de evento ou objetivo da ação corporativa.
- O perfil de quem receberá os brindes (ex: colaboradores em integração, clientes especiais, participantes de convenções).
- A quantidade estimada do lote e o prazo de entrega pretendido.
Conduza esse levantamento de forma leve e empática, valorizando as respostas do cliente antes de fazer as recomendações do catálogo.

# FORMATO DE SAÍDA (sempre JSON)
A cada mensagem você devolve:
{
  "resposta": "<texto que o cliente vê — SEMPRE preenchido, tom humano e caloroso>",
  "acao": "conversar" | "buscar",
  "q": "<termo central do produto — só quando acao = buscar; senão vazio>",
  "filtros": { "cor": "", "material": "", "categoria": "", "referencia": "" }
}
- "resposta" é OBRIGATÓRIO em TODA mensagem.
- "acao":"conversar"  → quando NÃO há um produto/contexto claro para buscar (saudação, briefing inicial, dúvida geral).
- "acao":"buscar"     → somente após compreender o briefing ou quando o cliente solicitar ativamente um produto específico.
- NUNCA invente produtos. Você só decide O QUE buscar; o catálogo é consultado pelo sistema.

# ROTEAMENTO
1. Saudação / small talk / agradecimento / off-topic
   → Responda humano e breve, e puxe gentilmente para o briefing consultivo.
2. Pedido de produto direto ("quero uma mochila")
   → Agradeça a escolha e faça uma pergunta de briefing rápido (ex: "Para qual tipo de evento seriam essas mochilas?") antes de exibir opções, para refinar o atendimento.
3. Informações de refinamento (cor, material, quantidade)
   → Agradeça as informações, ajuste os filtros e busque refinado.
4. Evento / contexto ("brinde de fim de ano")
   → Investigue o perfil dos presenteados e quantidade antes de sugerir as melhores categorias.

# REGRAS DE BUSCA
- Foco no produto central: após definir o produto central (ex: "squeeze"), busque refinar atributos (cor, material), mas não troque de produto sem contexto.
- Briefing conversacional: de forma natural, descubra (a) se ele tem foto/referência, (b) material preferido, (c) cor preferida, fazendo uma pergunta por vez.

# GERAÇÃO DE LEAD
Quando o cliente demonstrar interesse (gostou de um item, quer saber prazos ou opções de gravação da logo), ofereça falar com o time comercial no WhatsApp para fechar o orçamento personalizado.
TXT;
    }

    /** Arquivos de conhecimento da IA: lista de {nome, arquivo, tipo, tamanho}. */
    public static function iaArquivos(): array
    {
        $a = Settings::get('ia_arquivos', []);
        return is_array($a) ? $a : [];
    }
}
