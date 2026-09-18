<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/ProductMapper.php';
require_once __DIR__ . '/Seo.php';

/**
 * Migração de schema/dados do pacote de SEO. ADITIVA e IDEMPOTENTE:
 * só cria o que falta, nunca apaga nem recria tabela.
 *
 * Roda sozinha no primeiro request depois do deploy (SeoMigration::garantir()
 * no front controller), para não existir janela entre "subiu o código" e
 * "alguém clicou no botão" com o site quebrado por coluna inexistente.
 * Também pode ser disparada pelo configurador web (migrate-web.php).
 */
final class SeoMigration
{
    public const VERSAO = 3;

    /**
     * Categorias canônicas: nome (= valor gravado em produtos.categoria) => [slug, aliases].
     * Os aliases são nomes ANTIGOS que ainda circulam em links externos/anúncios e
     * que precisam continuar resolvendo (301) para a categoria certa.
     */
    private const CATEGORIAS = [
        'Canetas'                => ['canetas',                ['Escrita'],                                        'Canetas Personalizadas'],
        'Moleskine & Cadernos'   => ['cadernos-e-moleskines',  ['Cadernos e Agendas', 'Moleskine e Cadernos'],     'Cadernos e Moleskines Personalizados'],
        'Bolsas e Mochilas'      => ['bolsas-e-mochilas',      ['Mochilas e Bolsas', 'Sacolas'],                   'Bolsas e Mochilas Personalizadas'],
        'Garrafas e Squeezes'    => ['garrafas-e-squeezes',    ['Garrafas e Copos'],                               'Garrafas e Squeezes Personalizados'],
        'Canecas e Copos'        => ['canecas-e-copos',        [],                                                 'Canecas e Copos Personalizados'],
        'Tecnologia'             => ['tecnologia',             [],                                                 'Brindes de Tecnologia Personalizados'],
        'Chaveiros e Acessorios' => ['chaveiros-e-acessorios', [],                                                 'Chaveiros e Acessórios Personalizados'],
        'Casa e Cozinha'         => ['casa-e-cozinha',         [],                                                 'Brindes para Casa e Cozinha Personalizados'],
        'Escritorio'             => ['escritorio',             [],                                                 'Brindes de Escritório Personalizados'],
        'Vestuario'              => ['vestuario',              [],                                                 'Vestuário Corporativo Personalizado'],
        'Guarda-chuvas'          => ['guarda-chuvas',          [],                                                 'Guarda-chuvas Personalizados'],
        'Kits e Conjuntos'       => ['kits-e-conjuntos',       [],                                                 'Kits Corporativos Personalizados'],
        'Mouse Pads'             => ['mouse-pads',             [],                                                 'Mouse Pads Personalizados'],
        'Carteiras'              => ['carteiras',              [],                                                 'Carteiras Personalizadas'],
        'Diversos'               => ['diversos',               [],                                                 'Brindes Diversos Personalizados'],
    ];

    /** Chamada barata no front controller: só roda a migração se a versão gravada for antiga. */
    public static function garantir(): void
    {
        try {
            if ((int) Settings::get('seo_schema_versao', 0) >= self::VERSAO) {
                return;
            }
            $lock = dirname(__DIR__, 2) . '/storage/cache/.seo-migracao.lock';
            $fp = @fopen($lock, 'c');
            if ($fp === false) {
                return;
            }
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                fclose($fp);
                return; // outro request já está migrando
            }
            try {
                self::executar();
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        } catch (Throwable $e) {
            error_log('[SeoMigration] ' . $e->getMessage());
        }
    }

