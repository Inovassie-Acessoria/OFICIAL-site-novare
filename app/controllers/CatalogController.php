<?php

declare(strict_types=1);

/**
 * Controlador das páginas do catálogo.
 *
 * Contrato de $meta entregue ao layout (partials/layout.php):
 *   description, canonical, indexavel (bool), og_image, breadcrumbs[],
 *   schemas[] (JSON-LD extra), faq[] (mesmas perguntas renderizadas na página).
 * O title vai no 3º argumento de view() SEM marca — o layout aplica Seo::title().
 */
final class CatalogController
{
    private ProductRepository $repo;

    /** Filtros que viram parâmetro "livre": página noindex,follow com canonical para a categoria. */
    private const FILTROS_LIVRES = ['material', 'cor', 'sustentavel', 'preco_min', 'preco_max', 'quantidade_minima', 'q'];

    public function __construct()
    {
        $this->repo = ProductRepository::create();
    }

    // ============================================================
    // HOME
    // ============================================================

    public function home(): void
    {
        $faq = Seo::faq('global', null, 6);
        $meta = [
            'description' => 'Brindes corporativos personalizados com a logo da sua empresa: canetas, mochilas, garrafas, kits de onboarding e presentes executivos. Orçamento rápido pelo WhatsApp, entrega para todo o Brasil.',
            'canonical'   => Seo::canonical('/'),
            'faq'         => $faq,
            'schemas'     => [Seo::schemaWebSite()],
        ];

        view('home', [
            'categorias' => $this->repo->categorias(),
            'destaques'  => $this->repo->destaques(8),
            'faq'        => $faq,
        ], 'Brindes Corporativos Personalizados', $meta);
    }

    // ============================================================
    // CATÁLOGO: hub, categoria, faceta
    // ============================================================

    /** /brindes — hub do catálogo (todas as categorias). */
    public function hub(): void
    {
        $this->listagem(null, null);
    }

    /** /brindes/{categoria} */
    public function categoria(string $slug): void
    {
        $cat = Seo::categoriaPorSlug($slug);
        if ($cat === null) {
            $this->erro404();
            return;
        }
        $this->listagem($cat, null);
    }

    /** /brindes/{categoria}/material/{valor} — faceta promovida (indexável) ou livre (noindex). */
    public function faceta(string $catSlug, string $atributo, string $valorSlug): void
    {
        $cat = Seo::categoriaPorSlug($catSlug);
        if ($cat === null || $atributo !== 'material') {
            $this->erro404();
            return;
        }
        $faceta = $this->facetaPorSlug($cat['slug'], $atributo, $valorSlug);
        if ($faceta === null) {
            // valor de material existente mas não promovido: trata como filtro livre
            $material = $this->materialPorSlug($cat['nome'], $valorSlug);
            if ($material === null) {
                $this->erro404();
                return;
            }
            $_GET['material'] = $material;
            $this->listagem($cat, null);
            return;
        }
        $_GET['material'] = $faceta['valor'];
        $this->listagem($cat, $faceta);
    }

