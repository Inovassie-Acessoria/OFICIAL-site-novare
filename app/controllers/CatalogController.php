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
        view('home', [
            'categorias' => $this->repo->categorias(),
            'destaques'  => $this->repo->destaques(8),
        ], 'Novare Brindes — Brindes corporativos personalizados');
    }

    public function catalogo(): void
    {
        $filtros = $this->filtrosDaQuery();
        $pagina  = max(1, qint('pagina', 1));
        $resultado = $this->repo->listar($filtros, $pagina, 24);

        view('catalogo', [
            'resultado'  => $resultado,
            'filtros'    => $filtros,
            'categorias' => $this->repo->categorias(),
            'materiais'  => $this->repo->materiais($filtros),
            'cores'      => $this->repo->cores($filtros),
            'faixa'      => $this->repo->faixaPreco(),
            'titulo_pagina' => $filtros['categoria'] ?? 'Catálogo',
        ], 'Catálogo — Novare Brindes');
    }

    public function busca(): void
    {
        $termo = q('q', '') ?? '';
        $filtros = $this->filtrosDaQuery();
        $pagina  = max(1, qint('pagina', 1));
        $resultado = $this->repo->listar($filtros, $pagina, 24);

        view('catalogo', [
            'resultado'  => $resultado,
            'filtros'    => $filtros,
            'categorias' => $this->repo->categorias(),
            'materiais'  => $this->repo->materiais($filtros),
            'cores'      => $this->repo->cores($filtros),
            'faixa'      => $this->repo->faixaPreco(),
            'titulo_pagina' => 'Resultados para "' . $termo . '"',
            'eh_busca'   => true,
        ], 'Busca: ' . $termo . ' — Novare Brindes');
    }

    public function produto(string $skuPai): void
    {
        $dados = $this->repo->buscarPorSkuPai($skuPai);
        if ($dados === null) {
            $this->erro404();
            return;
        }
        view('produto', $dados, e($dados['produto']['nome']) . ' — Novare Brindes');
    }

    public function institucional(string $pagina): void
    {
        $mapa = [
            'sobre'       => 'Sobre a Novare',
            'atendimento' => 'Atendimento B2B',
            'fidelidade'  => 'Programa de Fidelidade Corporativo',
        ];
        view('institucional', [
            'pagina' => $pagina,
            'titulo_pagina' => $mapa[$pagina] ?? 'Novare Brindes',
        ], ($mapa[$pagina] ?? 'Novare Brindes') . ' — Novare Brindes');
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
