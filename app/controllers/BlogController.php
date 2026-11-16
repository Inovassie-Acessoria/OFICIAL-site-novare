<?php

declare(strict_types=1);

/**
 * Blog (/blog e /blog/{slug}).
 *
 * Matérias editadas no painel /settings-admin/seo#blog (tabela blog_posts).
 * Publicada = status "publicado" e publicado_em já passou. Rascunho e
 * agendada respondem 404 para o público; o admin logado vê a prévia com noindex.
 */
final class BlogController
{
    private const POR_PAGINA = 12;

    /** Tags que o corpo da matéria pode usar. Atributos perigosos são removidos em sanitizar(). */
    private const TAGS_CORPO = '<h2><h3><p><b><strong><em><i><br><a><ul><ol><li><table><thead><tbody><tr><th><td><blockquote>';

    // ============================================================
    // /blog
    // ============================================================

    public function index(): void
    {
        $pagina = max(1, qint('pagina', 1));
        $total  = $this->contarPublicados();
        $totalPag = max(1, (int) ceil($total / self::POR_PAGINA));
        if ($pagina > $totalPag) {
            (new CatalogController())->erro404();
            return;
        }
        $posts = $this->publicados(self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA);
        foreach ($posts as &$p) {
            $p['capa'] = $this->capa($p);
        }
        unset($p);

        $pag = Seo::paginacao($pagina, $totalPag, Seo::urlBlog());
        $bc  = [['url' => '/', 'name' => 'Início'], ['url' => Seo::urlBlog(), 'name' => 'Blog']];
        $titulo = 'Blog de Brindes Corporativos' . ($pagina > 1 ? " - Página {$pagina}" : '');

        $meta = [
            'description' => 'Guias e ideias de brindes corporativos personalizados: canetas, cadernos, mochilas, garrafas, copos e kits para fim de ano, onboarding, eventos e datas comemorativas.',
            'canonical'   => $pag['canonical'],
            // lista vazia é página rala: só indexa quando houver matéria
            'indexavel'   => $total > 0 && $pag['indexavel'],
            'breadcrumbs' => $bc,
            'og_image'    => $posts[0]['capa'] ?? null,
            'schemas'     => $posts ? [$this->schemaLista($posts)] : [],
        ];

        view('blog', [
            'posts'       => $posts,
            'paginacao'   => $pag,
            'pagina'      => $pagina,
            'breadcrumbs' => $bc,
        ], $titulo, $meta);
    }

    // ============================================================
    // /blog/{slug}
    // ============================================================

