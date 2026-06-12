<?php

declare(strict_types=1);

/**
 * Helpers globais de view (escape, URLs, preço, WhatsApp, render).
 */

if (!function_exists('e')) {
    /** Escapa para HTML (proteção XSS). */
    function e(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** URL raiz-relativa do site. */
function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

/** Caminho de asset estático. */
function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

/** URL absoluta (para links de compartilhamento / WhatsApp). */
function urlAbsoluta(string $path = ''): string
{
    $base = rtrim(Env::get('APP_URL', '') ?: ('https://' . Env::get('SITE_DOMAIN', 'localhost')), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Formata preço em BRL ou "Sob consulta". */
function preco(mixed $valor): string
{
    $v = (float) $valor;
    if ($v <= 0) {
        return 'Sob consulta';
    }
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/** Só os dígitos do número de WhatsApp. */
function whatsappNumero(): string
{
    return preg_replace('/\D+/', '', Env::get('WHATSAPP_NUMBER', '') ?? '') ?? '';
}

/** Gera o deep link do WhatsApp com mensagem pré-preenchida. */
function whatsappLink(string $mensagem): string
{
    return 'https://wa.me/' . whatsappNumero() . '?text=' . rawurlencode($mensagem);
}

/** Mensagem padrão de orçamento de um produto (nome + SKU). */
function whatsappProduto(string $nome, string $sku): string
{
    return "Olá! Eu vim através do site e me interessei no produto {$nome} (SKU: {$sku}) e gostaria de um orçamento";
}

/** Renderiza uma view dentro do layout padrão. */
function view(string $nome, array $dados = [], string $titulo = 'Novare Brindes', array $meta = []): void
{
    extract($dados, EXTR_SKIP);
    $arquivo = APP_VIEWS . '/' . $nome . '.php';
    if (!is_file($arquivo)) {
        http_response_code(500);
        echo 'View não encontrada: ' . e($nome);
        return;
    }
    ob_start();
    require $arquivo;
    $conteudo = ob_get_clean();
    require APP_VIEWS . '/partials/layout.php';
}

/** Renderiza um partial (sem layout). */
function partial(string $nome, array $dados = []): void
{
    extract($dados, EXTR_SKIP);
    $arquivo = APP_VIEWS . '/partials/' . $nome . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
}

/** Lê parâmetro de query string como string limpa. */
function q(string $chave, ?string $default = null): ?string
{
    $v = $_GET[$chave] ?? null;
    if ($v === null) {
        return $default;
    }
    return is_string($v) ? trim($v) : $default;
}

/** Lê parâmetro de query string como inteiro. */
function qint(string $chave, int $default = 0): int
{
    return isset($_GET[$chave]) && is_numeric($_GET[$chave]) ? (int) $_GET[$chave] : $default;
}
