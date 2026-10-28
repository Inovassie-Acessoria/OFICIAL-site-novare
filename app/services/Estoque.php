<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Settings.php';

/**
 * Controle de estoque do catálogo: colunas, tabelas e o filtro que esconde
 * produto e cor esgotados do site.
 *
 * Regras:
 *  - Esgotado = estoque abaixo do pedido mínimo do produto.
 *  - Cor esgotada some do seletor; produto sai do site quando TODAS as cores
 *    lidas estão esgotadas.
 *  - Nada é apagado do banco: só as colunas `esgotado` mudam.
 *  - Quem muda o status é só o botão do painel (EstoqueSync), nunca a sync completa.
 *
 * Segurança de deploy: os filtros só entram nas consultas depois que a
 * migração rodou (pronto()). Se a migração falhar, o site segue exatamente
 * como antes, sem quebrar nenhuma consulta.
 */
final class Estoque
{
    /** Versão do schema de estoque (chave estoque_schema_versao em configuracoes). */
    public const VERSAO = 1;

    private static ?bool $pronto = null;

    /** Colunas e tabelas já existem? Memoizado por requisição. */
    public static function pronto(): bool
    {
        if (self::$pronto === null) {
            try {
                self::$pronto = (int) Settings::get('estoque_schema_versao', 0) >= self::VERSAO;
            } catch (Throwable $e) {
                self::$pronto = false;
            }
        }
        return self::$pronto;
    }

    /** " AND p.esgotado = 0" (ou "" antes da migração). */
    public static function sqlProduto(string $alias = ''): string
    {
        return self::pronto() ? ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'esgotado = 0' : '';
    }

    /** " AND v.esgotado = 0" (ou "" antes da migração). */
    public static function sqlVariacao(string $alias = 'v'): string
    {
        return self::pronto() ? ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'esgotado = 0' : '';
    }

    /**
     * Pedido mínimo usado na regra de esgotado. Mesma fonte da página de
     * produto: coluna quantidade_minima ou a faixa de preço. Sem nenhum dos
     * dois, vale 1 (esgotado = estoque zero).
     */
    public static function minimo(array $produto): int
    {
        $q = (int) ($produto['quantidade_minima'] ?? 0);
        if ($q <= 0 && class_exists('ProductMapper')) {
            $q = (int) (ProductMapper::quantidadeMinima($produto['preco_base'] ?? null) ?? 0);
        }
        return max(1, $q);
    }

    // ============================================================
    // MIGRAÇÃO (aditiva, idempotente, roda sozinha no 1º request)
    // ============================================================

    /** Chamada barata no front controller: só migra se a versão gravada for antiga. */
    public static function garantir(): void
    {
        try {
            if (self::pronto()) {
                return;
            }
            $lock = dirname(__DIR__, 2) . '/storage/cache/.estoque-migracao.lock';
            $fp = @fopen($lock, 'c');
            if ($fp === false) {
                return;
            }
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                fclose($fp);
                return; // outro request já está migrando
            }
            try {
                self::migrar();
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        } catch (Throwable $e) {
            error_log('[Estoque] migração: ' . $e->getMessage());
        }
    }

    /** @return array{colunas:int,tabelas:int} */
    public static function migrar(): array
    {
        $pdo = Database::connection();
        $r = ['colunas' => 0, 'tabelas' => 0];

        $colunas = [
            'produtos' => [
                'esgotado'             => 'TINYINT(1) NOT NULL DEFAULT 0',
                'esgotado_desde'       => 'DATETIME NULL',
                'estoque_total'        => 'INT NULL',
                'estoque_conferido_em' => 'DATETIME NULL',
                'fora_api_desde'       => 'DATETIME NULL',
            ],
            'variacoes' => [
                'esgotado'       => 'TINYINT(1) NOT NULL DEFAULT 0',
                'esgotado_desde' => 'DATETIME NULL',
            ],
        ];
        foreach ($colunas as $tabela => $cols) {
            foreach ($cols as $nome => $def) {
                if (!self::colunaExiste($pdo, $tabela, $nome)) {
                    $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$nome} {$def}");
                    $r['colunas']++;
                }
            }
        }
        self::indice($pdo, 'produtos', 'idx_ativo_esgotado', 'INDEX idx_ativo_esgotado (ativo, esgotado, categoria)');
        self::indice($pdo, 'variacoes', 'idx_var_esgotado', 'INDEX idx_var_esgotado (produto_id, ativo, esgotado)');

        $tabelas = [
            // Uma linha por clique no botão (simulação). Vira "aplicada" quando confirmada.
            'catalogo_atualizacoes' => "CREATE TABLE IF NOT EXISTS catalogo_atualizacoes (
                id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
                status             ENUM('simulada','aplicada','descartada','bloqueada','erro') NOT NULL DEFAULT 'simulada',
                criado_em          DATETIME NOT NULL,
                aplicado_em        DATETIME NULL,
                usuario            VARCHAR(190) NULL,
                itens_api          INT NOT NULL DEFAULT 0,          -- variações devolvidas pela XBZ
                cobertura          DECIMAL(5,2) NULL,              -- % das cores do banco encontradas na API
                produtos_no_ar     INT NOT NULL DEFAULT 0,          -- visíveis antes desta atualização
                saem               INT NOT NULL DEFAULT 0,
                voltam             INT NOT NULL DEFAULT 0,
                cores_saem         INT NOT NULL DEFAULT 0,
                cores_voltam       INT NOT NULL DEFAULT 0,
                fora_api           INT NOT NULL DEFAULT 0,
                incertos           INT NOT NULL DEFAULT 0,
                divergentes        INT NOT NULL DEFAULT 0,
                exige_confirmacao  TINYINT(1) NOT NULL DEFAULT 0,  -- trava: muita coisa saindo de uma vez
                confirmado         TINYINT(1) NOT NULL DEFAULT 0,
                mensagem           VARCHAR(500) NULL,
                leitura            MEDIUMTEXT NULL,                -- JSON da 1a leitura (sku-cor => estoque)
                PRIMARY KEY (id),
                KEY ix_cat_atu_status (status, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // O que mudou (ou mudaria) em cada atualização. Base do relatório do comercial.
            'catalogo_mudancas' => "CREATE TABLE IF NOT EXISTS catalogo_mudancas (
                id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                atualizacao_id    INT UNSIGNED NOT NULL,
                tipo              ENUM('saiu','voltou','cor_saiu','cor_voltou','fora_api','incerto') NOT NULL,
                situacao          ENUM('previsto','aplicado','divergente','nao_aplicado') NOT NULL DEFAULT 'previsto',
                produto_id        BIGINT UNSIGNED NULL,
                sku_pai           VARCHAR(64) NOT NULL,
                nome              VARCHAR(255) NULL,
                categoria         VARCHAR(120) NULL,
                sku_completo      VARCHAR(80) NULL,
                cor               VARCHAR(80) NULL,
                estoque_anterior  INT NULL,
                estoque_atual     INT NULL,
                quantidade_minima INT NULL,
                detalhe           VARCHAR(500) NULL,
                PRIMARY KEY (id),
                KEY ix_cat_mud_atu (atualizacao_id, tipo),
                KEY ix_cat_mud_sku (sku_pai)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($tabelas as $nome => $ddl) {
            if (!self::tabelaExiste($pdo, $nome)) {
                $pdo->exec($ddl);
                $r['tabelas']++;
            }
        }

        Settings::set('estoque_schema_versao', self::VERSAO);
        self::$pronto = true;
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
