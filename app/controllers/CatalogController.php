<?php

declare(strict_types=1);

/**
 * Controlador das páginas do catálogo.
 */
final class CatalogController
{
    private ProductRepository $repo;

    public function __construct()
    {
        $this->repo = ProductRepository::create();
    }

    public function home(): void
    {
        $meta = [
            'description' => 'Brindes corporativos personalizados de alta qualidade para empresas. Canetas personalizadas, kits onboarding, garrafas e presentes executivos sob medida.',
            'keywords' => 'brindes personalizados, brindes corporativos, canetas personalizadas, kit onboarding, novare brindes, brindes corporativos executivos',
            'canonical' => urlAbsoluta('/')
        ];

        view('home', [
            'categorias' => $this->repo->categorias(),
            'destaques'  => $this->repo->destaques(8),
        ], 'Novare Brindes — Brindes corporativos personalizados', $meta);
    }

    public function catalogo(): void
    {
        $filtros = $this->filtrosDaQuery();
        $pagina  = max(1, qint('pagina', 1));
        $resultado = $this->repo->listar($filtros, $pagina, 24);

        $categoriaSelecionada = $filtros['categoria'] ?? null;
        
        if ($categoriaSelecionada) {
            $tituloSEO = e($categoriaSelecionada) . ' Personalizados | Novare Brindes';
            $metaDesc = 'Confira nossa linha de ' . e($categoriaSelecionada) . ' personalizados para empresas. Brindes corporativos de alto padrão com entrega rápida para todo o Brasil.';
            $keywords = e($categoriaSelecionada) . ', brindes corporativos, brindes personalizados, novare brindes';
            $canonical = urlAbsoluta('/catalogo?categoria=' . rawurlencode($categoriaSelecionada));
            $breadcrumbs = [
                ['url' => urlAbsoluta('/'), 'name' => 'Início'],
                ['url' => urlAbsoluta('/catalogo'), 'name' => 'Catálogo'],
                ['url' => $canonical, 'name' => $categoriaSelecionada]
            ];
        } else {
            $tituloSEO = 'Catálogo de Brindes Corporativos Personalizados | Novare Brindes';
            $metaDesc = 'Explore nosso catálogo completo de brindes corporativos personalizados para eventos, feiras e kits onboarding. Solicite seu orçamento via WhatsApp.';
            $keywords = 'catálogo de brindes, brindes corporativos, brindes personalizados, kit onboarding, canetas personalizadas';
            $canonical = urlAbsoluta('/catalogo');
            $breadcrumbs = [
                ['url' => urlAbsoluta('/'), 'name' => 'Início'],
                ['url' => $canonical, 'name' => 'Catálogo']
            ];
        }

        $meta = [
            'description' => $metaDesc,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'breadcrumbs' => $breadcrumbs
        ];

        view('catalogo', [
            'resultado'  => $resultado,
            'filtros'    => $filtros,
            'categorias' => $this->repo->categorias(),
            'materiais'  => $this->repo->materiais($filtros),
            'cores'      => $this->repo->cores($filtros),
            'faixa'      => $this->repo->faixaPreco(),
            'titulo_pagina' => $categoriaSelecionada ?? 'Catálogo',
        ], $tituloSEO, $meta);
    }

    public function busca(): void
    {
        $termo = q('q', '') ?? '';
        $filtros = $this->filtrosDaQuery();
        $pagina  = max(1, qint('pagina', 1));
        $resultado = $this->repo->listar($filtros, $pagina, 24);

        // Regra P0 de SEO: Páginas de busca interna devem conter noindex para evitar duplicidade e lixo no índice do Google
        $meta = [
            'robots' => 'noindex, follow',
            'description' => 'Resultados de busca por "' . e($termo) . '" no catálogo de brindes personalizados da Novare Brindes.',
            'canonical' => urlAbsoluta('/busca?q=' . rawurlencode($termo))
        ];

        view('catalogo', [
            'resultado'  => $resultado,
            'filtros'    => $filtros,
            'categorias' => $this->repo->categorias(),
            'materiais'  => $this->repo->materiais($filtros),
            'cores'      => $this->repo->cores($filtros),
            'faixa'      => $this->repo->faixaPreco(),
            'titulo_pagina' => 'Resultados para "' . e($termo) . '"',
            'eh_busca'   => true,
        ], 'Busca: ' . e($termo) . ' — Novare Brindes', $meta);
    }