    /** @return array<string,int|string> resumo do que foi feito */
    public static function executar(): array
    {
        $pdo = Database::connection();
        $r = ['colunas' => 0, 'tabelas' => 0, 'categorias_normalizadas' => 0, 'slugs' => 0];

        // ------------------------------------------------------------
        // 1. Colunas novas em produtos
        // ------------------------------------------------------------
        $colunas = [
            'slug'                    => 'VARCHAR(190) NULL AFTER nome',
            'descricao_propria'       => 'TEXT NULL AFTER descricao',
            'prazo_producao'          => 'VARCHAR(40) NULL',
            'tecnicas_personalizacao' => 'VARCHAR(255) NULL',
            'area_impressao'          => 'VARCHAR(120) NULL',
            'seo_title'               => 'VARCHAR(70) NULL',
            'seo_description'         => 'VARCHAR(180) NULL',
            'seo_indexavel'           => 'TINYINT(1) NOT NULL DEFAULT 0',
            'seo_motivo'              => 'VARCHAR(255) NULL',
            'seo_avaliado_em'         => 'DATETIME NULL',
            'conteudo_hash'           => 'CHAR(32) NULL',
            'conteudo_alterado_em'    => 'DATETIME NULL',
            'imagem_local'            => 'VARCHAR(255) NULL',
            'imagem_local_origem'     => 'VARCHAR(512) NULL',
        ];
        foreach ($colunas as $nome => $def) {
            if (!self::colunaExiste($pdo, 'produtos', $nome)) {
                $pdo->exec("ALTER TABLE produtos ADD COLUMN {$nome} {$def}");
                $r['colunas']++;
            }
        }
        self::indice($pdo, 'produtos', 'ux_produtos_slug', 'UNIQUE INDEX ux_produtos_slug (slug)');
        self::indice($pdo, 'produtos', 'idx_seo_indexavel', 'INDEX idx_seo_indexavel (seo_indexavel, ativo)');
        self::indice($pdo, 'produtos', 'idx_conteudo_alterado', 'INDEX idx_conteudo_alterado (conteudo_alterado_em)');

        // ------------------------------------------------------------
        // 2. Tabelas novas
        // ------------------------------------------------------------
        $tabelas = [
            'categorias' => 'CREATE TABLE IF NOT EXISTS categorias (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nome            VARCHAR(120) NOT NULL,           -- = produtos.categoria
                slug            VARCHAR(190) NOT NULL,
                aliases         VARCHAR(500) NULL,               -- nomes antigos, separados por vírgula
                rotulo_seo      VARCHAR(120) NULL,               -- frase usada em title/H1 (gênero correto)
                intro_seo       TEXT NULL,
                seo_title       VARCHAR(70) NULL,
                seo_description VARCHAR(180) NULL,
                seo_indexavel   TINYINT(1) NOT NULL DEFAULT 1,
                ordem           SMALLINT NOT NULL DEFAULT 100,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY ux_categorias_nome (nome),
                UNIQUE KEY ux_categorias_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'seo_facetas' => 'CREATE TABLE IF NOT EXISTS seo_facetas (
                id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                categoria_slug VARCHAR(190) NOT NULL,
                atributo       VARCHAR(60)  NOT NULL,           -- hoje: material
                valor          VARCHAR(190) NOT NULL,           -- valor gravado em produtos.material
                valor_slug     VARCHAR(190) NOT NULL,
                valor_label    VARCHAR(190) NOT NULL,
                intro_seo      TEXT NULL,
                volume_busca   INT NULL,
                seo_indexavel  TINYINT(1) NOT NULL DEFAULT 0,
                updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY ux_faceta (categoria_slug, atributo, valor_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'seo_faq' => "CREATE TABLE IF NOT EXISTS seo_faq (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entidade    ENUM('produto','categoria','faceta','ocasiao','global') NOT NULL,
                entidade_id VARCHAR(190) NULL,                  -- sku_pai | slug da categoria/ocasião
                pergunta    VARCHAR(255) NOT NULL,
                resposta    TEXT NOT NULL,
                ordem       INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY ix_faq_entidade (entidade, entidade_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'seo_ocasioes' => 'CREATE TABLE IF NOT EXISTS seo_ocasioes (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug          VARCHAR(190) NOT NULL,
                titulo        VARCHAR(190) NOT NULL,
                h1            VARCHAR(190) NOT NULL,
                seo_title     VARCHAR(70) NULL,
                seo_description VARCHAR(180) NULL,
                intro         TEXT NOT NULL,
                corpo         MEDIUMTEXT NULL,
                produtos_skus TEXT NULL,                        -- curadoria manual, SKUs separados por vírgula
                categorias    VARCHAR(500) NULL,                -- slugs de categoria relacionados, separados por vírgula
                seo_indexavel TINYINT(1) NOT NULL DEFAULT 0,
                updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY ux_ocasiao_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

            'seo_redirects' => 'CREATE TABLE IF NOT EXISTS seo_redirects (
                id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                origem    VARCHAR(255) NOT NULL,                -- path, ex.: /produto/12345
                destino   VARCHAR(255) NULL,                    -- NULL com status 410 = removido de vez
                status    SMALLINT NOT NULL DEFAULT 301,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY ux_redirect_origem (origem)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
        foreach ($tabelas as $nome => $ddl) {
            if (!self::tabelaExiste($pdo, $nome)) {
                $pdo->exec($ddl);
                $r['tabelas']++;
            }
        }

        // ------------------------------------------------------------
        // 3. Normaliza nomes de categoria (banco tinha nomes antigos:
        //    "Escrita", "Cadernos e Agendas"... que o mapper não gera mais).
        // ------------------------------------------------------------
        $upd = $pdo->prepare('UPDATE produtos SET categoria = :novo WHERE categoria = :antigo');
        foreach (self::CATEGORIAS as $nome => [, $aliases]) {
            foreach ($aliases as $antigo) {
                $upd->execute([':novo' => $nome, ':antigo' => $antigo]);
                $r['categorias_normalizadas'] += $upd->rowCount();
            }
        }

        // ------------------------------------------------------------
        // 4. Semeia categorias (não sobrescreve textos editados no admin)
        // ------------------------------------------------------------
        if (!self::colunaExiste($pdo, 'categorias', 'rotulo_seo')) {
            $pdo->exec('ALTER TABLE categorias ADD COLUMN rotulo_seo VARCHAR(120) NULL AFTER aliases');
            $r['colunas']++;
        }
        $ins = $pdo->prepare(
            'INSERT INTO categorias (nome, slug, aliases, rotulo_seo, ordem) VALUES (:n, :s, :a, :r, :o)
             ON DUPLICATE KEY UPDATE aliases = VALUES(aliases), rotulo_seo = COALESCE(rotulo_seo, VALUES(rotulo_seo))'
        );
        $ordem = 10;
        foreach (self::CATEGORIAS as $nome => [$slug, $aliases, $rotulo]) {
            $ins->execute([':n' => $nome, ':s' => $slug, ':a' => $aliases ? implode(',', $aliases) : null, ':r' => $rotulo, ':o' => $ordem]);
            $ordem += 10;
        }
        // categorias que existam no banco e o mapper desconheça (segurança)
        foreach ($pdo->query('SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria <> ""')->fetchAll(PDO::FETCH_COLUMN) as $cat) {
            $st = $pdo->prepare('SELECT 1 FROM categorias WHERE nome = :n');
            $st->execute([':n' => $cat]);
            if (!$st->fetchColumn()) {
                $ins->execute([':n' => $cat, ':s' => Seo::slugUnico($pdo, Seo::slugify($cat), 'categorias'), ':a' => null, ':r' => $cat . ' Personalizados', ':o' => 900]);
            }
        }

        // ------------------------------------------------------------
        // 5. Backfill de slugs (uma vez; depois o slug é congelado)
        // ------------------------------------------------------------
        // Unicidade resolvida em memória (um SELECT) e gravação em transação:
        // ~3.500 produtos em ~1s, em vez de 2 queries por produto.
        $usados = array_fill_keys(
            $pdo->query("SELECT slug FROM produtos WHERE slug IS NOT NULL AND slug <> ''")->fetchAll(PDO::FETCH_COLUMN),
            true
        );
        $pendentes = $pdo->query("SELECT id, sku_pai, nome FROM produtos WHERE slug IS NULL OR slug = '' ORDER BY id")->fetchAll();
        if ($pendentes) {
            $set = $pdo->prepare('UPDATE produtos SET slug = :s WHERE id = :id');
            $pdo->beginTransaction();
            try {
                foreach ($pendentes as $p) {
                    $base = Seo::slugProduto((string) $p['nome'], (string) $p['sku_pai']);
                    $slug = $base;
                    for ($i = 2; isset($usados[$slug]); $i++) {
                        $slug = substr($base, 0, 170) . '-' . $i;
                    }
                    $usados[$slug] = true;
                    $set->execute([':s' => $slug, ':id' => $p['id']]);
                    $r['slugs']++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // lastmod honesto: sem histórico, o melhor dado é a criação do registro
        $pdo->exec('UPDATE produtos SET conteudo_alterado_em = created_at WHERE conteudo_alterado_em IS NULL');

        Settings::set('seo_schema_versao', self::VERSAO);
        Cache::flush();
        Seo::limparCacheCategorias();

        return $r;
    }

    private static function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $st->execute([':t' => $tabela, ':c' => $coluna]);
        return (bool) $st->fetchColumn();
    }

    private static function tabelaExiste(PDO $pdo, string $tabela): bool
    {
        $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
        $st->execute([':t' => $tabela]);
        return (bool) $st->fetchColumn();
    }

    private static function indice(PDO $pdo, string $tabela, string $nome, string $ddl): void
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i LIMIT 1'
        );
        $st->execute([':t' => $tabela, ':i' => $nome]);
        if (!$st->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$tabela} ADD {$ddl}");
        }
    }
}
