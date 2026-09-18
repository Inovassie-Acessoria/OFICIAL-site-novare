<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/ProductMapper.php';
require_once __DIR__ . '/SiteContent.php';
require_once __DIR__ . '/../config/helpers.php'; // urlAbsoluta(), whatsappLink() — também fora do bootstrap web (CLI/migrate-web)

/**
 * Núcleo de SEO: URLs canônicas, slugs, títulos e JSON-LD.
 *
 * Toda URL pública do catálogo nasce AQUI. Views e controllers nunca montam
 * "/brindes/..." na mão — se a estrutura de URL mudar, muda em um lugar só.
 *
 * Estrutura de URL:
 *   /                                  home
 *   /brindes                           hub do catálogo (todas as categorias)
 *   /brindes/{categoria}               categoria
 *   /brindes/{categoria}/material/{m}  faceta promovida (só as da tabela seo_facetas)
 *   /brindes/produto/{slug}            produto-pai
 *   /{ocasiao}                         landing de ocasião (tabela seo_ocasioes)
 *
 * URLs antigas (/produto/{sku}, /catalogo?categoria=X) respondem 301 — ver
 * CatalogController::redirecionarLegado().
 */
final class Seo
{
    public const MARCA      = 'Novare Brindes';
    public const SEPARADOR  = ' | ';
    public const LIMITE_TITLE = 60;
    public const LIMITE_DESC  = 158;

    /** Segmentos reservados sob /brindes/ que nunca são slug de categoria. */
    public const SEGMENTOS_RESERVADOS = ['produto', 'material'];

    /** @var array<string,array>|null mapa nome-da-categoria (lower) => linha de categorias */
    private static ?array $categorias = null;

    // ============================================================
    // URLs
    // ============================================================