    public function post(string $slug): void
    {
        $post = $this->porSlug($slug);
        $publicado = $post && $post['status'] === 'publicado' && !empty($post['publicado_em']) && strtotime((string) $post['publicado_em']) <= time();
        $previa = $post && !$publicado && $this->adminLogado();
        if (!$post || (!$publicado && !$previa)) {
            (new CatalogController())->erro404();
            return;
        }

        $repo = ProductRepository::create();
        $skus = array_values(array_filter(array_map('trim', explode(',', (string) ($post['produtos_skus'] ?? '')))));
        $produtos = $skus ? $repo->porSkus($skus) : [];

        $catsRel = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($post['categorias'] ?? '')))) as $cs) {
            $c = Seo::categoriaPorSlug($cs);
            if ($c) {
                $catsRel[] = $c;
            }
        }

        [$corpo, $sumario] = $this->corpoComAncoras(self::sanitizar((string) ($post['corpo'] ?? '')));
        $palavras = str_word_count(strip_tags($post['resumo'] . ' ' . $corpo), 0, 'áàâãéêíóôõúüçÁÀÂÃÉÊÍÓÔÕÚÜÇ0123456789');
        $faq  = self::faqDoTexto((string) ($post['faq'] ?? ''));
        $capa = $this->capa($post, $produtos);

        $url = Seo::urlPost((string) $post['slug']);
        $bc  = [['url' => '/', 'name' => 'Início'], ['url' => Seo::urlBlog(), 'name' => 'Blog'], ['url' => $url, 'name' => $post['titulo']]];

        $meta = [
            'description'   => Seo::description((string) ($post['seo_description'] ?: $post['resumo'])),
            'canonical'     => Seo::canonical($url),
            'indexavel'     => $publicado,
            'og_type'       => 'article',
            'og_image'      => $capa,
            'preload_image' => $capa,
            'breadcrumbs'   => $bc,
            'faq'           => $faq,
            'schemas'       => [Seo::schemaBlogPosting($post, $capa, $palavras)],
        ];

        view('blog-post', [
            'post'        => $post,
            'corpo'       => $corpo,
            'sumario'     => $sumario,
            'capa'        => $capa,
            'minutos'     => max(1, (int) round($palavras / 200)),
            'produtos'    => $produtos,
            'faq'         => $faq,
            'categorias_relacionadas' => $catsRel,
            'relacionados' => $this->relacionados((int) $post['id'], 3),
            'previa'      => $previa,
            'breadcrumbs' => $bc,
        ], $post['seo_title'] ?: $post['titulo'], $meta);
    }

    // ============================================================
    // Usados também pelo sitemap, pelo llms.txt e pelo admin
    // ============================================================

    /**
     * SQL do que está no ar. Mesmo critério em todo lugar (página, lista, sitemap, llms.txt).
     * Compara com o relógio do PHP — o mesmo que grava publicado_em no painel e que a
     * página da matéria usa. O NOW() do MySQL pode estar em outro fuso na hospedagem,
     * e aí a lista e o sitemap divulgariam uma matéria que ainda dá 404 (ou o contrário).
     */
    public static function wherePublicado(): string
    {
        return "status = 'publicado' AND publicado_em IS NOT NULL AND publicado_em <= '" . date('Y-m-d H:i:s') . "'";
    }

    /**
     * Texto do campo FAQ do painel -> [['pergunta','resposta'], ...].
     * Formato: blocos separados por linha em branco; 1a linha é a pergunta, o resto é a resposta.
     */
    public static function faqDoTexto(string $texto): array
    {
        $faq = [];
        $blocos = preg_split("/\R\s*\R/u", trim(str_replace("\r", '', $texto))) ?: [];
        foreach ($blocos as $b) {
            $linhas = array_values(array_filter(array_map('trim', explode("\n", $b)), static fn ($l) => $l !== ''));
            if (count($linhas) < 2) {
                continue;
            }
            $faq[] = ['pergunta' => mb_substr($linhas[0], 0, 255), 'resposta' => implode("\n", array_slice($linhas, 1))];
        }
        return $faq;
    }

    /**
     * Mantém só as tags permitidas e tira atributos que executam código
     * (on*, style, javascript:). O corpo é escrito no painel, mas não custa blindar.
     */
    public static function sanitizar(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|iframe|object)\b[^>]*>.*?</\1\s*>#isu', '', $html);
        $html = strip_tags($html, self::TAGS_CORPO);
        $html = (string) preg_replace('/\s+(on[a-z]+|style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/(href\s*=\s*["\']?)\s*(javascript|data|vbscript):[^"\'\s>]*/iu', '$1#', $html);
        return trim($html);
    }

    // ============================================================
    // privados
    // ============================================================

    /** Coloca id em cada <h2> (link âncora do sumário) e devolve [html, [[id, texto], ...]]. */
    private function corpoComAncoras(string $html): array
    {
        $sumario = [];
        $usados = [];
        $html = (string) preg_replace_callback('#<h2>(.*?)</h2>#isu', static function (array $m) use (&$sumario, &$usados): string {
            $texto = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $id = Seo::slugify($texto) ?: 'secao';
            for ($i = 2; isset($usados[$id]); $i++) {
                $id = Seo::slugify($texto) . '-' . $i;
            }
            $usados[$id] = true;
            $sumario[] = [$id, $texto];
            return '<h2 id="' . e($id) . '">' . $m[1] . '</h2>';
        }, $html);
        return [$html, $sumario];
    }

    /** Capa: imagem_capa do painel; senão a foto do 1o SKU citado (local, se já convertida). */
    private function capa(array $post, ?array $produtos = null): ?string
    {
        $capa = trim((string) ($post['imagem_capa'] ?? ''));
        if ($capa !== '') {
            return $capa;
        }
        if ($produtos === null) {
            $skus = array_values(array_filter(array_map('trim', explode(',', (string) ($post['produtos_skus'] ?? '')))));
            $produtos = $skus ? ProductRepository::create()->porSkus(array_slice($skus, 0, 3)) : [];
        }
        $p = $produtos[0] ?? null;
        return $p ? (($p['imagem_local'] ?? '') ?: ($p['imagem_principal'] ?? null)) : null;
    }

    private function adminLogado(): bool
    {
        // só abre sessão se o cookie do painel existir: visitante comum não ganha sessão
        return isset($_COOKIE['novare_admin']) && AdminAuth::logado();
    }

    private function porSlug(string $slug): ?array
    {
        try {
            $st = Database::connection()->prepare('SELECT * FROM blog_posts WHERE slug = :s LIMIT 1');
            $st->execute([':s' => $slug]);
            return $st->fetch() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function contarPublicados(): int
    {
        try {
            return (int) Database::connection()->query('SELECT COUNT(*) FROM blog_posts WHERE ' . self::wherePublicado())->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function publicados(int $limite, int $offset): array
    {
        try {
            return Database::connection()->query(
                'SELECT id, slug, titulo, resumo, produtos_skus, imagem_capa, imagem_alt, publicado_em, updated_at FROM blog_posts WHERE '
                . self::wherePublicado() . ' ORDER BY publicado_em DESC, id DESC LIMIT ' . $limite . ' OFFSET ' . $offset
            )->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function relacionados(int $id, int $limite): array
    {
        try {
            $st = Database::connection()->prepare(
                'SELECT id, slug, titulo, resumo, produtos_skus, imagem_capa, imagem_alt, publicado_em FROM blog_posts WHERE '
                . self::wherePublicado() . ' AND id <> :id ORDER BY publicado_em DESC LIMIT ' . $limite
            );
            $st->execute([':id' => $id]);
            $rows = $st->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['capa'] = $this->capa($r);
        }
        return $rows;
    }

    private function schemaLista(array $posts): array
    {
        $itens = [];
        foreach (array_values($posts) as $i => $p) {
            $itens[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => Seo::canonical(Seo::urlPost((string) $p['slug'])), 'name' => $p['titulo']];
        }
        return ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Blog Novare Brindes', 'itemListElement' => $itens];
    }
}
