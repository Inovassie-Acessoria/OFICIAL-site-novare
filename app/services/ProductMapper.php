<?php

declare(strict_types=1);

/**
 * Traduz um item bruto da XBZ em campos normalizados do nosso modelo.
 *
 * A XBZ NÃO fornece categoria/material/sustentável e tem 467 sufixos de cor,
 * mas só 24 nomes de cor. Aqui derivamos esses atributos por palavra-chave
 * (a partir do nome/descrição) e mapeamos a cor para um hex de swatch.
 *
 * Métodos estáticos e puros (sem efeitos colaterais) — fáceis de testar.
 */
final class ProductMapper
{
    /** Mapa nome-da-cor (XBZ) -> hex do swatch. Cobre os 24 nomes existentes. */
    private const COR_HEX = [
        'PRETO'        => '#1A1C1E',
        'AZUL'         => '#24A1E0',
        'BRANCO'       => '#FFFFFF',
        'VERDE'        => '#2E9E5B',
        'VERMELHO'     => '#D64545',
        'ROSA'         => '#E85D9E',
        'CINZA'        => '#9AA5AD',
        'PRATA'        => '#C0C7CD',
        'LARANJA'      => '#E8731A',
        'AMARELO'      => '#F2C037',
        'BEGE'         => '#D8C7A8',
        'ROXO'         => '#7C3AED',
        'TRANSPARENTE' => '#E8EEF2',
        'MARROM'       => '#6B4A2B',
        'COLORIDO'     => '#8E7CC3',
        'INOX'         => '#C0C7CD',
        'MADEIRA'      => '#B5895A',
        'DOURADO'      => '#D1880C',
        'CHUMBO'       => '#4A4F54',
        'KRAFT'        => '#C9A66B',
        'BAMBU'        => '#C2A878',
        'COBRE'        => '#B87333',
        'BRONZE'       => '#CD7F32',
    ];

    private const COR_HEX_GENERICO = '#9AA5AD';

    /** Categoria por palavra-chave. Ordem importa (1ª palavra tem prioridade). */
    private const CATEGORIAS = [
        'Canecas e Copos'       => ['CANECA', 'COPO', 'XICARA'],
        'Garrafas e Squeezes'   => ['GARRAFA', 'SQUEEZE', 'COQUETELEIRA', 'GALAO'],
        'Canetas'               => ['CANETA', 'LAPIS', 'LAPISEIRA', 'ROLLER'],
        'Moleskine & Cadernos'  => ['CADERNO', 'CADERNETA', 'BLOCO', 'PLANNER', 'AGENDA', 'CALENDARIO', 'SKETCHBOOK'],
        'Bolsas e Mochilas'     => ['BOLSA', 'MOCHILA', 'SACOLA', 'SACOCHILA', 'MALA', 'POCHETE', 'NECESSAIRE', 'FRASQUEIRA', 'ECOBAG'],
        'Tecnologia'            => ['FONE', 'MOUSE', 'POWER', 'PENDRIVE', 'CARREGADOR', 'SPEAKER', 'LANTERNA', 'HUB', 'WEBCAM', 'CABO', 'RELOGIO', 'SMARTWATCH', 'UMIDIFICADOR'],
        'Chaveiros e Acessorios'=> ['CHAVEIRO', 'PULSEIRA', 'ABRIDOR', 'LEQUE', 'ESPELHO', 'PORTA-JOIAS', 'PORTA-RETRATO'],
        'Casa e Cozinha'        => ['TABUA', 'MARMITA', 'PETISQUEIRA', 'TOALHA', 'TAPETE', 'ESCOVA', 'FRASCO', 'POTE', 'BANDEJA', 'CHALEIRA', 'CHURRASQUEIRA', 'LUMINARIA', 'SACA-ROLHAS'],
        'Escritorio'            => ['PASTA', 'ESTOJO', 'REGUA', 'CARTAO', 'SUPORTE', 'PAPEL', 'ADESIVO', 'PLAQUINHA'],
        'Vestuario'             => ['BONE', 'CAMISETA', 'JALECO', 'AVENTAL', 'CHAPEU'],
        'Guarda-chuvas'         => ['GUARDA-CHUVA', 'SOMBRINHA'],
        'Kits e Conjuntos'      => ['KIT', 'CONJUNTO', 'JOGO'],
        'Mouse Pads'            => ['MOUSE PAD', 'MOUSEPAD', 'DESKPAD', 'DESK PAD'],
        'Carteiras'             => ['CARTEIRA', 'PORTA CARTAO', 'PORTA-CARTAO', 'PORTA CARTOES'],
    ];

