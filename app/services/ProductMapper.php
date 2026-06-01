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
        'Escrita'               => ['CANETA', 'LAPIS', 'LAPISEIRA', 'ROLLER'],
        'Cadernos e Agendas'    => ['CADERNO', 'CADERNETA', 'BLOCO', 'PLANNER', 'AGENDA', 'CALENDARIO', 'SKETCHBOOK'],
        'Bolsas e Mochilas'     => ['BOLSA', 'MOCHILA', 'SACOLA', 'SACOCHILA', 'MALA', 'POCHETE', 'NECESSAIRE', 'FRASQUEIRA', 'ECOBAG'],
        'Tecnologia'            => ['FONE', 'MOUSE', 'POWER', 'PENDRIVE', 'CARREGADOR', 'SPEAKER', 'LANTERNA', 'HUB', 'WEBCAM', 'CABO', 'RELOGIO', 'SMARTWATCH', 'UMIDIFICADOR'],
        'Chaveiros e Acessorios'=> ['CHAVEIRO', 'PULSEIRA', 'ABRIDOR', 'LEQUE', 'ESPELHO', 'PORTA-JOIAS', 'PORTA-RETRATO'],
        'Casa e Cozinha'        => ['TABUA', 'MARMITA', 'PETISQUEIRA', 'TOALHA', 'TAPETE', 'ESCOVA', 'FRASCO', 'POTE', 'BANDEJA', 'CHALEIRA', 'CHURRASQUEIRA', 'LUMINARIA', 'SACA-ROLHAS'],
        'Escritorio'            => ['PASTA', 'ESTOJO', 'REGUA', 'CARTAO', 'SUPORTE', 'PAPEL', 'ADESIVO', 'PLAQUINHA'],
        'Vestuario'             => ['BONE', 'CAMISETA', 'JALECO', 'AVENTAL', 'CHAPEU'],
        'Guarda-chuvas'         => ['GUARDA-CHUVA', 'SOMBRINHA'],
        'Kits e Conjuntos'      => ['KIT', 'CONJUNTO', 'JOGO'],
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
}
