# Novare Brindes — Catálogo Inteligente B2B

Catálogo consultivo de brindes corporativos. **Não é e-commerce transacional**: não há
carrinho nem pagamento — toda a jornada termina em um **lead qualificado no WhatsApp**.

- **Stack:** PHP 8.x + MySQL (PDO) · HTML/CSS/JS vanilla · Google Gemini 2.5 Flash (via proxy PHP) · API XBZ (sync semanal via cron)
- **Hospedagem:** Hostinger (plano compartilhado)
- **Idioma:** pt-BR

---

## Estrutura do projeto

```
.
├─ app/
│  ├─ config/        env.php (leitor de .env) · Database.php (PDO)
│  ├─ controllers/   lógica de páginas
│  ├─ services/      XBZService · GeminiService · ProductRepository
│  └─ views/         templates
├─ public/           RAIZ PÚBLICA (index.php, assets, api)
├─ scripts/          schema.sql · migrate.php · seed.php · sync_xbz.php
├─ storage/          cache/ · logs/   (gerados em runtime)
├─ .env.example      modelo (versionado)
├─ .env.local        valores reais de DEV (NUNCA versionar)
├─ start.bat / start.sh   preview local
└─ README.md
```

> **Segurança:** `.env` e `.env.local` estão no `.gitignore`. Chaves de API (Gemini, XBZ)
> e credenciais do banco **nunca** vão para o Git nem para o frontend.

---

## Pré-requisitos

- **PHP 8.x** com a extensão `pdo_mysql` (verifique com `php -m`).
- **MySQL 5.7+/MariaDB** local para desenvolvimento (via XAMPP ou Laragon).

### Não tem PHP/MySQL instalado? (Windows)
Escolha **uma** opção:
- **XAMPP** (recomendado, traz PHP + MySQL) → https://www.apachefriends.org
- **Laragon** → https://laragon.org
- **PHP puro** → https://windows.php.net/download (descompacte em `tools/php/`); use um MySQL separado.

O `start.bat` detecta PHP no PATH, no XAMPP, no Laragon ou em `tools/php/` automaticamente.

---

## Configuração (`.env`)

1. Copie o modelo:
   ```bash
   cp .env.example .env.local
   ```
2. Preencha as variáveis. Para **dev local** com XAMPP/Laragon o padrão já é:
   ```
   DB_HOST=127.0.0.1
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=novare_local
   ```
3. Em **produção** (Hostinger) crie um `.env` na raiz do projeto (fora de `public/`) ou
   use as variáveis de ambiente do hPanel, apontando para o MySQL da Hostinger.

| Variável | Descrição |
|---|---|
| `APP_ENV` | `local` ou `production` |
| `APP_DEBUG` | `true` em dev, `false` em produção |
| `DB_*` | conexão MySQL |
| `GEMINI_API_KEY` / `GEMINI_MODEL` | agente de IA (proxy) |
| `XBZ_API_URL` / `XBZ_CNPJ` / `XBZ_TOKEN` | sincronização do catálogo |
| `WHATSAPP_NUMBER` | comercial (só dígitos: DDI+DDD+número) |
| `SITE_DOMAIN` | domínio final p/ links absolutos |

---

## Banco de dados

```bash
# 1) Cria o banco (em local) e aplica o schema com índices
php scripts/migrate.php

# 2) (opcional) Dados de demonstração para o preview
php scripts/seed.php
```

O schema usa **InnoDB / utf8mb4**, índices em todos os campos de filtro e **FULLTEXT**
para busca textual em escala (~10k+ produtos).

---

## Preview local

```bash
# Windows
start.bat

# Linux / macOS
chmod +x start.sh && ./start.sh

# ou manualmente
php -S localhost:8000 -t public
```

Acesse **http://localhost:8000**. A tela inicial (provisória) verifica PHP, `pdo_mysql`
e a conexão com o banco. Será substituída pela Home no item 6.

---

## CSS (Tailwind estático)

O site **não** usa mais o CDN do Tailwind (compilava ~400 KB de JS no navegador). O CSS é
compilado uma vez e commitado em `public/assets/css/app.css`:

```bash
npm install          # uma vez
npm run css          # recompila public/assets/css/app.css (rode após mexer em classes nas views)
npm run css:watch    # modo watch durante o desenvolvimento
```

Fonte: `src/css/app.css` + `tailwind.config.js` (escaneia `app/views/**` e `public/assets/js`).
**Sempre commite o `app.css` gerado** — a Hostinger não roda build.

---

## SEO / AEO / GEO

Tudo centralizado em [app/services/Seo.php](app/services/Seo.php) (URLs, slugs, title, canonical,
JSON-LD). Views e controllers nunca montam `/brindes/...` na mão.

**Estrutura de URL**

| URL | O quê |
|---|---|
| `/brindes` | hub do catálogo |
| `/brindes/{categoria}` | categoria (tabela `categorias`, slug estável) |
| `/brindes/{categoria}/material/{m}` | faceta promovida (`seo_facetas`, indexável) — as demais são filtro livre `noindex,follow` |
| `/brindes/produto/{slug}` | produto (slug **congelado**: gerado uma vez, nunca regenerado na sync) |
| `/{slug}` | landing de ocasião (`seo_ocasioes`) |
| `/produto/{sku}`, `/catalogo?categoria=` | legado → 301 |

