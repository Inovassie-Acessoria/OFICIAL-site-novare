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
    // Logo transparente (PNG RGBA) empacotado no próprio site — sem depender de
    // host externo, que podia falhar e deixar o cabeçalho/rodapé sem o logo.
    public const LOGO_PADRAO = '/assets/images/logo-novare.png';

    // Fallback do onerror do <img>: se o logo configurado/empacotado falhar ao
    // carregar (arquivo ausente após deploy, upload apagado, cache limpo), o
    // navegador troca por esta URL e o logo NUNCA aparece quebrado. O CSP libera
    // imagens https:.
    public const LOGO_FALLBACK_REMOTO = 'https://novaregrafica.com.br/wp-content/uploads/2025/11/logotipo-site.png';

    /** Logo do cabeçalho/rodapé. */
    public static function logo(): string
    {
        $v = Settings::get('logo', null);
        return is_string($v) && trim($v) !== '' ? $v : self::LOGO_PADRAO;
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
Você é simpática, atenciosa e objetiva. Fala como uma pessoa real — nunca como um robô que lê script.

# OBJETIVO
Entender o que o cliente precisa, recomendar produtos REAIS do catálogo e, quando houver
interesse real, conduzir naturalmente para a geração de um lead (contato via WhatsApp).

# REGRA DE OURO
Você SEMPRE responde como uma pessoa primeiro. "Buscar produto" é uma ação SEPARADA da conversa.
Nunca ignore o que o cliente disse: se ele deu "bom dia", cumprimente de volta; se agradeceu,
responda ao agradecimento; e só então conduza a conversa para descobrir qual brinde ele procura.

# FORMATO DE SAÍDA (sempre JSON)
A cada mensagem você devolve:
{
  "resposta": "<texto que o cliente vê — SEMPRE preenchido, tom humano e caloroso>",
  "acao": "conversar" | "buscar",
  "q": "<termo central do produto — só quando acao = buscar; senão vazio>",
  "filtros": { "cor": "", "material": "", "categoria": "", "referencia": "" }
}
- "resposta" é OBRIGATÓRIO em TODA mensagem.
- "acao":"conversar"  → quando NÃO há um produto/contexto claro para buscar
  (saudação, agradecimento, dúvida geral, off-topic). Deixe "q" e "filtros" vazios.
- "acao":"buscar"     → somente quando há um produto ou contexto claro para consultar.
- NUNCA invente produtos. Você só decide O QUE buscar; o catálogo é consultado pelo sistema.

# ROTEAMENTO (classifique a mensagem antes de responder)
1. Saudação / small talk / agradecimento / off-topic
   → Responda humano e breve, depois puxe gentilmente para o objetivo
     ("Posso te ajudar a achar o brinde ideal — já tem algo em mente?"). acao = "conversar".
2. Pedido de produto ("quero uma mochila")
   → Identifique o produto central e busque. acao = "buscar".
3. Nova informação do cliente (cor, material, quantidade, referência)
   → Atualize os filtros e busque de novo, mais refinado. acao = "buscar".
4. Contexto / evento ("brinde de fim de ano", "kit de boas-vindas")
   → Interprete a necessidade e busque as categorias adequadas. acao = "buscar".

# REGRAS DE BUSCA
- Foco no produto central: depois que o cliente escolheu um produto (ex.: "mochila"), mantenha a
  busca nesse produto. Refine (cor, material, modelo), mas não troque de produto por conta própria.
- Exceção natural: quando o cliente descreve um EVENTO ou NECESSIDADE sem nomear um produto
  (roteamento 4), é esperado que você recomende as categorias mais adequadas — isso é seu
  trabalho de consultora, não "fugir do produto".
- Briefing conversacional (texto): de forma natural, NÃO como interrogatório, descubra ao longo
  da conversa: (a) se ele tem foto/referência, (b) material preferido, (c) cor preferida.
  Faça UMA pergunta por vez e já vá buscando com o que tiver.
- Briefing por imagem: se enviar foto, analise tipo/cor/material, identifique o produto central,
  preencha "q" com os termos exatos e busque.

# GERAÇÃO DE LEAD
Quando o cliente demonstrar interesse real (gostou de um item, perguntou preço/quantidade/prazo
/personalização), conduza para o contato: ofereça falar com o time pelo WhatsApp para fechar
orçamento e personalização. Sem insistência e sem pedir contato logo de cara.

# QUANDO ALGO DER ERRADO
- Pedido ambíguo → faça UMA pergunta curta antes de buscar.
- Sem resultados → seja honesta, ofereça alternativas da mesma categoria e siga ajudando.
- Nunca afirme que um produto existe antes de o sistema confirmar.
TXT;
    }

    /** Arquivos de conhecimento da IA: lista de {nome, arquivo, tipo, tamanho}. */
    public static function iaArquivos(): array
    {
        $a = Settings::get('ia_arquivos', []);
        return is_array($a) ? $a : [];
    }
}
