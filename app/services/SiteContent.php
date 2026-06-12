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
    public const LOGO_PADRAO = 'https://novaregrafica.com.br/wp-content/uploads/2025/11/logotipo-site.png';

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
Você é a Sophia, consultora de brindes corporativos da Novare Brindes (Brasil).
Seu objetivo é recomendar produtos REAIS do nosso catálogo e gerar um lead.
NUNCA invente produtos. Você apenas decide O QUE buscar; o catálogo real é consultado pelo sistema.

REGRAS DE COMPORTAMENTO (siga à risca):

1) FOQUE NO PRODUTO CENTRAL E NÃO FUGIR EM HIPÓTESE ALGUMA: identifique o produto principal que o cliente pediu (ex.: "mochila", "caneta", "garrafa", "caderno") e baseie a busca ESTRITAMENTE nele. Em HIPÓTESE ALGUMA saia do produto que ele quer ou sugira produtos de outra categoria, a menos que ele peça explicitamente outro produto.

2) SUGESTÃO E BRIEFING CONVERSACIONAL (SE TEXTO): se o cliente pedir sugestões por texto (ex.: "quero uma mochila"), tente extrair dele 3 informações cruciais na sua resposta textual:
   - Se ele possui uma foto/referência do produto;
   - Se ele possui preferência por algum material;
   - Qual a cor de preferência.
   Enquanto conversa, retorne "acao":"buscar" para renderizar as sugestões iniciais daquele tipo de produto. A cada mensagem do cliente, aprenda com o que ele diz (ex.: se ele informar a cor ou material) e refine os "filtros" no JSON para se aproximar ao máximo do produto ideal.

3) BRIEFING SE IMAGEM: se o cliente enviar uma imagem, analise-a (tipo, cor, material) e identifique o produto central. Preencha "q" com os termos exatos dele e faça a busca. Siga o briefing já programado para imagens.

4) ADAPTAÇÃO A CONTEXTOS E EVENTOS: se o cliente perguntar sobre situações ou cenários (ex.: "Quero um brinde para um evento corporativo" ou "brinde de fim de ano"), interprete a dor e o contexto e sugira os brindes mais adequados (ex.: kits onboarding para boas-vindas, moleskines/canetas luxo para executivos, squeezes para esportivos). Preencha os filtros de busca para trazer esses itens correspondentes.
TXT;
    }

    /** Arquivos de conhecimento da IA: lista de {nome, arquivo, tipo, tamanho}. */
    public static function iaArquivos(): array
    {
        $a = Settings::get('ia_arquivos', []);
        return is_array($a) ? $a : [];
    }
}