    /**
     * Miolo compartilhado de hub / categoria / faceta.
     * @param array|null $cat    linha de categorias
     * @param array|null $faceta linha de seo_facetas (promovida)
     */
    private function listagem(?array $cat, ?array $faceta): void
    {
        $filtros = $this->filtrosDaQuery();
        if ($cat) {
            $filtros['categoria'] = $cat['nome'];
        }
        $pagina    = max(1, qint('pagina', 1));
        $resultado = $this->repo->listar($filtros, $pagina, 24);
        $totalPag  = (int) $resultado['total_paginas'];
        if ($pagina > 1 && $pagina > $totalPag) {
            $this->erro404();
            return;
        }

        // ---- caminho base (canonical, paginação, form de filtros)
        if ($faceta) {
            $basePath = Seo::urlFaceta($cat['slug'], $faceta['atributo'], $faceta['valor_slug']);
        } elseif ($cat) {
            $basePath = Seo::urlCategoriaSlug($cat['slug']);
        } else {
            $basePath = Seo::urlHub();
        }

        // ---- três camadas de faceta: promovida (path) / livre (param, noindex) / bloqueada (robots)
        $livres = array_intersect_key($filtros, array_flip(self::FILTROS_LIVRES));
        if ($faceta) {
            unset($livres['material']);
        }
        $temFiltroLivre = !empty($livres) || !empty($filtros['ordenar']);

        $pag = Seo::paginacao($pagina, $totalPag, $basePath);
        $canonical = $temFiltroLivre ? Seo::canonical($basePath) : $pag['canonical'];

        // ---- textos
        $nomeCat = $cat['nome'] ?? null;
        $total   = (int) $resultado['total'];
        // Frase com gênero certo ("Canetas Personalizadas", "Brindes de Tecnologia Personalizados")
        $rotulo = $cat ? (trim((string) ($cat['rotulo_seo'] ?? '')) ?: "{$nomeCat} Personalizados") : '';
        if ($faceta) {
            $h1    = "{$nomeCat} de {$faceta['valor_label']}: {$rotulo} com Logo";
            $title = "{$nomeCat} de {$faceta['valor_label']} — {$rotulo}";
            $desc  = $faceta['seo_description'] ?? sprintf(
                '%s de %s personalizados com a logo da sua empresa. %d modelos com quantidade mínima a partir de 20 unidades. Orçamento pelo WhatsApp na Novare Brindes.',
                $nomeCat, $faceta['valor_label'], $total
            );
            $intro = $faceta['intro_seo'] ?? '';
            $entidadeFaq = ['faceta', $cat['slug'] . '/' . $faceta['atributo'] . '/' . $faceta['valor_slug']];
            $indexavelBase = (bool) $faceta['seo_indexavel'] && $total >= 8;
        } elseif ($cat) {
            $h1    = "{$rotulo} com Logo";
            $title = $cat['seo_title'] ?: $rotulo;
            $desc  = $cat['seo_description'] ?: sprintf(
                '%s personalizados com a logo da sua empresa: %d modelos, quantidade mínima a partir de 20 unidades e orçamento rápido pelo WhatsApp. Novare Brindes, entrega para todo o Brasil.',
                $nomeCat, $total
            );
            $intro = (string) ($cat['intro_seo'] ?? '');
            $entidadeFaq = ['categoria', $cat['slug']];
            $indexavelBase = (bool) $cat['seo_indexavel'] && $total >= 8;
        } else {
            $h1    = 'Catálogo de Brindes Corporativos Personalizados';
            $title = 'Catálogo de Brindes Corporativos';
            $desc  = sprintf('Catálogo completo da Novare Brindes: %s brindes corporativos personalizados com a logo da sua empresa, em %d categorias. Quantidade mínima a partir de 20 unidades e orçamento pelo WhatsApp.', number_format($total, 0, ',', '.'), count($this->repo->categorias()));
            $intro = '';
            $entidadeFaq = ['global', null];
            $indexavelBase = true;
        }
        if ($pagina > 1) {
            $title .= " - Página {$pagina}";
        }

        $faq = $pagina === 1
            ? Seo::faq($entidadeFaq[0], $entidadeFaq[1], 6, $entidadeFaq[0] === 'global')
            : [];

        // ---- breadcrumbs
        $bc = [['url' => '/', 'name' => 'Início'], ['url' => Seo::urlHub(), 'name' => 'Brindes']];
        if ($cat) {
            $bc[] = ['url' => Seo::urlCategoriaSlug($cat['slug']), 'name' => $nomeCat];
        }
        if ($faceta) {
            $bc[] = ['url' => $basePath, 'name' => $faceta['valor_label']];
        }

        $meta = [
            'description' => Seo::description($desc),
            'canonical'   => $canonical,
            'indexavel'   => $indexavelBase && $pag['indexavel'] && !$temFiltroLivre,
            'breadcrumbs' => $bc,
            'faq'         => $faq,
            'og_image'    => $resultado['itens'][0]['imagem_local'] ?? ($resultado['itens'][0]['imagem_principal'] ?? null),
            'schemas'     => $resultado['itens'] ? [Seo::schemaItemList($resultado['itens'], $h1)] : [],
        ];

        view('catalogo', [
            'resultado'     => $resultado,
            'filtros'       => $filtros,
            'categorias'    => $this->repo->categorias(),
            'materiais'     => $this->repo->materiais($filtros),
            'cores'         => $this->repo->cores($filtros),
            'faixa'         => $this->repo->faixaPreco(),
            'titulo_pagina' => $h1,
            'categoria_atual' => $cat,
            'faceta_atual'  => $faceta,
            'intro_seo'     => $intro,
            'faq'           => $faq,
            'facetas_promovidas' => $cat && !$faceta ? $this->facetasPromovidas($cat['slug']) : [],
            'ocasioes'      => $pagina === 1 ? $this->ocasioesIndexaveis(6) : [],
            'base_path'     => $basePath,
            'paginacao'     => $pag,
            'breadcrumbs'   => $bc,
        ], $title, $meta);
    }