    public function produto(string $skuPai): void
    {
        $dados = $this->repo->buscarPorSkuPai($skuPai);
        if ($dados === null) {
            $this->erro404();
            return;
        }

        $nomeProd = $dados['produto']['nome'];
        $descProd = $dados['produto']['descricao'] ?? '';
        $categoria = $dados['produto']['categoria'] ?? 'Brindes';

        // Sanitização e formatação da meta descrição de produto (tamanho recomendado: 155 caracteres)
        $metaDesc = mb_substr(strip_tags($descProd), 0, 150);
        if (mb_strlen(strip_tags($descProd)) > 150) {
            $metaDesc .= '...';
        }
        if (empty($metaDesc)) {
            $metaDesc = 'Compre ' . e($nomeProd) . ' personalizado na Novare Brindes. Brindes corporativos de alto padrão para sua empresa com gravação sob medida.';
        }

        $meta = [
            'description' => $metaDesc,
            'keywords' => e($categoria) . ', ' . e($nomeProd) . ', brinde personalizado, brinde corporativo, novare brindes',
            'canonical' => urlAbsoluta('/produto/' . rawurlencode($skuPai)),
            'og_image' => $dados['produto']['imagem_principal'] ?? '',
            'breadcrumbs' => [
                ['url' => urlAbsoluta('/'), 'name' => 'Início'],
                ['url' => urlAbsoluta('/catalogo?categoria=' . rawurlencode($categoria)), 'name' => $categoria],
                ['url' => urlAbsoluta('/produto/' . rawurlencode($skuPai)), 'name' => $nomeProd]
            ],
            'product_schema' => [
                'nome' => $nomeProd,
                'descricao' => $descProd,
                'sku' => $skuPai,
                'imagem' => $dados['produto']['imagem_principal'] ?? ''
            ]
        ];

        view('produto', $dados, e($nomeProd) . ' | Novare Brindes', $meta);
    }

    public function institucional(string $pagina): void
    {
        $mapa = [
            'sobre'       => 'Sobre a Novare',
            'atendimento' => 'Atendimento B2B',
            'fidelidade'  => 'Programa de Fidelidade Corporativo',
        ];

        $descricoes = [
            'sobre'       => 'Conheça a história da Novare Brindes, nossa missão de elevar a percepção de marcas através de brindes personalizados desenvolvidos com excelência técnica.',
            'atendimento' => 'Fale com o nosso atendimento corporativo B2B dedicado. Atendimento ágil e consultoria personalizada para a cotação de brindes da sua empresa.',
            'fidelidade'  => 'Participe do programa de fidelidade corporativo da Novare Brindes e garanta benefícios exclusivos, descontos e prioridade em lotes de brindes.',
        ];

        $meta = [
            'description' => $descricoes[$pagina] ?? 'Novare Brindes Corporativos.',
            'keywords' => ($mapa[$pagina] ?? 'Novare Brindes') . ', brindes corporativos, brindes personalizados, novare',
            'canonical' => urlAbsoluta('/' . $pagina),
            'breadcrumbs' => [
                ['url' => urlAbsoluta('/'), 'name' => 'Início'],
                ['url' => urlAbsoluta('/' . $pagina), 'name' => $mapa[$pagina] ?? 'Institucional']
            ]
        ];

        view('institucional', [
            'pagina' => $pagina,
            'titulo_pagina' => $mapa[$pagina] ?? 'Novare Brindes',
        ], ($mapa[$pagina] ?? 'Novare Brindes') . ' | Novare Brindes', $meta);
    }

    public function status(): void
    {
        $checks = [
            'PHP >= 8.0 (' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'Extensão pdo_mysql'               => extension_loaded('pdo_mysql'),
        ];
        try {
            Database::connection()->query('SELECT 1');
            $checks['Conexão MySQL'] = true;
            $checks['Produtos cadastrados'] = (int) Database::connection()
                ->query('SELECT COUNT(*) FROM produtos WHERE ativo = 1')->fetchColumn() > 0;
        } catch (Throwable $e) {
            $checks['Conexão MySQL'] = false;
        }
        view('status', ['checks' => $checks], 'Status — Novare Brindes');
    }

    public function erro404(): void
    {
        http_response_code(404);
        view('erro', [
            'codigo' => 404,
            'titulo' => 'Página não encontrada',
            'msg'    => 'O conteúdo que você procura não existe ou foi movido.',
        ], '404 — Novare Brindes');
    }

    public function erro500(): void
    {
        http_response_code(500);
        view('erro', [
            'codigo' => 500,
            'titulo' => 'Algo deu errado',
            'msg'    => 'Tivemos um problema ao processar sua solicitação. Tente novamente em instantes.',
        ], 'Erro — Novare Brindes');
    }

    /** Extrai e normaliza filtros da query string. */
    private function filtrosDaQuery(): array
    {
        return array_filter([
            'q'                 => q('q'),
            'categoria'         => q('categoria'),
            'material'          => q('material'),
            'cor'               => q('cor'),
            'sustentavel'       => q('sustentavel'),
            'preco_min'         => q('preco_min'),
            'preco_max'         => q('preco_max'),
            'quantidade_minima' => q('quantidade_minima'),
            'ordenar'           => q('ordenar'),
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
