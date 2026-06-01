<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/**
 * Proxy para o Google Gemini. A chave fica só no servidor (env).
 *
 * Pede ao modelo uma resposta em JSON puro (responseMimeType=application/json),
 * usada para decidir: perguntar mais OU traduzir a necessidade em filtros.
 * Quem retorna produto real é o banco (ProductRepository), não o modelo —
 * isso evita alucinação de produtos inexistentes.
 */
final class GeminiService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            Env::get('GEMINI_API_KEY', '') ?? '',
            Env::get('GEMINI_MODEL', 'gemini-2.5-flash') ?? 'gemini-2.5-flash',
        );
    }

    public function disponivel(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Envia a conversa e retorna o JSON decodificado do modelo (ou null em falha).
     *
     * @param array<int,array{role?:string,texto?:string}> $historico
     * @param string|null $imagemBase64
     * @param int $timeout
     * @return array<string,mixed>|null
     */
    public function gerarJson(string $instrucaoSistema, array $historico, ?string $imagemBase64 = null, int $timeout = 30): ?array
    {
        if (!$this->disponivel()) {
            return null;
        }

        $contents = [];
        $totalMsgs = count($historico);
        $contador = 0;

        foreach ($historico as $m) {
            $contador++;
            $texto = trim((string) ($m['texto'] ?? ''));
            if ($texto === '' && (!$imagemBase64 || $contador !== $totalMsgs)) {
                continue;
            }
            $role = (($m['role'] ?? 'user') === 'assistant') ? 'model' : 'user';
            
            $parts = [];
            if ($texto !== '') {
                $parts[] = ['text' => mb_substr($texto, 0, 1000)];
            }

            // Injeta o inlineData na última mensagem se houver imagem anexa
            if ($imagemBase64 && $contador === $totalMsgs && $role === 'user') {
                if (preg_match('/^data:(image\/[a-zA-Z+.-]+);base64,(.+)$/', $imagemBase64, $matches)) {
                    $parts[] = [
                        'inlineData' => [
                            'mimeType' => $matches[1],
                            'data'     => $matches[2]
                        ]
                    ];
                } else {
                    $parts[] = [
                        'inlineData' => [
                            'mimeType' => 'image/png', // fallback
                            'data'     => $imagemBase64
                        ]
                    ];
                }
            }

            if ($parts) {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }
        if (!$contents) {
            return null;
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $instrucaoSistema]]],
            'contents'          => $contents,
            'generationConfig'  => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.3,
                'maxOutputTokens'  => 1024,
            ],
        ];

        $base = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($this->model) . ':generateContent';

        // Chave de API do AI Studio (AIza...) usa ?key=; tokens OAuth usam Bearer.
        $headers = ['Content-Type: application/json'];
        if (str_starts_with($this->apiKey, 'AIza')) {
            $url = $base . '?key=' . urlencode($this->apiKey);
        } else {
            $url = $base;
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $httpCode !== 200 || !is_string($body)) {
            error_log("[Gemini] HTTP {$httpCode} errno {$errno}");
            return null;
        }

        $data = json_decode($body, true);
        $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($texto)) {
            return null;
        }

        $json = json_decode($texto, true);
        return is_array($json) ? $json : null;
    }
}