    // ============================================================
    // BUSCA (sempre noindex)
    // ============================================================

    public function busca(): void
    {
        $termo   = q('q', '') ?? '';
        $filtros = $this->filtrosDaQuery();
        $pagina  = max(1, qint('pagina', 1));
        $resultado = $this->repo->listar($filtros, $pagina, 24);

        $meta = [
            'indexavel'   => false,
            'description' => 'Resultados de busca por "' . $termo . '" no catálogo de brindes personalizados da Novare Brindes.',
            'canonical'   => Seo::canonical('/busca'),
        ];

        view('catalogo', [
            'resultado'     => $resultado,
            'filtros'       => $filtros,
            'categorias'    => $this->repo->categorias(),
            'materiais'     => $this->repo->materiais($filtros),
            'cores'         => $this->repo->cores($filtros),
            'faixa'         => $this->repo->faixaPreco(),
            'titulo_pagina' => 'Resultados para "' . $termo . '"',
            'eh_busca'      => true,
            'base_path'     => '/busca',
            'paginacao'     => Seo::paginacao($pagina, (int) $resultado['total_paginas'], '/busca', ['q' => $termo]),
            'breadcrumbs'   => [['url' => '/', 'name' => 'Início'], ['url' => '/busca', 'name' => 'Busca']],
        ], 'Busca: ' . $termo, $meta);
    }

    // ============================================================
    // PRODUTO
    // ============================================================

    /** /brindes/produto/{slug} */
    public function produto(string $slug): void
    {
        $dados = $this->repo->buscarPorSlug($slug);
        if ($dados === null) {
            // slug antigo (produto renomeado nunca regenera slug, mas por segurança) ou SKU
            $novo = $this->repo->slugPorSku($slug);
            if ($novo !== null && $novo !== $slug) {
                $this->redirect(Seo::urlProduto(['slug' => $novo, 'sku_pai' => $slug]));
                return;
            }
            $this->erro404();
            return;
        }

        $p   = $dados['produto'];
        $cat = Seo::categoriaPorNome($p['categoria'] ?? null);
        $op  = Seo::operacional($p);
        $nomeLegivel = Seo::nomeLegivel((string) $p['nome']);

        // imagens de todas as variações (galeria + schema)
        $imagens = [];
        foreach ($dados['variacoes'] as $v) {
            foreach ($v['imagens'] ?? [] as $img) {
                $imagens[] = $img;
            }
        }
        if (!$imagens && !empty($p['imagem_principal'])) {
            $imagens[] = $p['imagem_principal'];
        }
        $imagens = array_values(array_unique($imagens));

        $qtd  = (int) ($p['quantidade_minima'] ?? 0) ?: ProductMapper::quantidadeMinima($p['preco_base'] ?? null);
        $desc = $p['seo_description'] ?: sprintf(
            '%s personalizado com a logo da sua empresa.%s%s Orçamento rápido pelo WhatsApp na Novare Brindes.',
            $nomeLegivel,
            $qtd ? " Pedido mínimo de {$qtd} unidades." : '',
            $op['prazo'] ? ' Produção em ' . Seo::prazoTexto($op['prazo']) . ' após aprovação da arte.' : ''
        );

        $faq = Seo::faq('produto', (string) $p['sku_pai'], 5, true);
        $urlProduto = Seo::urlProduto($p);

        $bc = [
            ['url' => '/', 'name' => 'Início'],
            ['url' => Seo::urlHub(), 'name' => 'Brindes'],
        ];
        if ($cat) {
            $bc[] = ['url' => Seo::urlCategoriaSlug($cat['slug']), 'name' => $cat['nome']];
        }
        $bc[] = ['url' => $urlProduto, 'name' => $nomeLegivel];

        $meta = [
            'description' => Seo::description($desc),
            'canonical'   => Seo::canonical($urlProduto),
            'indexavel'   => Seo::produtoIndexavel($p),
            'og_type'     => 'product',
            'og_image'    => $p['imagem_local'] ?: ($imagens[0] ?? null),
            'breadcrumbs' => $bc,
            'faq'         => $faq,
            'schemas'     => [Seo::schemaProduct($p, $imagens, $cat['nome'] ?? null, $op)],
        ];

        $titulo = $p['seo_title'] ?: ($nomeLegivel . ' Personalizado');

        view('produto', $dados + [
            'nome_legivel' => $nomeLegivel,
            'categoria_row' => $cat,
            'operacional'  => $op,
            'qtd_minima'   => $qtd,
            'faq'          => $faq,
            'relacionados' => $this->repo->relacionados($p, 8),
            'url_produto'  => $urlProduto,
            'breadcrumbs'  => $bc,
        ], $titulo, $meta);
    }

