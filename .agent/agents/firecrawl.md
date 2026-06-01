# 🔥 Firecrawl MCP Server

> Servidor MCP oficial do Firecrawl — Adiciona web scraping, busca e interação com páginas web ao seu assistente de IA.

**Repositório:** [firecrawl/firecrawl-mcp-server](https://github.com/firecrawl/firecrawl-mcp-server)  
**Licença:** MIT  
**Pacote:** `firecrawl-mcp`  
**Versão Atual:** v3.2.1

---

## Índice

- [Visão Geral](#visão-geral)
- [Instalação e Configuração](#instalação-e-configuração)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Ferramentas Disponíveis](#ferramentas-disponíveis)
- [Guia de Seleção de Ferramenta](#guia-de-seleção-de-ferramenta)
- [Exemplos de Uso](#exemplos-de-uso)
- [Configuração Avançada](#configuração-avançada)
- [Tratamento de Erros](#tratamento-de-erros)
- [Sistema de Logs](#sistema-de-logs)

---

## Visão Geral

O Firecrawl MCP Server é uma implementação do Model Context Protocol (MCP) que integra o [Firecrawl](https://firecrawl.dev) para buscar, fazer scraping e interagir com a web. Ele transforma websites em dados prontos para LLM (markdown, JSON, etc.), lidando automaticamente com:

- **Renderização JavaScript** — Páginas dinâmicas são processadas corretamente
- **Proteções anti-bot** — Bypass automático de CAPTCHAs e bloqueios
- **Paginação** — Navegação automática por múltiplas páginas
- **Rate limiting** — Controle inteligente de requisições
- **Retries automáticos** — Backoff exponencial para erros transitórios

### Capacidades Principais

| Capacidade | Descrição |
|---|---|
| **Scrape** | Extrair conteúdo limpo de URLs individuais |
| **Batch Scrape** | Processar múltiplas URLs em paralelo |
| **Crawl** | Rastrear um site inteiro com controle de profundidade |
| **Map** | Descobrir todas as URLs de um site |
| **Search** | Buscar na web e obter conteúdo completo |
| **Extract** | Extração estruturada com LLM |
| **Interact** | Clicar, navegar e preencher formulários |
| **Agent** | Pesquisa autônoma multi-fonte |

---

## Instalação e Configuração

### Pré-requisitos

- **Node.js** instalado (v18+)
- **API Key** do Firecrawl — obtenha em [firecrawl.dev/app/api-keys](https://www.firecrawl.dev/app/api-keys)

### Via npx (Recomendado)

```bash
env FIRECRAWL_API_KEY=fc-1a7d1c09a04b4e78a26a224e9d16feeb npx -y firecrawl-mcp
```

> **Windows:** Se tiver problemas, use:
> ```bash
> cmd /c "set FIRECRAWL_API_KEY=fc-1a7d1c09a04b4e78a26a224e9d16feeb && npx -y firecrawl-mcp"
> ```

### Instalação Global

```bash
npm install -g firecrawl-mcp
```

---

### Configuração por Cliente

#### Cursor (v0.48.6+)

1. Abra **Settings** → **Features** → **MCP Servers**
2. Clique em **"+ Add new global MCP server"**
3. Cole:

```json
{
  "mcpServers": {
    "firecrawl-mcp": {
      "command": "npx",
      "args": ["-y", "firecrawl-mcp"],
      "env": {
        "FIRECRAWL_API_KEY": "fc-1a7d1c09a04b4e78a26a224e9d16feeb"
      }
    }
  }
}
```

#### Claude Desktop

Adicione ao `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "mcp-server-firecrawl": {
      "command": "npx",
      "args": ["-y", "firecrawl-mcp"],
      "env": {
        "FIRECRAWL_API_KEY": "fc-1a7d1c09a04b4e78a26a224e9d16feeb"
      }
    }
  }
}
```

#### Claude Code (CLI)

```bash
claude mcp add firecrawl -e FIRECRAWL_API_KEY=fc-1a7d1c09a04b4e78a26a224e9d16feeb -- npx -y firecrawl-mcp
```

Ou via URL remota:

```bash
claude mcp add firecrawl --url https://mcp.firecrawl.dev/fc-1a7d1c09a04b4e78a26a224e9d16feeb/v2/mcp
```

#### VS Code

Adicione ao **User Settings (JSON)** (`Ctrl+Shift+P` → `Preferences: Open User Settings (JSON)`):

```json
{
  "mcp": {
    "inputs": [
      {
        "type": "promptString",
        "id": "apiKey",
        "description": "Firecrawl API Key",
        "password": true
      }
    ],
    "servers": {
      "firecrawl": {
        "command": "npx",
        "args": ["-y", "firecrawl-mcp"],
        "env": {
          "FIRECRAWL_API_KEY": "${input:apiKey}"
        }
      }
    }
  }
}
```

Ou crie `.vscode/mcp.json` no workspace para compartilhar a configuração:

```json
{
  "inputs": [
    {
      "type": "promptString",
      "id": "apiKey",
      "description": "Firecrawl API Key",
      "password": true
    }
  ],
  "servers": {
    "firecrawl": {
      "command": "npx",
      "args": ["-y", "firecrawl-mcp"],
      "env": {
        "FIRECRAWL_API_KEY": "${input:apiKey}"
      }
    }
  }
}
```

#### Windsurf

Adicione ao `./codeium/windsurf/model_config.json`:

```json
{
  "mcpServers": {
    "mcp-server-firecrawl": {
      "command": "npx",
      "args": ["-y", "firecrawl-mcp"],
      "env": {
        "FIRECRAWL_API_KEY": "fc-1a7d1c09a04b4e78a26a224e9d16feeb"
      }
    }
  }
}
```

#### Streamable HTTP (Modo Local)

```bash
env HTTP_STREAMABLE_SERVER=true FIRECRAWL_API_KEY=fc-1a7d1c09a04b4e78a26a224e9d16feeb npx -y firecrawl-mcp
```

URL: `http://localhost:3000/mcp`

---

## Variáveis de Ambiente

### Obrigatórias

| Variável | Descrição |
|---|---|
| `FIRECRAWL_API_KEY` | Sua chave de API do Firecrawl. Obrigatória para API cloud. Opcional para instâncias self-hosted. |

### Opcionais

| Variável | Descrição | Padrão |
|---|---|---|
| `FIRECRAWL_API_URL` | Endpoint customizado para instâncias self-hosted. Ex: `https://firecrawl.seu-dominio.com` | API cloud |
| `FIRECRAWL_RETRY_MAX_ATTEMPTS` | Número máximo de tentativas de retry | `3` |
| `FIRECRAWL_RETRY_INITIAL_DELAY` | Delay inicial em ms antes do primeiro retry | `1000` |
| `FIRECRAWL_RETRY_MAX_DELAY` | Delay máximo em ms entre retries | `10000` |
| `FIRECRAWL_RETRY_BACKOFF_FACTOR` | Multiplicador de backoff exponencial | `2` |
| `FIRECRAWL_CREDIT_WARNING_THRESHOLD` | Limiar de aviso de créditos consumidos | `1000` |
| `FIRECRAWL_CREDIT_CRITICAL_THRESHOLD` | Limiar crítico de créditos consumidos | `100` |

---

## Ferramentas Disponíveis

### 1. `firecrawl_scrape` — Scrape de URL Única

Faz scraping de conteúdo de uma URL individual com opções avançadas.

**Melhor para:** Extração de conteúdo de página única quando você sabe exatamente qual página contém a informação.

**Formatos disponíveis:**
- `json` — **Recomendado.** Extrai apenas dados específicos via schema. Econômico em tokens.
- `markdown` — Conteúdo completo da página. Use somente quando necessário (ex: resumo de artigo).
- `branding` — Extrai identidade visual (cores, fontes, tipografia, espaçamento, logo, componentes UI).

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `url` | `string` | URL para fazer scraping (obrigatório) |
| `formats` | `array` | Formatos de saída: `"markdown"`, `"branding"`, ou objeto `{type: "json", prompt, schema}` |
| `onlyMainContent` | `boolean` | Extrair apenas conteúdo principal (sem nav, footer, ads) |

**Exemplo — JSON (preferido):**

```json
{
  "name": "firecrawl_scrape",
  "arguments": {
    "url": "https://example.com/product",
    "formats": [{
      "type": "json",
      "prompt": "Extract the product information",
      "schema": {
        "type": "object",
        "properties": {
          "name": { "type": "string" },
          "price": { "type": "number" },
          "description": { "type": "string" }
        },
        "required": ["name", "price"]
      }
    }]
  }
}
```

**Exemplo — Markdown:**

```json
{
  "name": "firecrawl_scrape",
  "arguments": {
    "url": "https://example.com/article",
    "formats": ["markdown"],
    "onlyMainContent": true
  }
}
```

**Exemplo — Branding:**

```json
{
  "name": "firecrawl_scrape",
  "arguments": {
    "url": "https://example.com",
    "formats": ["branding"]
  }
}
```

---

### 2. `firecrawl_batch_scrape` — Scrape em Lote

Processa múltiplas URLs em paralelo com rate limiting embutido.

**Melhor para:** Quando você sabe exatamente quais páginas acessar e são múltiplas.

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `urls` | `string[]` | Array de URLs (obrigatório) |
| `options.formats` | `array` | Formatos de saída |
| `options.onlyMainContent` | `boolean` | Apenas conteúdo principal |

**Exemplo:**

```json
{
  "name": "firecrawl_batch_scrape",
  "arguments": {
    "urls": ["https://example1.com", "https://example2.com"],
    "options": {
      "formats": ["markdown"],
      "onlyMainContent": true
    }
  }
}
```

**Retorno:** ID da operação para verificar progresso com `firecrawl_check_batch_status`.

---

### 3. `firecrawl_check_batch_status` — Status do Lote

Verifica o progresso de uma operação batch.

```json
{
  "name": "firecrawl_check_batch_status",
  "arguments": {
    "id": "batch_1"
  }
}
```

---

### 4. `firecrawl_map` — Mapeamento de URLs

Descobre todas as URLs indexadas de um site sem extrair conteúdo.

**Melhor para:** Descobrir URLs antes de decidir o que fazer scraping.

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `url` | `string` | URL base do site (obrigatório) |

**Exemplo:**

```json
{
  "name": "firecrawl_map",
  "arguments": {
    "url": "https://example.com"
  }
}
```

**Retorno:** Array de URLs encontradas no site.

---

### 5. `firecrawl_search` — Busca na Web

Busca na web e opcionalmente extrai conteúdo dos resultados.

**Melhor para:** Encontrar informação específica quando você não sabe qual site a contém.

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `query` | `string` | Termo de busca (obrigatório) |
| `limit` | `number` | Número máximo de resultados |
| `lang` | `string` | Idioma (ex: `"pt"`, `"en"`) |
| `country` | `string` | País (ex: `"br"`, `"us"`) |
| `scrapeOptions` | `object` | Opções de scraping para os resultados |

**Exemplo:**

```json
{
  "name": "firecrawl_search",
  "arguments": {
    "query": "últimas pesquisas sobre IA 2025",
    "limit": 5,
    "lang": "pt",
    "country": "br",
    "scrapeOptions": {
      "formats": ["markdown"],
      "onlyMainContent": true
    }
  }
}
```

---

### 6. `firecrawl_crawl` — Rastreamento de Site

Inicia um job assíncrono de crawl para extrair conteúdo de múltiplas páginas relacionadas.

> ⚠️ **Atenção:** Respostas de crawl podem ser muito grandes e exceder limites de tokens. Limite a profundidade e número de páginas.

**Melhor para:** Extração abrangente de múltiplas páginas relacionadas.

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `url` | `string` | URL base para crawl (obrigatório) |
| `maxDepth` | `number` | Profundidade máxima de navegação |
| `limit` | `number` | Número máximo de páginas |
| `allowExternalLinks` | `boolean` | Permitir links externos |
| `deduplicateSimilarURLs` | `boolean` | Remover URLs duplicadas similares |

**Exemplo:**

```json
{
  "name": "firecrawl_crawl",
  "arguments": {
    "url": "https://example.com/blog/*",
    "maxDepth": 2,
    "limit": 100,
    "allowExternalLinks": false,
    "deduplicateSimilarURLs": true
  }
}
```

**Retorno:** Job ID para verificar progresso com `firecrawl_check_crawl_status`.

---

### 7. `firecrawl_check_crawl_status` — Status do Crawl

Verifica o progresso de um job de crawl.

```json
{
  "name": "firecrawl_check_crawl_status",
  "arguments": {
    "id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

---

### 8. `firecrawl_extract` — Extração Estruturada com LLM

Extrai informações estruturadas de páginas web usando capacidades de LLM.

**Melhor para:** Extrair dados estruturados específicos como preços, nomes, detalhes de produto.

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `urls` | `string[]` | Array de URLs (obrigatório) |
| `prompt` | `string` | Prompt customizado para extração |
| `systemPrompt` | `string` | System prompt para guiar o LLM |
| `schema` | `object` | JSON Schema para dados estruturados |
| `allowExternalLinks` | `boolean` | Permitir links externos |
| `enableWebSearch` | `boolean` | Habilitar busca web para contexto adicional |
| `includeSubdomains` | `boolean` | Incluir subdomínios |

**Exemplo:**

```json
{
  "name": "firecrawl_extract",
  "arguments": {
    "urls": ["https://example.com/page1", "https://example.com/page2"],
    "prompt": "Extract product information including name, price, and description",
    "systemPrompt": "You are a helpful assistant that extracts product information",
    "schema": {
      "type": "object",
      "properties": {
        "name": { "type": "string" },
        "price": { "type": "number" },
        "description": { "type": "string" }
      },
      "required": ["name", "price"]
    },
    "allowExternalLinks": false,
    "enableWebSearch": false,
    "includeSubdomains": false
  }
}
```

---

### 9. `firecrawl_agent` — Agente Autônomo de Pesquisa

Agente de IA autônomo que navega na internet, busca informações e extrai dados estruturados. Opera de forma **assíncrona** — retorna um Job ID imediatamente.

**Melhor para:** Pesquisas complexas em múltiplas fontes desconhecidas.

**Fluxo assíncrono:**
1. Chame `firecrawl_agent` com prompt/schema → recebe Job ID
2. Faça outras tarefas enquanto o agente pesquisa (pode levar minutos)
3. Faça polling com `firecrawl_agent_status` para verificar progresso
4. Quando o status for `"completed"`, a resposta inclui os dados

**Parâmetros:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `prompt` | `string` | Descrição em linguagem natural do que buscar (obrigatório, máx 10.000 caracteres) |
| `urls` | `string[]` | URLs opcionais para focar o agente |
| `schema` | `object` | JSON Schema para saída estruturada |

**Exemplo:**

```json
{
  "name": "firecrawl_agent",
  "arguments": {
    "prompt": "Find the top 5 AI startups founded in 2024 and their funding amounts",
    "schema": {
      "type": "object",
      "properties": {
        "startups": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "name": { "type": "string" },
              "funding": { "type": "string" },
              "founded": { "type": "string" }
            }
          }
        }
      }
    }
  }
}
```

---

### 10. `firecrawl_agent_status` — Status do Agente

Verifica o status de um job do agente e recupera resultados.

**Intervalo de polling recomendado:** 10-30 segundos.

**Status possíveis:**
- `processing` — Agente ainda pesquisando
- `completed` — Pesquisa finalizada, dados incluídos na resposta
- `failed` — Erro ocorreu

```json
{
  "name": "firecrawl_agent_status",
  "arguments": {
    "id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

---

### 11-14. Browser Tools (Deprecated ⚠️)

> **Depreciados.** Prefira `firecrawl_scrape` + `firecrawl_interact`.

| Ferramenta | Descrição |
|---|---|
| `firecrawl_browser_create` | Cria sessão de browser cloud |
| `firecrawl_browser_execute` | Executa código na sessão (bash/Python/JS) |
| `firecrawl_browser_list` | Lista sessões ativas |
| `firecrawl_browser_delete` | Encerra uma sessão |

---

## Guia de Seleção de Ferramenta

Use este fluxo para escolher a ferramenta correta:

```
Você sabe a(s) URL(s)?
├── SIM, apenas 1 URL → firecrawl_scrape (JSON para dados específicos)
├── SIM, múltiplas URLs → firecrawl_batch_scrape
└── NÃO
    ├── Preciso descobrir URLs de um site → firecrawl_map
    ├── Preciso buscar info na web → firecrawl_search
    ├── Pesquisa complexa multi-fonte → firecrawl_agent
    └── Preciso raspar um site inteiro → firecrawl_crawl (com limites!)

Preciso interagir com a página (clicar, preencher)?
└── SIM → firecrawl_scrape + firecrawl_interact
```

### Escolhendo o Formato Correto

| Formato | Quando Usar |
|---|---|
| **JSON** (recomendado) | Na maioria dos casos. Defina um schema para extrair apenas o necessário. Economiza tokens. |
| **Markdown** | Somente quando precisa do conteúdo completo (ex: ler artigo inteiro, analisar estrutura). |
| **Branding** | Quando precisa da identidade visual (cores, fontes, logo, componentes UI). |

---

## Configuração Avançada

### Cloud API com Retry Customizado

```bash
# Obrigatório
export FIRECRAWL_API_KEY=fc-1a7d1c09a04b4e78a26a224e9d16feeb

# Retry customizado
export FIRECRAWL_RETRY_MAX_ATTEMPTS=5
export FIRECRAWL_RETRY_INITIAL_DELAY=2000
export FIRECRAWL_RETRY_MAX_DELAY=30000
export FIRECRAWL_RETRY_BACKOFF_FACTOR=3

# Monitoramento de créditos
export FIRECRAWL_CREDIT_WARNING_THRESHOLD=2000
export FIRECRAWL_CREDIT_CRITICAL_THRESHOLD=500
```

### Instância Self-Hosted

```bash
# Obrigatório para self-hosted
export FIRECRAWL_API_URL=https://firecrawl.seu-dominio.com

# Opcional (se a instância requer autenticação)
export FIRECRAWL_API_KEY=fc-1a7d1c09a04b4e78a26a224e9d16feeb

# Retry customizado
export FIRECRAWL_RETRY_MAX_ATTEMPTS=10
export FIRECRAWL_RETRY_INITIAL_DELAY=500
```

### Configuração Completa no Claude Desktop

```json
{
  "mcpServers": {
    "mcp-server-firecrawl": {
      "command": "npx",
      "args": ["-y", "firecrawl-mcp"],
      "env": {
        "FIRECRAWL_API_KEY": "fc-1a7d1c09a04b4e78a26a224e9d16feeb",
        "FIRECRAWL_RETRY_MAX_ATTEMPTS": "5",
        "FIRECRAWL_RETRY_INITIAL_DELAY": "2000",
        "FIRECRAWL_RETRY_MAX_DELAY": "30000",
        "FIRECRAWL_RETRY_BACKOFF_FACTOR": "3",
        "FIRECRAWL_CREDIT_WARNING_THRESHOLD": "2000",
        "FIRECRAWL_CREDIT_CRITICAL_THRESHOLD": "500"
      }
    }
  }
}
```

### Comportamento de Retry

Os retries seguem backoff exponencial:

| Tentativa | Delay (config padrão) |
|---|---|
| 1ª retry | 1 segundo |
| 2ª retry | 2 segundos |
| 3ª retry | 4 segundos (limitado pelo `maxDelay`) |

### Monitoramento de Créditos

- **Warning** em 1000 créditos restantes (padrão)
- **Critical** em 100 créditos restantes (padrão)

---

## Tratamento de Erros

O servidor oferece tratamento robusto de erros:

- ✅ Retries automáticos para erros transitórios
- ✅ Rate limit handling com backoff
- ✅ Mensagens de erro detalhadas
- ✅ Avisos de uso de créditos
- ✅ Resiliência de rede

**Exemplo de resposta de erro:**

```json
{
  "content": [
    {
      "type": "text",
      "text": "Error: Rate limit exceeded. Retrying in 2 seconds..."
    }
  ],
  "isError": true
}
```

---

## Sistema de Logs

O servidor inclui logging abrangente:

```
[INFO]    Firecrawl MCP Server initialized successfully
[INFO]    Starting scrape for URL: https://example.com
[INFO]    Batch operation queued with ID: batch_1
[WARNING] Credit usage has reached warning threshold
[ERROR]   Rate limit exceeded, retrying in 2s...
```

**Categorias de log:**

| Nível | Descrição |
|---|---|
| `INFO` | Status de operações e progresso |
| `WARNING` | Limiares de crédito atingidos |
| `ERROR` | Rate limits, falhas de conexão |
| `METRICS` | Performance e métricas de uso |

---

## Referência Rápida

| Preciso... | Ferramenta | Formato |
|---|---|---|
| Dados específicos de 1 página | `firecrawl_scrape` | JSON + schema |
| Ler conteúdo completo de 1 página | `firecrawl_scrape` | markdown |
| Identidade visual de um site | `firecrawl_scrape` | branding |
| Dados de várias páginas (URLs conhecidas) | `firecrawl_batch_scrape` | JSON/markdown |
| Descobrir URLs de um site | `firecrawl_map` | — |
| Buscar na web | `firecrawl_search` | markdown |
| Rastrear site completo | `firecrawl_crawl` | markdown |
| Extrair dados estruturados com LLM | `firecrawl_extract` | JSON schema |
| Pesquisa complexa multi-fonte | `firecrawl_agent` | JSON schema |
| Interagir com página (clicar, digitar) | `firecrawl_scrape` + interact | — |

---

> **Fonte:** [github.com/firecrawl/firecrawl-mcp-server](https://github.com/firecrawl/firecrawl-mcp-server) | [firecrawl.dev](https://firecrawl.dev) | [Playground MCP](https://mcp.so/playground?server=firecrawl-mcp-server)