    /** Material por palavra-chave (str_contains). Stems escolhidos p/ evitar falsos positivos. */
    private const MATERIAIS = [
        'Aço Inox'        => ['INOX'],
        'Acrílico'        => ['ACRILIC'],
        'Bambu'           => ['BAMBU'],
        'Algodão'         => ['ALGODAO'],
        'Vidro'           => ['VIDRO'],
        'Cerâmica'        => ['CERAMIC', 'PORCELANA'],
        'Silicone'        => ['SILICONE'],
        'Madeira'         => ['MADEIRA', 'MDF'],
        'Couro'           => ['COURO'],
        'Alumínio'        => ['ALUMINIO'],
        'Papel / Kraft'   => ['KRAFT', 'PAPEL', 'CARTOLINA'],
        'Poliéster/Nylon' => ['POLIESTER', 'NYLON', 'OXFORD', 'LONA'],
        'Plástico'        => ['PLASTIC', 'POLIPROPILENO', 'POLICARBONATO', 'TRITAN', 'ACRILONITRILA', 'RPET'],
        'Metal'           => ['METAL', 'METALIC'],
    ];

    /**
     * Faixas de pedido mínimo por preço-base, em CENTAVOS: [teto, qtd_minima].
     * Ordem crescente; o 1º teto que cobrir o preço vence. Acima do último
     * teto aplica-se QTD_MINIMA_TOPO.
     *
     *   R$ 0,01 – R$  2,00 => 200 un.
     *   R$ 2,01 – R$  5,00 => 100 un.
     *   R$ 5,01 – R$ 20,00 =>  50 un.
     *   acima de R$ 20,00  =>  20 un.
     */
    private const FAIXAS_QTD_MINIMA = [
        [200,  200],
        [500,  100],
        [2000,  50],
    ];

    private const QTD_MINIMA_TOPO = 20;

    private const SUSTENTAVEL_KW = [
        'BAMBU', 'ECOLOG', 'ECOBAG', 'SUSTENTAVEL', 'RECICL', 'RPET',
        'KRAFT', 'ALGODAO', 'BIODEGRAD', 'CORTICA', 'FIBRA', 'TRIGO', 'RETORNAVEL',
    ];