    // ============================================================
    // LANDING DE OCASIÃO  /{slug}
    // ============================================================

    public function ocasiao(string $slug): bool
    {
        $oc = $this->ocasiaoPorSlug($slug);
        if ($oc === null) {
            return false;
        }
        $skus = array_values(array_filter(array_map('trim', explode(',', (string) ($oc['produtos_skus'] ?? '')))));
        $produtos = $skus ? $this->repo->porSkus($skus) : [];
        $faq = Seo::faq('ocasiao', $oc['slug'], 6, false);

        $catsRel = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($oc['categorias'] ?? '')))) as $cs) {
            $c = Seo::categoriaPorSlug($cs);
            if ($c) {
                $catsRel[] = $c;
            }
        }

        $bc = [['url' => '/', 'name' => 'Início'], ['url' => Seo::urlOcasiao($oc['slug']), 'name' => $oc['titulo']]];
        $meta = [
            'description' => Seo::description($oc['seo_description'] ?: $oc['intro']),
            'canonical'   => Seo::canonical(Seo::urlOcasiao($oc['slug'])),
            'indexavel'   => (bool) $oc['seo_indexavel'] && count($produtos) >= 4,
            'breadcrumbs' => $bc,
            'faq'         => $faq,
            'og_image'    => $produtos[0]['imagem_local'] ?? ($produtos[0]['imagem_principal'] ?? null),
            'schemas'     => $produtos ? [Seo::schemaItemList($produtos, $oc['h1'])] : [],
        ];
        view('ocasiao', [
            'ocasiao'     => $oc,
            'produtos'    => $produtos,
            'faq'         => $faq,
            'categorias_relacionadas' => $catsRel,
            'breadcrumbs' => $bc,
        ], $oc['seo_title'] ?: $oc['titulo'], $meta);
        return true;
    }

    // ============================================================
    // LEGADO: 301 das URLs antigas
    // ============================================================

    /** /produto/{sku} -> /brindes/produto/{slug} */
    public function produtoLegado(string $skuPai): void
    {
        $slug = $this->repo->slugPorSku($skuPai);
        if ($slug === null) {
            $this->erro404();
            return;
        }
        $this->redirect(Seo::urlProduto(['slug' => $slug, 'sku_pai' => $skuPai]));
    }

    /** /catalogo[?categoria=X&...] -> /brindes[/{cat}] preservando os demais filtros */
    public function catalogoLegado(): void
    {
        $params = $_GET;
        $destino = Seo::urlHub();
        if (!empty($params['categoria'])) {
            $cat = Seo::categoriaPorNome((string) $params['categoria']);
            if ($cat) {
                $destino = Seo::urlCategoriaSlug($cat['slug']);
            }
        }
        unset($params['categoria']);
        $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
        $this->redirect($destino . ($params ? '?' . http_build_query($params) : ''));
    }

    /** Tabela seo_redirects: produto descontinuado vai para o substituto (301) ou 410. */
    public function redirectTabela(string $path): bool
    {
        try {
            $st = Database::connection()->prepare('SELECT destino, status FROM seo_redirects WHERE origem = :o LIMIT 1');
            $st->execute([':o' => $path]);
            $r = $st->fetch();
        } catch (Throwable $e) {
            return false;
        }
        if (!$r) {
            return false;
        }
        $status = (int) $r['status'];
        if ($status === 410 || empty($r['destino'])) {
            http_response_code(410);
            view('erro', [
                'codigo' => 410,
                'titulo' => 'Produto descontinuado',
                'msg'    => 'Este brinde saiu de linha. Veja opções semelhantes no catálogo.',
            ], 'Produto descontinuado', ['indexavel' => false]);
            return true;
        }
        $this->redirect((string) $r['destino'], $status === 302 ? 302 : 301);
        return true;
    }

    // ============================================================
    // INSTITUCIONAL / STATUS / ERROS
    // ============================================================

    public function institucional(string $pagina): void
    {
        $mapa = [
            'sobre'       => 'Sobre a empresa',
            'atendimento' => 'Atendimento B2B',
            'fidelidade'  => 'Programa de Fidelidade Corporativo',
        ];
        $descricoes = [
            'sobre'       => 'A Novare Brindes é uma fornecedora brasileira de brindes corporativos personalizados: milhares de itens, personalização com a logo do cliente e atendimento consultivo por WhatsApp para empresas de todo o Brasil.',
            'atendimento' => 'Atendimento corporativo B2B da Novare Brindes: consultoria para escolha do brinde, orçamento por quantidade, aprovação de arte e entrega para todo o Brasil.',
            'fidelidade'  => 'Programa de fidelidade corporativo da Novare Brindes: benefícios, condições especiais e prioridade em lotes de brindes para empresas recorrentes.',
        ];
        $faq = $pagina === 'sobre' ? Seo::faq('global', null, 6) : [];

        $meta = [
            'description' => $descricoes[$pagina] ?? 'Novare Brindes Corporativos.',
            'canonical'   => Seo::canonical('/' . $pagina),
            'breadcrumbs' => [['url' => '/', 'name' => 'Início'], ['url' => '/' . $pagina, 'name' => $mapa[$pagina] ?? 'Institucional']],
            'faq'         => $faq,
        ];

        view('institucional', [
            'pagina'        => $pagina,
            'titulo_pagina' => $mapa[$pagina] ?? 'Novare Brindes',
            'faq'           => $faq,
        ], $mapa[$pagina] ?? 'Novare Brindes', $meta);
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
        view('status', ['checks' => $checks], 'Status', ['indexavel' => false]);
    }

    public function erro404(): void
    {
        // Antes do 404: a URL pode estar na tabela de redirects (produto
        // descontinuado -> substituto, ou 410). Vale para qualquer rota.
        $pathAtual = '/' . trim(rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')), '/');
        if ($pathAtual !== '/' && !headers_sent() && $this->redirectTabela($pathAtual)) {
            return;
        }
        http_response_code(404);
        view('erro', [
            'codigo' => 404,
            'titulo' => 'Página não encontrada',
            'msg'    => 'O conteúdo que você procura não existe ou foi movido.',
        ], 'Página não encontrada', ['indexavel' => false]);
    }

    public function erro500(): void
    {
        http_response_code(500);
        view('erro', [
            'codigo' => 500,
            'titulo' => 'Algo deu errado',
            'msg'    => 'Tivemos um problema ao processar sua solicitação. Tente novamente em instantes.',
        ], 'Erro', ['indexavel' => false]);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function redirect(string $path, int $status = 301): void
    {
        $url = str_starts_with($path, 'http') ? $path : Seo::host() . $path;
        header('Location: ' . $url, true, $status);
        header('Cache-Control: public, max-age=86400');
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

    private function facetaPorSlug(string $catSlug, string $atributo, string $valorSlug): ?array
    {
        try {
            $st = Database::connection()->prepare(
                'SELECT * FROM seo_facetas WHERE categoria_slug = :c AND atributo = :a AND valor_slug = :v AND seo_indexavel = 1 LIMIT 1'
            );
            $st->execute([':c' => $catSlug, ':a' => $atributo, ':v' => $valorSlug]);
            return $st->fetch() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<int,array> facetas promovidas de uma categoria (links reais em path) */
    private function facetasPromovidas(string $catSlug): array
    {
        return Cache::remember('facetas:' . $catSlug, 3600, static function () use ($catSlug): array {
            try {
                $st = Database::connection()->prepare(
                    'SELECT atributo, valor_slug, valor_label FROM seo_facetas WHERE categoria_slug = :c AND seo_indexavel = 1 ORDER BY valor_label'
                );
                $st->execute([':c' => $catSlug]);
                return $st->fetchAll();
            } catch (Throwable $e) {
                return [];
            }
        });
    }

    private function materialPorSlug(string $categoriaNome, string $valorSlug): ?string
    {
        foreach ($this->repo->materiais(['categoria' => $categoriaNome]) as $m) {
            if (Seo::slugify((string) $m['material']) === $valorSlug) {
                return (string) $m['material'];
            }
        }
        return null;
    }

    private function ocasiaoPorSlug(string $slug): ?array
    {
        try {
            $st = Database::connection()->prepare('SELECT * FROM seo_ocasioes WHERE slug = :s LIMIT 1');
            $st->execute([':s' => $slug]);
            return $st->fetch() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<int,array{slug:string,titulo:string}> */
    private function ocasioesIndexaveis(int $limite): array
    {
        return Cache::remember('ocasioes:idx:' . $limite, 3600, static function () use ($limite): array {
            try {
                return Database::connection()
                    ->query("SELECT slug, titulo FROM seo_ocasioes WHERE seo_indexavel = 1 ORDER BY id LIMIT {$limite}")
                    ->fetchAll();
            } catch (Throwable $e) {
                return [];
            }
        });
    }
}
