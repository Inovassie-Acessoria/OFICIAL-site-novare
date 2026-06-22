<?php

declare(strict_types=1);

/**
 * Proxy do assistente de IA.
 *   POST /api/agent.php  { "mensagens": [ {role, texto}, ... ] }
 *   -> { "resposta": "...", "produtos": [ {nome, url, imagem, preco}, ... ] }
 *
 * Segurança:
 *  - Chave do Gemini só no servidor.
 *  - Rate limiting por IP.
 *  - Filtros do modelo são sanitizados contra listas válidas antes da query.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function responder(array $dados, int $http = 200): never
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Detecta cumprimentos/conversa básica para não responder com produtos no fallback. */
function ehConversaSimples(string $txt): bool
{
    $t = mb_strtolower(trim($txt), 'UTF-8');
    $t = trim((string) preg_replace('/[!?.,;:]+/u', '', $t));
    $saudacoes = ['oi', 'ola', 'olá', 'oie', 'eai', 'e ai', 'e aí', 'opa', 'hey', 'hello',
        'bom dia', 'boa tarde', 'boa noite', 'tudo bem', 'tudo bom', 'td bem', 'blz', 'beleza',
        'obrigado', 'obrigada', 'valeu', 'vlw', 'tchau', 'ok', 'okay', 'como vai', 'tudo certo'];
    if (in_array($t, $saudacoes, true)) {
        return true;
    }
    foreach (['tudo bem', 'tudo bom', 'bom dia', 'boa tarde', 'boa noite'] as $frag) {
        if (str_contains($t, $frag) && mb_strlen($t) <= 25) {
            return true;
        }
    }
    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responder(['erro' => 'Método não permitido'], 405);
}

/* ---------- Rate limiting simples por IP ---------- */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$rlFile = APP_ROOT . '/storage/cache/rl_' . md5($ip) . '.json';
$agora = time();
$janela = 60;       // segundos
$limite = 20;       // requisições por janela
$hits = [];
if (is_file($rlFile)) {
    $hits = json_decode((string) file_get_contents($rlFile), true) ?: [];
    $hits = array_values(array_filter($hits, static fn ($t) => $t > $agora - $janela));
}
if (count($hits) >= $limite) {
    responder(['resposta' => 'Você enviou muitas mensagens em pouco tempo. Aguarde alguns segundos. 🙂', 'produtos' => []], 429);
}
$hits[] = $agora;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

/* ---------- Lê e valida a entrada ---------- */
$input = json_decode((string) file_get_contents('php://input'), true);
$mensagens = is_array($input['mensagens'] ?? null) ? $input['mensagens'] : [];
$mensagens = array_slice($mensagens, -12); // limita histórico
$imagem = is_string($input['imagem'] ?? null) ? $input['imagem'] : null;

$ultimaDoUsuario = '';
foreach (array_reverse($mensagens) as $m) {
    if (($m['role'] ?? '') !== 'assistant') {
        $ultimaDoUsuario = trim((string) ($m['texto'] ?? ''));
        break;
    }
}
if ($ultimaDoUsuario === '') {
    responder(['resposta' => 'Conte um pouco da sua necessidade para eu sugerir brindes. 😊', 'produtos' => []]);
}

