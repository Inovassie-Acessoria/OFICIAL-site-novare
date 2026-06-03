-- ============================================================
--  Novare Brindes — Schema MySQL (InnoDB / utf8mb4)
--  Modelo produto-pai (produtos) + variações de cor (variacoes)
--  Projetado para ~10.000+ produtos: índices em todos os campos
--  de filtro/ordenação + FULLTEXT para busca textual.
-- ============================================================

-- ------------------------------------------------------------
--  produtos  (PRODUTO-PAI — uma página por sku_pai)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produtos (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku_pai           VARCHAR(64)     NOT NULL,                 -- raiz antes do hífen (ex: 15589)
    nome              VARCHAR(255)    NOT NULL,
    descricao         TEXT            NULL,
    categoria         VARCHAR(120)    NULL,
    material          VARCHAR(120)    NULL,
    preco_base        DECIMAL(10,2)   NULL,                     -- preço de referência (B2B é consultivo)
    quantidade_minima INT UNSIGNED    NULL,                     -- filtro "quantidade mínima"
    sustentavel       TINYINT(1)      NOT NULL DEFAULT 0,
    imagem_principal  VARCHAR(512)    NULL,                     -- thumb denormalizado p/ listagem rápida
    ativo             TINYINT(1)      NOT NULL DEFAULT 1,       -- soft-delete (sync nunca apaga)
    tags              TEXT            NULL,                     -- tags inteligentes p/ pesquisa
    synced_at         DATETIME        NULL,                     -- marcador da última sync (p/ inativar ausentes)
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sku_pai (sku_pai),
    KEY idx_categoria (categoria),
    KEY idx_material (material),
    KEY idx_sustentavel (sustentavel),
    KEY idx_preco (preco_base),
    KEY idx_ativo (ativo),
    KEY idx_ativo_categoria (ativo, categoria),               -- consulta comum: ativos por categoria
    KEY idx_synced (synced_at),
    FULLTEXT KEY ft_busca (nome, descricao, tags)                   -- busca textual em escala
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  variacoes  (FILHOS — uma por cor, ex: 15589-PRE)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS variacoes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id    BIGINT UNSIGNED NOT NULL,
    sku_completo  VARCHAR(80)     NOT NULL,                     -- ex: 15589-PRE
    cor_sufixo    VARCHAR(20)     NULL,                         -- ex: PRE
    cor           VARCHAR(80)     NULL,                         -- ex: Preto
    cor_codigo    VARCHAR(7)      NULL,                         -- hex p/ swatch, ex: #1A1C1E
    estoque       INT             NOT NULL DEFAULT 0,
    ativo         TINYINT(1)      NOT NULL DEFAULT 1,
    synced_at     DATETIME        NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sku_completo (sku_completo),
    KEY idx_produto (produto_id),
    KEY idx_cor (cor),
    KEY idx_cor_sufixo (cor_sufixo),
    KEY idx_produto_ativo (produto_id, ativo),
    KEY idx_synced (synced_at),
    CONSTRAINT fk_variacao_produto FOREIGN KEY (produto_id)
        REFERENCES produtos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  imagens  (galeria por variação — swatch troca as fotos)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS imagens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variacao_id BIGINT UNSIGNED NOT NULL,
    url         VARCHAR(512)    NOT NULL,
    ordem       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    principal   TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_variacao (variacao_id),
    KEY idx_variacao_ordem (variacao_id, ordem),
    CONSTRAINT fk_imagem_variacao FOREIGN KEY (variacao_id)
        REFERENCES variacoes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  cores  (dicionário sufixo -> nome + hex para swatches)
--  Sufixo desconhecido na sync entra aqui com revisar = 1.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cores (
    sufixo      VARCHAR(20)  NOT NULL,
    nome        VARCHAR(80)  NOT NULL,
    hex         VARCHAR(7)   NOT NULL DEFAULT '#9AA5AD',
    revisar     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sufixo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  sync_runs  (auditoria das execuções do cron XBZ)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_runs (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    iniciado_em           DATETIME     NOT NULL,
    finalizado_em         DATETIME     NULL,
    status                VARCHAR(20)  NOT NULL DEFAULT 'em_andamento', -- sucesso|erro|parcial|em_andamento
    pais_inseridos        INT UNSIGNED NOT NULL DEFAULT 0,
    pais_atualizados      INT UNSIGNED NOT NULL DEFAULT 0,
    variacoes_inseridas   INT UNSIGNED NOT NULL DEFAULT 0,
    variacoes_atualizadas INT UNSIGNED NOT NULL DEFAULT 0,
    sufixos_desconhecidos INT UNSIGNED NOT NULL DEFAULT 0,
    mensagem              TEXT         NULL,
    PRIMARY KEY (id),
    KEY idx_iniciado (iniciado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  admin_users  (acesso ao painel oculto /settings-admin)
--  Senha guardada como hash (password_hash). O admin padrão é semeado
--  pela aplicação (AdminAuth) no primeiro acesso, caso a tabela esteja vazia.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(190)    NOT NULL,
    senha_hash VARCHAR(255)    NOT NULL,
    criado_em  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  configuracoes  (chave-valor JSON editável pelo painel admin)
--  Banners, imagens de categorias, rankings "Top", logo e prompt da IA.
--  É a fonte única lida pelo site (sincronia admin <-> site).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes (
    chave         VARCHAR(64)  NOT NULL,
    valor         LONGTEXT     NULL,
    atualizado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Dicionário de cores inicial (mapa determinístico de sufixos)
--  Sufixos comuns de brindes corporativos no padrão XBZ.
-- ------------------------------------------------------------
INSERT INTO cores (sufixo, nome, hex, revisar) VALUES
    ('PRE',   'Preto',          '#1A1C1E', 0),
    ('BRC',   'Branco',         '#FFFFFF', 0),
    ('BCO',   'Branco',         '#FFFFFF', 0),
    ('AZ',    'Azul',           '#24A1E0', 0),
    ('AZC',   'Azul Claro',     '#7FC8EC', 0),
    ('AZR',   'Azul Royal',     '#1E40AF', 0),
    ('AZM',   'Azul Marinho',   '#1E293B', 0),
    ('VM',    'Vermelho',       '#D64545', 0),
    ('VD',    'Verde',          '#2E9E5B', 0),
    ('VDC',   'Verde Claro',    '#7BCB97', 0),
    ('AM',    'Amarelo',        '#F2C037', 0),
    ('LJ',    'Laranja',        '#E8731A', 0),
    ('RS',    'Rosa',           '#E85D9E', 0),
    ('RX',    'Roxo',           '#7C3AED', 0),
    ('CZ',    'Cinza',          '#9AA5AD', 0),
    ('PRT',   'Prata',          '#C0C7CD', 0),
    ('DOU',   'Dourado',        '#D1880C', 0),
    ('MRR',   'Marrom',         '#6B4A2B', 0),
    ('BG',    'Bege',           '#D8C7A8', 0),
    ('NAT',   'Natural',        '#C9B79C', 0),
    ('VNH',   'Vinho',          '#7B1E3B', 0),
    ('TRA',   'Transparente',   '#E8EEF2', 0),
    ('TRANS', 'Transparente',   '#E8EEF2', 0)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), hex = VALUES(hex);