**Peças**

- `SeoMigration` — schema/dados do pacote (aditiva, idempotente). Roda sozinha no 1º request
  após o deploy; também pelo botão *Migração de SEO* do `migrate-web.php`.
- `SeoGate` — portão de qualidade. Só **reporta** até o admin ligar `Portão ATIVO` em
  `/settings-admin/seo`; ligado, produto reprovado vira `noindex,follow` e sai do sitemap.
  Meta robots, sitemap e links internos leem a MESMA decisão (`Seo::produtoIndexavel`).
- `SitemapGenerator` — índice segmentado (`/sitemap.xml` → institucional, categorias, ocasiões,
  produtos-N). `lastmod` = `conteudo_alterado_em` (muda só quando o conteúdo muda). Também gera `/llms.txt`.
- `IndexNow` — chave em `configuracoes`, publicada em `/{chave}.txt`; a sync envia só o que mudou.
- `ImagemLocal` — converte a imagem principal (XBZ) para WebP no nosso domínio, em lotes pelo painel.
- Painel: `/settings-admin/seo` — portão, dados operacionais (prazo/técnicas), entidade (razão social,
  CNPJ, endereço, perfis), conteúdo próprio por produto/categoria, FAQ, landings, facetas, redirects.

**Sem preço no site** (decisão de negócio: catálogo consultivo). O schema `Product` sai sem `offers`.

---

## Sincronização XBZ (cron semanal)

```bash
php scripts/sync_xbz.php
```

Importa o catálogo da XBZ, agrupa `SKU-COR` em produto-pai + variações (upsert; o que
some da XBZ vira inativo, nunca é apagado) e regenera o cache. A lógica vive em
[app/services/CatalogSync.php](app/services/CatalogSync.php), compartilhada entre a CLI e a web.

**Sem acesso a CLI? Use o migrador web** (`public/migrate-web.php`): botão **"2. Importar
Catálogo REAL da XBZ"** roda exatamente a mesma sincronização pelo navegador. Apague o
arquivo do servidor depois de usar.

**Cron na Hostinger (1x por semana, segunda 03:00):**
```
0 3 * * 1 /usr/bin/php /home/USUARIO/domains/novarebrindes.com.br/public_html/scripts/sync_xbz.php >> /home/USUARIO/.../storage/logs/sync.log 2>&1
```
> Ajuste o caminho do PHP e do projeto conforme o painel da Hostinger.

---

## Deploy (GitHub → Hostinger)

- Branches: `main` (produção) · `dev` (desenvolvimento).
- **Deploy automático via GitHub Action** ([.github/workflows/deploy.yml](.github/workflows/deploy.yml)):
  todo push em `main` publica por FTP. Configure os segredos no repositório
  (*Settings → Secrets and variables → Actions*): `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`.
  Ajuste `server-dir` no workflow para a pasta do domínio na Hostinger.
- **Document root → `public/`**: no hPanel da Hostinger, aponte o document root do domínio
  para a subpasta `public/` do projeto. Assim `app/`, `scripts/`, `storage/` e `.env`
  ficam **fora** da web. (Se o plano não permitir mudar o document root, mova o conteúdo de
  `public/` para `public_html/` e o resto para uma pasta irmã privada, ajustando os
  `require` de bootstrap.)
- **`.env` de produção**: crie manualmente no servidor (ou use variáveis do hPanel).
  O workflow nunca envia `.env`, `storage/` nem `tools/`.
- Nunca commitar segredos. Se algum vazar, **revogar/rotacionar imediatamente**.

### Pós-deploy (uma vez)
```bash
php scripts/migrate.php      # cria/atualiza tabelas (banco já criado no hPanel)
php scripts/sync_xbz.php     # primeira carga do catálogo
```
**Sem CLI?** Acesse `https://SEU-DOMINIO/migrate-web.php` e clique em **1. Migração**,
depois **2. Importar Catálogo REAL da XBZ** e **Migração de SEO**. **Apague `public/migrate-web.php` do servidor
em seguida.** Depois agende o cron semanal (seção acima).

---

## Checklist de segurança

- [x] `.env`/`.env.local` fora do público e no `.gitignore`
- [x] PDO com prepared statements reais (`EMULATE_PREPARES=false`)
- [x] Proxy PHP para Gemini e XBZ (chaves nunca no frontend)
- [x] Validação/sanitização de entrada (busca, filtros por whitelist, chat)
- [x] Headers de segurança (CSP, X-Content-Type-Options, X-Frame-Options) no `.htaccess` + PHP
- [x] HTTPS forçado em produção (regra no `.htaccess`)
- [x] Rate limiting no endpoint do agente (20 req/min por IP)
- [x] Mensagens de erro genéricas ao usuário; detalhes só no log
- [x] Bloqueio de acesso a `.env`/arquivos sensíveis via `.htaccess`
- [ ] **Pendente (você):** usuário MySQL com privilégio mínimo no hPanel
- [ ] **Pendente (você):** rotacionar a senha do banco e a chave do Gemini (foram coladas no chat)
```