// Guarda de nível superior: qualquer falha (banco, Gemini, etc.) vira uma
// resposta JSON amigável — nunca um 500 sem corpo, que no front aparecia como
// "Tivemos um problema de conexão".
try {

$repo = ProductRepository::create();
$catsValidas  = array_column($repo->categorias(), 'categoria');
$matsValidos  = array_column($repo->materiais(), 'material');
$coresValidas = array_column($repo->cores(), 'cor');

/* ---------- Monta a instrução do sistema ---------- */
// Persona/comportamento EDITÁVEL pelo painel admin (com padrão embutido).
$instrucao = SiteContent::iaPersona();

// Injeção de regras críticas de negócio inquebráveis (proibição de preço e briefing consultivo aprofundado)
$instrucao .= "\n\n# REGRAS CRÍTICAS DE NEGÓCIO (OBRIGATÓRIO SEGUIR À RISCA):";
$instrucao .= "\n- PROIBIDO MENCIONAR PREÇOS OU CUSTOS: Você de forma alguma deve mencionar preços de produtos, valores em reais ou estimativas de custos no texto de resposta. O catálogo é consultivo B2B e todos os preços são sob consulta comercial.";
$instrucao .= "\n- BRIEFING CONSULTIVO E DEEP DISCOVERY: Nunca sugira produtos de forma direta ou apressada no início da conversa. Primeiro, atue como consultora investigativa e faça perguntas pontuais e amigáveis (uma ou duas por vez) para entender profundamente a necessidade real do cliente antes de recomendar brindes (ex: pergunte sobre o tipo/objetivo do evento corporativo, perfil de quem vai receber os brindes, quantidade pretendida e prazo de entrega).";

// Conhecimento extra: arquivos de texto anexados pelo admin (complementam a IA).
$blocosConhecimento = [];
foreach (SiteContent::iaArquivos() as $arq) {
    $nomeArq = basename((string) ($arq['arquivo'] ?? ''));
    if ($nomeArq === '') {
        continue;
    }
    $caminho = APP_ROOT . '/storage/ia/' . $nomeArq;
    if (is_file($caminho)) {
        $conteudo = (string) file_get_contents($caminho, false, null, 0, 8000);
        if (trim($conteudo) !== '') {
            $blocosConhecimento[] = '### ' . ($arq['nome'] ?? $nomeArq) . "\n" . $conteudo;
        }
    }
}
if ($blocosConhecimento) {
    $instrucao .= "\n\nBASE DE CONHECIMENTO (use como referência ao recomendar):\n"
        . mb_substr(implode("\n\n", $blocosConhecimento), 0, 12000);
}

// Formato de resposta — SEMPRE fixado pelo sistema (o admin não pode quebrá-lo).
$instrucao .= <<<'TXT'


Responda SEMPRE e SOMENTE com um JSON puro neste formato:
{
  "resposta": "texto que o cliente vê — SEMPRE preenchido, tom humano e caloroso",
  "acao": "conversar" | "buscar",
  "q": "termo central do produto — só quando acao = buscar; senão vazio",
  "filtros": {
    "cor": "uma das cores válidas ou vazia",
    "material": "um dos materiais válidos ou vazio",
    "categoria": "uma das categorias válidas ou vazia",
    "referencia": "referência de produto ou vazia"
  }
}
TXT;
// Anexa as listas válidas (para o modelo escolher valores reais).
$instrucao .= "\nCategorias válidas: " . implode(', ', $catsValidas);
$instrucao .= "\nCores válidas: " . implode(', ', $coresValidas);
$instrucao .= "\nMateriais válidos: " . implode(', ', $matsValidos);

/* ---------- Chama o Gemini (com fallback) ---------- */
$gemini = GeminiService::fromEnv();
$decisao = $gemini->gerarJson($instrucao, $mensagens, $imagem);

if (!is_array($decisao)) {
    // Sem IA disponível (ex.: 503 transitório do Gemini). Para NÃO responder um
    // simples cumprimento com produtos aleatórios, trata conversa básica aqui;
    // só então busca pelo texto como melhor esforço.
    if (ehConversaSimples($ultimaDoUsuario)) {
        responder([
            'resposta' => 'Oi! Tudo bem? 😊 Sou a Sophia, consultora de brindes da Novare. Me conta o que você procura (caneta, garrafa, mochila, kit de onboarding...) que eu já te mostro boas opções!',
            'produtos' => [],
        ]);
    }
    $decisao = [
        'acao'     => 'buscar',
        'resposta' => 'Veja algumas opções que encontrei:',
        'q'        => $ultimaDoUsuario,
        'filtros'  => [],
    ];
}

// Aceita tanto a nova ação 'conversar' quanto a antiga 'perguntar'
$acaoDecisao = $decisao['acao'] ?? 'buscar';
$acao = ($acaoDecisao === 'conversar' || $acaoDecisao === 'perguntar') ? 'conversar' : 'buscar';

// Aceita tanto a nova chave 'resposta' quanto a antiga 'mensagem'
$mensagem = trim((string) ($decisao['resposta'] ?? $decisao['mensagem'] ?? ''));
if ($mensagem === '') {
    $mensagem = $acao === 'conversar' ? 'Pode me contar um pouco mais?' : 'Encontrei estas opções:';
}

if ($acao === 'conversar') {
    responder(['resposta' => $mensagem, 'produtos' => []]);
}

/* ---------- Sanitiza filtros contra listas válidas ---------- */
$qDecisao = $decisao['q'] ?? $decisao['filtros']['q'] ?? '';
$f = is_array($decisao['filtros'] ?? null) ? $decisao['filtros'] : [];

$filtros = [];
if ($qDecisao !== '') {
    $filtros['q'] = mb_substr(trim((string) $qDecisao), 0, 120);
}

// Se o modelo enviou uma referência (ex.: SKU ou termo), junta ao termo de busca 'q' para precisão
if (!empty($f['referencia'])) {
    $ref = mb_substr(trim((string) $f['referencia']), 0, 80);
    $filtros['q'] = empty($filtros['q']) ? $ref : $filtros['q'] . ' ' . $ref;
}

if (!empty($f['categoria']) && in_array($f['categoria'], $catsValidas, true)) {
    $filtros['categoria'] = $f['categoria'];
}
if (!empty($f['cor']) && in_array($f['cor'], $coresValidas, true)) {
    $filtros['cor'] = $f['cor'];
}
if (!empty($f['material']) && in_array($f['material'], $matsValidos, true)) {
    $filtros['material'] = $f['material'];
}
// Retrocompatibilidade opcional com filtros antigos (se passados pela IA)
if (isset($f['preco_max']) && is_numeric($f['preco_max']) && $f['preco_max'] > 0) {
    $filtros['preco_max'] = (float) $f['preco_max'];
}
if (!empty($f['sustentavel'])) {
    $filtros['sustentavel'] = 1;
}

if (!$filtros) {
    $filtros['q'] = mb_substr($ultimaDoUsuario, 0, 120);
}

/* ---------- Consulta o banco (broadening se vier pouco) ---------- */
$res = $repo->listar($filtros, 1, 6);
if (count($res['itens']) < 3 && count($filtros) > 1) {
    $broad = array_intersect_key($filtros, ['q' => 1, 'categoria' => 1]);
    if ($broad) {
        $res = $repo->listar($broad, 1, 6);
    }
}

$produtos = [];
foreach ($res['itens'] as $p) {
    $urlProd = urlAbsoluta('/produto/' . rawurlencode($p['sku_pai']));
    $produtos[] = [
        'nome'     => $p['nome'],
        'preco'    => '',
        'imagem'   => $p['imagem_principal'] ?? '',
        'url'      => $urlProd,
        'whatsapp' => whatsappLink(whatsappProduto($p['nome'], $p['sku_pai'])),
    ];
}

if (!$produtos) {
    $mensagem = 'Não encontrei itens exatos para isso, mas um consultor especializado de atendimento pode te ajudar de forma personalizada. Quer que eu envie seu contato para o nosso time comercial?';
}

responder(['resposta' => $mensagem, 'produtos' => $produtos]);

} catch (Throwable $e) {
    error_log('[agent] ' . $e->getMessage());
    responder([
        'resposta' => 'Tive uma instabilidade momentânea por aqui. 😅 Tente novamente em instantes ou fale agora com um consultor pelo WhatsApp que te ajudamos na hora.',
        'produtos' => [],
    ]);
}