    /**
     * URL canônica absoluta a partir de um PATH lógico. Nunca usa REQUEST_URI:
     * qualquer ?utm_source= viraria uma canônica diferente.
     */
    public static function canonical(string $path = '/'): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');
        return self::host() . ($path === '' ? '/' : $path);
    }

    /** Host canônico (sem barra no fim). Vem do .env (APP_URL / SITE_DOMAIN). */
    public static function host(): string
    {
        return rtrim(urlAbsoluta('/'), '/');
    }

    public static function urlHub(): string
    {
        return '/brindes';
    }

    /**
     * URL raiz-relativa do produto. Usa o slug congelado; se um produto ainda
     * não tiver slug (migração não rodou), cai no SKU para nunca gerar link quebrado.
     *
     * @param array{slug?:?string,sku_pai:string} $p
     */
    public static function urlProduto(array $p): string
    {
        $slug = trim((string) ($p['slug'] ?? ''));
        if ($slug === '') {
            return '/produto/' . rawurlencode((string) $p['sku_pai']);
        }
        return '/brindes/produto/' . rawurlencode($slug);
    }

    /** URL raiz-relativa da categoria pelo NOME gravado em produtos.categoria. */
    public static function urlCategoria(?string $nome): string
    {
        $cat = self::categoriaPorNome($nome);
        if ($cat === null) {
            return '/brindes';
        }
        return '/brindes/' . rawurlencode($cat['slug']);
    }

    public static function urlCategoriaSlug(string $slug): string
    {
        return '/brindes/' . rawurlencode($slug);
    }

    public static function urlFaceta(string $categoriaSlug, string $atributo, string $valorSlug): string
    {
        return '/brindes/' . rawurlencode($categoriaSlug) . '/' . rawurlencode($atributo) . '/' . rawurlencode($valorSlug);
    }

    public static function urlOcasiao(string $slug): string
    {
        return '/' . rawurlencode($slug);
    }

    // ============================================================
    // CATEGORIAS (tabela categorias, cacheada)
    // ============================================================

    /** @return array<string,array> chave = nome em minúsculas */
    public static function categorias(): array
    {
        if (self::$categorias !== null) {
            return self::$categorias;
        }
        self::$categorias = Cache::remember('seo:categorias', 3600, static function (): array {
            try {
                $rows = Database::connection()
                    ->query('SELECT * FROM categorias ORDER BY ordem ASC, nome ASC')
                    ->fetchAll();
            } catch (Throwable $e) {
                return []; // migração ainda não rodou
            }
            $map = [];
            foreach ($rows as $r) {
                $map[mb_strtolower(trim((string) $r['nome']), 'UTF-8')] = $r;
            }
            return $map;
        });
        return self::$categorias;
    }

    public static function limparCacheCategorias(): void
    {
        self::$categorias = null;
        Cache::forget('seo:categorias');
    }

    public static function categoriaPorNome(?string $nome): ?array
    {
        $nome = mb_strtolower(trim((string) $nome), 'UTF-8');
        if ($nome === '') {
            return null;
        }
        $cats = self::categorias();
        if (isset($cats[$nome])) {
            return $cats[$nome];
        }
        // aliases (nomes antigos de categoria ainda em links externos/anúncios)
        foreach ($cats as $c) {
            foreach (self::aliases($c) as $alias) {
                if ($alias === $nome) {
                    return $c;
                }
            }
        }
        return null;
    }

    public static function categoriaPorSlug(string $slug): ?array
    {
        $slug = mb_strtolower(trim($slug), 'UTF-8');
        foreach (self::categorias() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    /** @return string[] aliases em minúsculas */
    private static function aliases(array $c): array
    {
        $raw = (string) ($c['aliases'] ?? '');
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn ($a) => mb_strtolower(trim((string) $a), 'UTF-8'),
            explode(',', $raw)
        )));
    }

    // ============================================================
    // SLUG
    // ============================================================

    /** Slug ASCII minúsculo com hífens. Determinístico. */
    public static function slugify(string $texto): string
    {
        $texto = trim($texto);
        // remove acentos sem depender de iconv (comportamento varia entre hosts)
        $texto = ProductMapper::normalizar($texto);
        $texto = strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');
        return $texto !== '' ? substr($texto, 0, 180) : 'item';
    }

    /**
     * Slug de produto: nome + SKU. O SKU no fim garante unicidade e mantém a
     * referência que o cliente B2B usa no WhatsApp ("cod. 15016").
     */
    public static function slugProduto(string $nome, string $skuPai): string
    {
        $base = self::slugify($nome);
        $sku  = self::slugify($skuPai);
        $slug = $base === 'item' ? $sku : $base . '-' . $sku;
        return substr($slug, 0, 180);
    }

    /** Garante slug único na tabela (sufixo -2, -3…). */
    public static function slugUnico(PDO $pdo, string $base, string $tabela = 'produtos', ?int $ignorarId = null): string
    {
        $slug = $base;
        $i = 2;
        $sql = "SELECT id FROM {$tabela} WHERE slug = :s" . ($ignorarId ? ' AND id <> :id' : '') . ' LIMIT 1';
        $st = $pdo->prepare($sql);
        while (true) {
            $params = [':s' => $slug];
            if ($ignorarId) {
                $params[':id'] = $ignorarId;
            }
            $st->execute($params);
            if (!$st->fetchColumn()) {
                return $slug;
            }
            $slug = substr($base, 0, 170) . '-' . $i++;
        }
    }

    // ============================================================
    // TEXTO: title e description
    // ============================================================

    public static function truncar(string $texto, int $limite): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', strip_tags($texto)));
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }
        $corte = mb_substr($texto, 0, $limite - 1);
        $pos   = mb_strrpos($corte, ' ');
        return rtrim($pos ? mb_substr($corte, 0, $pos) : $corte, " ,.;:-") . '…';
    }

    /**
     * Title com a marca UMA vez, sempre no fim. Corta no servidor para
     * controlarmos onde corta, e não o Google. Idempotente: se o texto já
     * termina com a marca, não duplica (era o bug "| Novare Brindes | Novare Brindes").
     */
    public static function title(string $principal, bool $comMarca = true): string
    {
        $principal = trim($principal);
        // remove sufixos de marca que já vieram no texto
        $principal = (string) preg_replace('/\s*[|\-–—]\s*Novare Brindes( Corporativos)?\s*$/iu', '', $principal);
        if ($principal === '') {
            return self::MARCA;
        }
        if (!$comMarca) {
            return $principal;
        }
        $sufixo = self::SEPARADOR . self::MARCA;
        if (mb_strlen($principal . $sufixo) > self::LIMITE_TITLE) {
            $principal = self::truncar($principal, self::LIMITE_TITLE - mb_strlen($sufixo));
        }
        return $principal . $sufixo;
    }

    public static function description(string $texto): string
    {
        return self::truncar($texto, self::LIMITE_DESC);
    }

    /** "Nome" em caixa mista para uso em frases (os nomes da XBZ vêm em CAIXA ALTA). */
    public static function nomeLegivel(string $nome): string
    {
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
        if ($nome === '' || $nome !== mb_strtoupper($nome, 'UTF-8')) {
            return $nome;
        }
        $t = mb_convert_case(mb_strtolower($nome, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        // preposições em minúsculas; siglas/medidas mantidas
        $t = (string) preg_replace_callback('/\b(De|Da|Do|Das|Dos|E|Em|Com|Para|Por|A|O)\b/u', static fn ($m) => mb_strtolower($m[1], 'UTF-8'), $t);
        $t = (string) preg_replace_callback('/\b(\d+)(Ml|Gb|Cm|Mm|Kg|Mah|Tb|Pol|W|Led|Usb|Pu|Pvc|Abs|Tnt|Mdf|Inox|Rpet|Pet|Cd|Dvd)\b/u', static fn ($m) => $m[1] . mb_strtoupper($m[2], 'UTF-8'), $t);
        $t = (string) preg_replace_callback('/\b(Usb|Led|Pvc|Abs|Tnt|Mdf|Pu|Rpet|Pet|Tv|Uv|Sd|Cd|Dvd|Bt|Rgb|Gps|Mp3|Fm|Am|Hd|Ip|Ipx|Xl|Xxl|Gg|Pp|Oled|Lcd|Pc|Pp|Eva)\b/u', static fn ($m) => mb_strtoupper($m[1], 'UTF-8'), $t);
        return $t;
    }

    // ============================================================
    // DADOS OPERACIONAIS (globais no admin, override por produto)
    // ============================================================

    /** @return array{prazo:?string,tecnicas:?string,area:?string} */
    public static function operacional(?array $p = null): array
    {
        $g = Settings::get('seo_operacional', []);
        $g = is_array($g) ? $g : [];
        $pega = static function (string $chaveProduto, string $chaveGlobal) use ($p, $g): ?string {
            $v = trim((string) ($p[$chaveProduto] ?? ''));
            if ($v === '' || $v === '0') {
                $v = trim((string) ($g[$chaveGlobal] ?? ''));
            }
            return $v === '' ? null : $v;
        };
        return [
            'prazo'    => $pega('prazo_producao_dias', 'prazo'),
            'tecnicas' => $pega('tecnicas_personalizacao', 'tecnicas'),
            'area'     => $pega('area_impressao', 'area'),
        ];
    }

    /** Texto humano do prazo: "10 a 15 dias úteis" ou "12 dias úteis". */
    public static function prazoTexto(?string $prazo): ?string
    {
        if ($prazo === null || $prazo === '') {
            return null;
        }
        if (ctype_digit($prazo)) {
            return $prazo . ' dias úteis';
        }
        return str_contains($prazo, 'dia') ? $prazo : $prazo . ' dias úteis';
    }

    // ============================================================
    // INDEXABILIDADE (portão de qualidade — ver SeoGate)
    // ============================================================

    /** O portão só decide o índice quando o admin liga a chave. */
    public static function portaoAtivo(): bool
    {
        return (bool) Settings::get('seo_portao_ativo', false);
    }

    /**
     * Decisão ÚNICA de indexabilidade de um produto, usada pela meta robots,
     * pelo sitemap e pelos links internos — os três precisam concordar.
     */
    public static function produtoIndexavel(array $p): bool
    {
        if (empty($p['ativo']) || empty($p['imagem_principal'])) {
            return false;
        }
        if (!self::portaoAtivo()) {
            return true;
        }
        return !empty($p['seo_indexavel']);
    }

    // ============================================================
    // JSON-LD
    // ============================================================

    public static function schemaOrganization(): array
    {
        $logo = SiteContent::logoUrl();
        $org = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            '@id'         => self::host() . '/#organization',
            'name'        => self::MARCA,
            'url'         => self::host() . '/',
            'logo'        => ['@type' => 'ImageObject', 'url' => urlAbsoluta($logo)],
            'description' => 'Fornecedora brasileira de brindes corporativos personalizados para empresas: '
                           . 'catálogo com milhares de itens, personalização com a logo do cliente e '
                           . 'atendimento consultivo por WhatsApp para todo o Brasil.',
            'areaServed'  => ['@type' => 'Country', 'name' => 'Brasil'],
            'contactPoint' => [[
                '@type'             => 'ContactPoint',
                'telephone'         => '+' . whatsappNumero(),
                'contactType'       => 'sales',
                'areaServed'        => 'BR',
                'availableLanguage' => ['Portuguese'],
            ]],
        ];
        $ent = Settings::get('seo_entidade', []);
        $ent = is_array($ent) ? $ent : [];
        if (!empty($ent['razao_social'])) {
            $org['legalName'] = $ent['razao_social'];
        }
        if (!empty($ent['cnpj'])) {
            $org['taxID'] = $ent['cnpj'];
        }
        if (!empty($ent['cidade'])) {
            $org['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $ent['endereco'] ?? null,
                'addressLocality' => $ent['cidade'],
                'addressRegion'   => $ent['uf'] ?? null,
                'postalCode'      => $ent['cep'] ?? null,
                'addressCountry'  => 'BR',
            ]);
        }
        $perfis = array_values(array_filter(array_map('trim', explode("\n", (string) ($ent['perfis'] ?? '')))));
        if ($perfis) {
            $org['sameAs'] = $perfis;
        }
        return $org;
    }

    public static function schemaWebSite(): array
    {
        return [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebSite',
            '@id'       => self::host() . '/#website',
            'url'       => self::host() . '/',
            'name'      => self::MARCA,
            'inLanguage' => 'pt-BR',
            'publisher' => ['@id' => self::host() . '/#organization'],
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => self::host() . '/busca?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param array<int,array{name:string,url:string}> $itens */
    public static function schemaBreadcrumb(array $itens): array
    {
        $lista = [];
        foreach (array_values($itens) as $i => $it) {
            $lista[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $it['name'],
                'item'     => str_starts_with($it['url'], 'http') ? $it['url'] : self::canonical($it['url']),
            ];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $lista];
    }

    /**
     * Product SEM offers: o site é consultivo e não publica preço. Marcar um
     * preço que o usuário não vê (o antigo "0.00") é o que gera ação manual.
     *
     * @param string[] $imagens
     */
    public static function schemaProduct(array $p, array $imagens, ?string $categoriaNome, array $op): array
    {
        $url  = self::canonical(self::urlProduto($p));
        $nome = self::nomeLegivel((string) $p['nome']);
        $desc = trim((string) ($p['descricao_propria'] ?? '')) ?: (string) ($p['descricao'] ?? '');

        $s = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            '@id'         => $url . '#product',
            'name'        => $nome . ' Personalizado',
            'description' => self::truncar($desc, 300),
            'sku'         => (string) $p['sku_pai'],
            'image'       => array_values(array_map(static fn ($i) => str_starts_with($i, 'http') ? $i : urlAbsoluta($i), array_slice($imagens, 0, 6))),
            'brand'       => ['@type' => 'Brand', 'name' => self::MARCA],
            'url'         => $url,
        ];
        if ($categoriaNome) {
            $s['category'] = $categoriaNome;
        }
        $props = [];
        if (!empty($p['material'])) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Material', 'value' => $p['material']];
        }
        if (!empty($p['quantidade_minima'])) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Quantidade mínima', 'value' => (int) $p['quantidade_minima'], 'unitCode' => 'C62'];
        }
        if ($op['tecnicas']) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Personalização', 'value' => $op['tecnicas']];
        }
        if ($op['area']) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Área de impressão', 'value' => $op['area']];
        }
        if ($props) {
            $s['additionalProperty'] = $props;
        }
        return array_filter($s, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** @param array<int,array> $produtos linhas com slug/sku_pai/nome */
    public static function schemaItemList(array $produtos, string $nome): array
    {
        $itens = [];
        foreach (array_values(array_slice($produtos, 0, 30)) as $i => $p) {
            $itens[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'url'      => self::canonical(self::urlProduto($p)),
                'name'     => self::nomeLegivel((string) $p['nome']),
            ];
        }
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => $nome,
            'numberOfItems'   => count($produtos),
            'itemListElement' => $itens,
        ];
    }

    /**
     * FAQPage só é válido se as perguntas estão VISÍVEIS na página. Quem chama
     * precisa renderizar o mesmo array com o partial 'faq'.
     * @param array<int,array{pergunta:string,resposta:string}> $faq
     */
    public static function schemaFaq(array $faq): ?array
    {
        if (count($faq) < 2) {
            return null;
        }
        $itens = [];
        foreach ($faq as $f) {
            $itens[] = [
                '@type'          => 'Question',
                'name'           => $f['pergunta'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $f['resposta'])],
            ];
        }
        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $itens];
    }

    // ============================================================
    // FAQ (tabela seo_faq)
    // ============================================================

    /**
     * FAQ de uma entidade + as globais, com fallback nas perguntas padrão do
     * site quando a tabela ainda está vazia (para a home nunca ficar sem bloco).
     *
     * @return array<int,array{pergunta:string,resposta:string}>
     */
    public static function faq(string $entidade, ?string $entidadeId = null, int $limite = 6, bool $incluirGlobal = true): array
    {
        $chave = 'seo:faq:' . $entidade . ':' . ($entidadeId ?? '') . ':' . (int) $incluirGlobal;
        return Cache::remember($chave, 3600, static function () use ($entidade, $entidadeId, $limite, $incluirGlobal): array {
            $rows = [];
            try {
                $pdo = Database::connection();
                if ($entidadeId !== null) {
                    $st = $pdo->prepare('SELECT pergunta, resposta FROM seo_faq WHERE entidade = :e AND entidade_id = :id ORDER BY ordem, id');
                    $st->execute([':e' => $entidade, ':id' => $entidadeId]);
                    $rows = $st->fetchAll();
                }
                if ($incluirGlobal && count($rows) < $limite) {
                    $st = $pdo->prepare('SELECT pergunta, resposta FROM seo_faq WHERE entidade = :e ORDER BY ordem, id');
                    $st->execute([':e' => 'global']);
                    $globais = $st->fetchAll();
                    // sem globais cadastradas no admin, completa com as perguntas padrão do site
                    $rows = array_merge($rows, $globais ?: self::faqPadrao());
                }
            } catch (Throwable $e) {
                $rows = $incluirGlobal ? self::faqPadrao() : [];
            }
            return array_slice(array_values($rows), 0, $limite);
        });
    }

    /** Perguntas padrão (visíveis + schema). Substituídas pelo admin via seo_faq global. */
    public static function faqPadrao(): array
    {
        return [
            [
                'pergunta' => 'Qual é a quantidade mínima para comprar brindes personalizados na Novare Brindes?',
                'resposta' => 'A quantidade mínima na Novare Brindes depende do valor unitário de cada brinde: itens de menor valor, como canetas e chaveiros, partem de 200 unidades; brindes intermediários, de 100 ou 50 unidades; e produtos de maior valor, como mochilas e garrafas térmicas, a partir de 20 unidades. O mínimo exato de cada item aparece na própria página do produto.',
            ],
            [
                'pergunta' => 'Como solicitar um orçamento de brindes corporativos na Novare Brindes?',
                'resposta' => 'Na Novare Brindes o orçamento é feito pelo WhatsApp: o cliente escolhe o produto no catálogo, clica em "Fazer orçamento" e a mensagem já sai com o nome e o código do brinde. A equipe comercial responde com valores por quantidade, opções de personalização, prazo e frete para a empresa.',
            ],
            [
                'pergunta' => 'A Novare Brindes personaliza os brindes com a logo da empresa?',
                'resposta' => 'Sim. Todos os brindes do catálogo da Novare Brindes podem ser personalizados com a logo ou a mensagem da empresa. A técnica de gravação é definida conforme o material do produto — silk-screen, tampografia, gravação a laser, impressão UV ou bordado — e a arte é aprovada pelo cliente antes da produção.',
            ],
            [
                'pergunta' => 'A Novare Brindes entrega brindes personalizados para todo o Brasil?',
                'resposta' => 'Sim. A Novare Brindes envia brindes corporativos personalizados para empresas em todas as regiões do Brasil, com frete calculado no orçamento conforme o volume do lote e o destino.',
            ],
        ];
    }

    // ============================================================
    // PAGINAÇÃO
    // ============================================================

    /**
     * Cada página é canônica de si mesma (apontar a 2 para a 1 faz o Google
     * descartar os itens internos). Acima da página 5 não vale gastar rastreio.
     * @return array{canonical:string,indexavel:bool,anterior:?string,proxima:?string}
     */
    public static function paginacao(int $atual, int $total, string $basePath, array $extras = []): array
    {
        $url = static function (int $n) use ($basePath, $extras): string {
            $q = $extras;
            if ($n > 1) {
                $q['pagina'] = $n;
            }
            return self::canonical($basePath) . ($q ? '?' . http_build_query($q) : '');
        };
        return [
            'canonical' => $url($atual),
            'indexavel' => $atual <= 5,
            'anterior'  => $atual > 1 ? $url($atual - 1) : null,
            'proxima'   => $atual < $total ? $url($atual + 1) : null,
        ];
    }

    // ============================================================
    // WHATSAPP COM CONTEXTO
    // ============================================================

    public static function whatsappProdutoLink(array $p): string
    {
        $nome = self::nomeLegivel((string) $p['nome']);
        return whatsappLink(whatsappProduto($nome, (string) $p['sku_pai']));
    }
}
