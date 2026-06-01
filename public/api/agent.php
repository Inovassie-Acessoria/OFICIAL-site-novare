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

$repo = ProductRepository::create();
$catsValidas  = array_column($repo->categorias(), 'categoria');
$matsValidos  = array_column($repo->materiais(), 'material');
$coresValidas = array_column($repo->cores(), 'cor');

/* ---------- Monta a instrução do sistema ---------- */
$instrucao = <<<'TXT'
Você é o assistente consultivo da Novare Brindes (brindes corporativos no Brasil).
Objetivo: entender a necessidade do cliente e ajudá-lo a encontrar brindes, gerando um lead.
NUNCA invente produtos. Você apenas decide O QUE buscar; o catálogo real é consultado pelo sistema.

Faça no máximo 1-2 perguntas curtas de qualificação (ocasião, público, quantidade, orçamento, estilo)
APENAS se faltar informação essencial. Assim que tiver o suficiente, peça uma busca.

Se o usuário enviar uma imagem ou print de um produto, você deve analisá-la de forma multimodal (cor, material, formato, utilidade do objeto) e traduzir o item visualizado em filtros textuais precisos no JSON de retorno para buscarmos produtos equivalentes em nosso catálogo (por exemplo, preenchendo o termo textual "q" com os termos extraídos do produto visualizado).

Responda SEMPRE e SOMENTE com um JSON puro neste formato:
{
  "acao": "perguntar" | "buscar",
  "mensagem": "texto curto e cordial em pt-BR",
  "filtros": {
    "q": "palavras-chave para busca textual",
    "categoria": "uma das categorias válidas ou string vazia",
    "cor": "uma das cores válidas ou vazia",
    "material": "um dos materiais válidos ou vazio",
    "preco_max": número ou null,
    "sustentavel": 0 ou 1
  }
}
TXT;
// Anexa as listas válidas (fora do nowdoc, para o modelo escolher valores reais).
$instrucao .= "\nCategorias válidas: " . implode(', ', $catsValidas);
$instrucao .= "\nCores válidas: " . implode(', ', $coresValidas);
$instrucao .= "\nMateriais válidos: " . implode(', ', $matsValidos);

/* ---------- Chama o Gemini (com fallback) ---------- */
$gemini = GeminiService::fromEnv();
$decisao = $gemini->gerarJson($instrucao, $mensagens, $imagem);

if (!is_array($decisao)) {
    // Fallback: sem IA disponível, busca direta pelo texto do usuário.
    $decisao = ['acao' => 'buscar', 'mensagem' => 'Veja algumas opções que encontrei:', 'filtros' => ['q' => $ultimaDoUsuario]];
}

$acao     = ($decisao['acao'] ?? 'buscar') === 'perguntar' ? 'perguntar' : 'buscar';
$mensagem = trim((string) ($decisao['mensagem'] ?? ''));
if ($mensagem === '') {
    $mensagem = $acao === 'perguntar' ? 'Pode me contar um pouco mais?' : 'Encontrei estas opções:';
}

if ($acao === 'perguntar') {
    responder(['resposta' => $mensagem, 'produtos' => []]);
}

/* ---------- Sanitiza filtros contra listas válidas ---------- */
$f = is_array($decisao['filtros'] ?? null) ? $decisao['filtros'] : [];
$filtros = [];
if (!empty($f['q']))         $filtros['q'] = mb_substr(trim((string) $f['q']), 0, 120);
if (!empty($f['categoria']) && in_array($f['categoria'], $catsValidas, true)) $filtros['categoria'] = $f['categoria'];
if (!empty($f['cor']) && in_array($f['cor'], $coresValidas, true))           $filtros['cor'] = $f['cor'];
if (!empty($f['material']) && in_array($f['material'], $matsValidos, true))   $filtros['material'] = $f['material'];
if (isset($f['preco_max']) && is_numeric($f['preco_max']) && $f['preco_max'] > 0) $filtros['preco_max'] = (float) $f['preco_max'];
if (!empty($f['sustentavel'])) $filtros['sustentavel'] = 1;

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
        'preco'    => preco($p['preco_base'] ?? 0),
        'imagem'   => $p['imagem_principal'] ?? '',
        'url'      => $urlProd,
        'whatsapp' => whatsappLink(whatsappProduto($p['nome'], '', $p['sku_pai'], $urlProd)),
    ];
}

if (!$produtos) {
    $mensagem = 'Não encontrei itens exatos para isso, mas um consultor especializado de atendimento pode te ajudar de forma personalizada. Quer que eu envie seu contato para o nosso time comercial?';
}

responder(['resposta' => $mensagem, 'produtos' => $produtos]);