    /**
     * Remove acentos e coloca em caixa alta (para casamento de palavras-chave).
     */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $mapa = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'Ê' => 'E', 'È' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ò' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];
        return strtr($texto, $mapa);
    }

    public static function categoria(string $nome): string
    {
        $n = self::normalizar($nome);
        $primeira = explode(' ', trim($n))[0] ?? '';

        // 1) Prioriza a primeira palavra (ex.: "KIT ..." => Kits).
        foreach (self::CATEGORIAS as $cat => $kws) {
            if (in_array($primeira, $kws, true)) {
                return $cat;
            }
        }
        // 2) Qualquer palavra-chave em qualquer posição.
        foreach (self::CATEGORIAS as $cat => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($n, $kw)) {
                    return $cat;
                }
            }
        }
        return 'Diversos';
    }

    public static function material(string $nome, string $descricao = ''): ?string
    {
        // O NOME indica o material primário; a descrição só complementa.
        return self::buscarMaterial(self::normalizar($nome))
            ?? self::buscarMaterial(self::normalizar($descricao));
    }

    private static function buscarMaterial(string $n): ?string
    {
        foreach (self::MATERIAIS as $material => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($n, $kw)) {
                    return $material;
                }
            }
        }
        return null;
    }

    public static function sustentavel(string $nome, string $descricao = ''): bool
    {
        $n = self::normalizar($nome . ' ' . $descricao);
        foreach (self::SUSTENTAVEL_KW as $kw) {
            if (str_contains($n, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pedido mínimo (em unidades) derivado do preço-base do produto.
     *
     * Comparação feita em centavos (int) para não escorregar em fronteira de
     * faixa por imprecisão de float — R$ 2,00 tem de cair em 200 un., e
     * R$ 2,01 em 100 un.
     *
     * Sem preço (NULL ou <= 0) => null: o produto simplesmente não exibe a
     * quantidade mínima, em vez de receber um valor chutado.
     */
    public static function quantidadeMinima(float|int|string|null $precoBase): ?int
    {
        if ($precoBase === null || $precoBase === '') {
            return null;
        }
        $centavos = (int) round((float) $precoBase * 100);
        if ($centavos <= 0) {
            return null;
        }
        foreach (self::FAIXAS_QTD_MINIMA as [$teto, $qtd]) {
            if ($centavos <= $teto) {
                return $qtd;
            }
        }
        return self::QTD_MINIMA_TOPO;
    }

    /**
     * Hex do swatch a partir do nome da cor da XBZ.
     * @return array{hex:string, conhecida:bool}
     */
    public static function corHex(string $nomeCor): array
    {
        $chave = self::normalizar(trim($nomeCor));
        if ($chave !== '' && isset(self::COR_HEX[$chave])) {
            return ['hex' => self::COR_HEX[$chave], 'conhecida' => true];
        }
        return ['hex' => self::COR_HEX_GENERICO, 'conhecida' => false];
    }

    /**
     * Extrai o sufixo de cor do CodigoComposto usando o CodigoAmigavel (pai).
     * Ex.: ('06520-AZU', '06520') => 'AZU'. Sem sufixo => 'UNICO'.
     */
    public static function sufixoCor(string $codigoComposto, string $codigoAmigavel): string
    {
        $comp = trim($codigoComposto);
        $amig = trim($codigoAmigavel);

        if ($amig !== '' && str_starts_with($comp, $amig)) {
            $suf = ltrim(substr($comp, strlen($amig)), '-');
            return $suf !== '' ? $suf : 'UNICO';
        }
        if (str_contains($comp, '-')) {
            return substr($comp, strpos($comp, '-') + 1);
        }
        return 'UNICO';
    }

    /**
     * Gera uma string de tags (palavras-chaves e sinônimos) para otimizar e ampliar a busca do produto.
     */
    public static function gerarTags(string $categoria, string $nome, string $descricao = ''): string
    {
        $tags = [];
        $textoComp = self::normalizar($nome . ' ' . $categoria . ' ' . $descricao);

        // Sinônimos por categoria
        $sinonimosCategorias = [
            'CANETAS' => ['CANETA', 'LAPIS', 'LAPISEIRA', 'ESFEROGRAFICA', 'PAPELARIA', 'ESCRITORIO', 'ROLLER', 'MARCADOR', 'GIZ', 'PENA', 'DESENHO', 'ANOTACOES'],
            'MOLESKINE & CADERNOS' => ['CADERNO', 'CADERNETA', 'BLOCO', 'PLANNER', 'AGENDA', 'CALENDARIO', 'SKETCHBOOK', 'MOLESKINE', 'PAPELARIA', 'NOTAS', 'DIARIO', 'REGISTRO', 'ESCRITORIO'],
            'BOLSAS E MOCHILAS' => ['BOLSA', 'MOCHILA', 'SACOLA', 'SACOCHILA', 'MALA', 'POCHETE', 'NECESSAIRE', 'FRASQUEIRA', 'ECOBAG', 'VIAGEM', 'TRANSPORTE', 'ACADEMIA', 'BAG', 'MALETA'],
            'GARRAFAS E SQUEEZES' => ['GARRAFA', 'SQUEEZE', 'COQUETELEIRA', 'GALAO', 'ACADEMIA', 'ESPORTE', 'HIDRATACAO', 'TERMICA', 'COPO', 'CANECA', 'CANTIL', 'BEBIDA', 'AGUA'],
            'CANECAS E COPOS' => ['CANECA', 'COPO', 'XICARA', 'TUMBLER', 'STANLEY', 'BEBIDA', 'CAFE', 'TERMICOS', 'XICARA', 'PINT', 'CHAMPANHE', 'CERVEJA'],
            'TECNOLOGIA' => ['FONE', 'MOUSE', 'POWER', 'PENDRIVE', 'CARREGADOR', 'SPEAKER', 'LANTERNA', 'HUB', 'WEBCAM', 'CABO', 'RELOGIO', 'SMARTWATCH', 'UMIDIFICADOR', 'ELETRONICO', 'GIFT', 'DIAL', 'HEADSET'],
            'KITS E CONJUNTOS' => ['KIT', 'CONJUNTO', 'JOGO', 'ONBOARDING', 'BOAS VINDAS', 'CORPORATIVO', 'INTEGRACAO', 'PRESENTE', 'CAIXA', 'WELCOME', 'BOAS-VINDAS'],
            'MOUSE PADS' => ['MOUSEPAD', 'MOUSE PAD', 'DESKPAD', 'DESK PAD', 'TECNOLOGIA', 'ESCRITORIO'],
            'CARTEIRAS' => ['CARTEIRA', 'PORTA-CARTAO', 'PORTA CARTAO', 'PORTA CARTOES', 'COURO', 'ORGANIZADOR'],
        ];

        $catUpper = self::normalizar($categoria);
        if (isset($sinonimosCategorias[$catUpper])) {
            $tags = array_merge($tags, $sinonimosCategorias[$catUpper]);
        }

        // Busca por palavras-chave específicas e injeta sinônimos correspondentes
        $sinonimosTermos = [
            'MOCHILA' => ['MALETA', 'MOCHILAS', 'BAG', 'PASTA', 'CAMPING', 'TRILHA', 'EXECUTIVA'],
            'ECOBAG' => ['SACOLA', 'ALGODAO', 'SUSTENTAVEL', 'ECOLOGICA', 'REUTILIZAVEL'],
            'CANETA' => ['ESFEROGRAFICA', 'CANETAS', 'ESCRITA', 'LAPIS', 'BRINDE'],
            'CADERNO' => ['CADERNETA', 'CADERNOS', 'BLOCO', 'ANOTACOES', 'DIARIO'],
            'GARRAFA' => ['GARRAFAS', 'SQUEEZE', 'SQUEEZES', 'TERMICA', 'TERMICO', 'BEBIDA', 'CANTIL'],
            'COPO' => ['COPOS', 'STANLEY', 'CANECA', 'CANECAS', 'XICARA', 'TERMICO'],
            'TECNOLOGIA' => ['CARREGADOR', 'FONTE', 'POWERBANK', 'CABO', 'CELULAR', 'SMARTPHONE', 'WIRELESS', 'INDUCACAO', 'INDUCAO'],
            'ONBOARDING' => ['KIT', 'BOAS VINDAS', 'BOAS-VINDAS', 'INTEGRACAO', 'COLABORADOR'],
            'CHAVEIRO' => ['CHAVEIROS', 'ACESSORIO', 'PINGENTE', 'MOSQUETAO'],
            'TERMICA' => ['TERMICO', 'VACUO', 'TEMPERATURA', 'QUENTE', 'FRIO', 'CONSERVACAO'],
        ];

        foreach ($sinonimosTermos as $kw => $sinonimos) {
            if (str_contains($textoComp, $kw)) {
                $tags = array_merge($tags, $sinonimos);
            }
        }

        // Limpa palavras vazias, duplicados e junta em string única separada por espaços
        $tags = array_unique(array_filter($tags));
        
        return implode(' ', $tags);
    }
}
