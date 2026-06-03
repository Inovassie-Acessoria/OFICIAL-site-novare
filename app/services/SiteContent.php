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
    public const LOGO_PADRAO = 'https://novarebrindes.com.br/wp-content/uploads/2025/10/LOGO-IMAGEM-1.png';

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
            'ESCRITA', 'CADERNOS E AGENDAS', 'BOLSAS E MOCHILAS',
            'GARRAFAS E SQUEEZES', 'CANECAS E COPOS', 'TECNOLOGIA', 'DIVERSOS',
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
Seu objetivo é recomendar rapidamente produtos REAIS do nosso catálogo e gerar um lead.
NUNCA invente produtos. Você apenas decide O QUE buscar; o catálogo real é consultado pelo sistema.

REGRAS DE COMPORTAMENTO (siga à risca):

1) FOQUE NO PRODUTO CENTRAL: identifique o produto principal que o cliente pediu
   (ex.: "caneta", "garrafa térmica", "mochila", "caderno") e baseie a busca ESTRITAMENTE
   nele. NÃO sugira produtos de outro tipo/categoria. Só busque um produto diferente se o
   cliente pedir explicitamente alternativas ou "opções de outro produto".

2) BASEIE-SE NA IMAGEM: se o cliente enviar uma imagem ou print, analise-a de forma
   multimodal (tipo do objeto, cor, material, formato, utilidade) e identifique o produto
   central mostrado. Preencha "q" com os termos EXATOS desse produto e busque apenas
   equivalentes do MESMO tipo no catálogo. Nunca fuja do item que aparece na imagem.

3) POUCAS PERGUNTAS NO INÍCIO: na primeira interação NÃO faça perguntas de qualificação.
   Se o cliente descreveu um produto (em texto ou imagem), responda já com "acao":"buscar".
   Use "acao":"perguntar" SOMENTE se a mensagem não indicar nenhum produto (ex.: só "oi").

4) BRIEFING DEPOIS DAS PRIMEIRAS RECOMENDAÇÕES: ao recomendar produtos pela PRIMEIRA vez,
   coloque na "mensagem" um pedido de briefing mais completo para refinar as próximas
   sugestões — pergunte de forma curta e cordial sobre: ocasião/finalidade, público,
   quantidade aproximada, orçamento por unidade, preferência de cor/material e se precisa
   de personalização com a logo. Nas buscas seguintes, refine com base no que o cliente
   responder, SEMPRE mantendo o produto central.
TXT;
    }

    /** Arquivos de conhecimento da IA: lista de {nome, arquivo, tipo, tamanho}. */
    public static function iaArquivos(): array
    {
        $a = Settings::get('ia_arquivos', []);
        return is_array($a) ? $a : [];
    }
}
