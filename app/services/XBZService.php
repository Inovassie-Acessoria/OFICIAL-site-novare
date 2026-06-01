<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/**
 * Cliente da API XBZ (GetListaDeProdutos).
 *
 * Responsabilidade única: buscar e decodificar a lista de produtos.
 * Não conhece o banco — quem persiste é o sync_xbz.php.
 *
 * A resposta da XBZ é um ARRAY JSON de itens no formato SKU-COR
 * (CodigoComposto), cada um com CodigoAmigavel (raiz/pai).
 */
final class XBZService
{
    public function __construct(
        private readonly string $url,
        private readonly string $cnpj,
        private readonly string $token,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            Env::require('XBZ_API_URL'),
            Env::require('XBZ_CNPJ'),
            Env::require('XBZ_TOKEN'),
        );
    }

    /**
     * Busca a lista completa de produtos da XBZ.
     *
     * @return array<int,array<string,mixed>>
     * @throws RuntimeException em falha de rede, HTTP != 200, vazio ou JSON inválido.
     */
    public function getListaDeProdutos(int $timeoutSegundos = 120): array
    {
        $url = $this->url
            . '?cnpj=' . urlencode($this->cnpj)
            . '&token=' . urlencode($this->token);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeoutSegundos,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'NovareBrindes-Sync/1.0',
            CURLOPT_ENCODING       => '', // aceita gzip (resposta é grande)
        ]);

        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errmsg   = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("Falha de rede ao acessar XBZ: {$errmsg}");
        }
        if ($httpCode !== 200) {
            throw new RuntimeException("XBZ retornou HTTP {$httpCode}.");
        }
        if (!is_string($body) || $body === '') {
            throw new RuntimeException('XBZ retornou resposta vazia.');
        }

        return $this->decode($body);
    }

    /**
     * Decodifica um JSON da XBZ a partir de uma string (também usado em testes
     * offline com um arquivo salvo).
     *
     * @return array<int,array<string,mixed>>
     */
    public function decode(string $json): array
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('XBZ retornou JSON inválido: ' . json_last_error_msg());
        }
        // Algumas APIs envelopam a lista; normaliza para o array de itens.
        if (isset($data['Produtos']) && is_array($data['Produtos'])) {
            $data = $data['Produtos'];
        }

        return $data;
    }

    /**
     * Carrega de um arquivo local (desenvolvimento/teste, sem rede).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fromFile(string $caminho): array
    {
        if (!is_file($caminho)) {
            throw new RuntimeException("Arquivo XBZ não encontrado: {$caminho}");
        }
        return $this->decode((string) file_get_contents($caminho));
    }
}
