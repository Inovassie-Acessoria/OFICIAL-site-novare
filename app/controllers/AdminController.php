<?php

declare(strict_types=1);

/**
 * Painel oculto /settings-admin.
 *
 * Não há NENHUM link para cá no site público; o acesso é só pela URL direta e
 * exige login. Todas as ações (exceto o login) checam a sessão; POSTs checam CSRF.
 */
final class AdminController
{
    private const UPLOAD_DIR  = APP_ROOT . '/public/assets/uploads';
    private const UPLOAD_URL  = '/assets/uploads';
    private const IA_DIR      = APP_ROOT . '/storage/ia';

    /** Tipos de imagem e dimensões mínimas (px) por uso — garante responsividade. */
    private const REGRAS_IMG = [
        'banner'    => ['min_w' => 1280, 'min_h' => 520,  'rotulo' => 'Banner (hero)',     'rec' => '1920 × 1080 px (16:9), paisagem'],
        'categoria' => ['min_w' => 300,  'min_h' => 300,  'rotulo' => 'Imagem de categoria', 'rec' => '600 × 600 px, quadrada'],
        'logo'      => ['min_w' => 120,  'min_h' => 40,   'rotulo' => 'Logotipo',           'rec' => 'PNG transparente, ~600 × 200 px'],
    ];

    private const TOPS = [
        'top_canetas'  => 'Top Canetas',
        'top_cadernos' => 'Top Cadernos, Agendas & Moleskine',
        'top_garrafas' => 'Top Garrafas',
        'top_mochilas' => 'Top Mochilas',
    ];

    /* ============================ ROTAS ============================ */

    /** GET /settings-admin — dashboard (logado) ou tela de login. */
    public function index(): void
    {
        if (!AdminAuth::logado()) {
            $this->render('login', ['erro' => null]);
            return;
        }
        $this->dashboard();
    }

    /** POST /settings-admin/login */
    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/settings-admin');
        }
        // Rate-limit por IP para barrar tentativas em força bruta.
        if (!$this->rateLimitLogin()) {
            $this->render('login', ['erro' => 'Muitas tentativas. Aguarde 1 minuto e tente novamente.']);
            return;
        }
        $email = (string) ($_POST['email'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');
        if (AdminAuth::tentarLogin($email, $senha)) {
            $this->redirect('/settings-admin');
        }
        $this->render('login', ['erro' => 'Credenciais inválidas.']);
    }

    /** GET|POST /settings-admin/logout */
    public function logout(): void
    {
        AdminAuth::logout();
        $this->redirect('/settings-admin');
    }

    /** GET /settings-admin (interno) */
    private function dashboard(): void
    {
        $repo = ProductRepository::create();

        // Resolve os "Top" salvos para exibir nome/imagem dos produtos arrastáveis.
        $tops = [];
        foreach (self::TOPS as $chave => $rotulo) {
            $skus = SiteContent::topSkus($chave);
            $tops[$chave] = [
                'rotulo'   => $rotulo,
                'produtos' => $skus ? $repo->porSkus($skus) : [],
            ];
        }

        // Imagens atuais das categorias (override do admin OU imagem real do banco).
        $cats = [];
        foreach (SiteContent::categoriasPainel() as $c) {
            $cats[$c] = SiteContent::categoriaImagem($c) ?? $this->imagemDeCategoria($c);
        }

        $this->render('dashboard', [
            'flash'      => $this->consumirFlash(),
            'logo'       => SiteContent::logo(),
            'banners'    => SiteContent::banners(),
            'cats'       => $cats,
            'tops'       => $tops,
            'iaPersona'  => SiteContent::iaPersona(),
            'iaArquivos' => SiteContent::iaArquivos(),
            'regras'     => self::REGRAS_IMG,
        ]);
    }

    /** POST /settings-admin/salvar — grava uma seção. */
    public function salvar(): void
    {
        $this->exigirLogin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !AdminAuth::csrfValido($_POST['csrf'] ?? null)) {
            $this->redirect('/settings-admin');
        }
        $secao = (string) ($_POST['secao'] ?? '');

        switch ($secao) {
            case 'logo':
                Settings::set('logo', trim((string) ($_POST['logo'] ?? '')) ?: SiteContent::LOGO_PADRAO);
                $msg = 'Logo atualizado.';
                break;

            case 'banners':
                Settings::set('banners', $this->jsonInput('banners'));
                $msg = 'Banners atualizados.';
                break;

            case 'categorias':
                $map = $this->jsonInput('categorias_imagens');
                Settings::set('categorias_imagens', is_array($map) ? $map : []);
                $msg = 'Imagens das categorias atualizadas.';
                break;

            case 'top_canetas':
            case 'top_cadernos':
            case 'top_garrafas':
            case 'top_mochilas':
                $skus = $this->jsonInput('skus');
                Settings::set($secao, is_array($skus) ? array_values($skus) : []);
                $msg = (self::TOPS[$secao] ?? 'Ranking') . ' atualizado.';
                break;

            case 'ia':
                Settings::set('ia_prompt', trim((string) ($_POST['ia_prompt'] ?? '')));
                $arqs = $this->jsonInput('ia_arquivos');
                Settings::set('ia_arquivos', is_array($arqs) ? $arqs : []);
                $msg = 'Configuração da IA atualizada.';
                break;

            default:
                $msg = 'Nada para salvar.';
        }

        // Limpa o cache de listagens para o site refletir na hora.
        if (class_exists('Cache')) {
            Cache::flush();
        }
        $this->flash($msg);
        $this->redirect('/settings-admin#' . rawurlencode($secao));
    }

    /** POST /settings-admin/upload — upload AJAX (imagem ou arquivo da IA). JSON. */
    public function upload(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!AdminAuth::logado()) {
            $this->jsonOut(['ok' => false, 'erro' => 'Sessão expirada.'], 401);
        }
        if (!AdminAuth::csrfValido($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? null))) {
            $this->jsonOut(['ok' => false, 'erro' => 'Token inválido.'], 403);
        }
        $tipo = (string) ($_POST['tipo'] ?? 'banner');

        if ($tipo === 'ia') {
            $this->jsonOut($this->uploadArquivoIa());
        }
        $this->jsonOut($this->uploadImagem($tipo));
    }

    /** GET /settings-admin/sku?sku=XXX — busca um produto pelo SKU. JSON. */
    public function sku(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!AdminAuth::logado()) {
            $this->jsonOut(['ok' => false, 'erro' => 'Sessão expirada.'], 401);
        }
        $sku = trim((string) q('sku', ''));
        if ($sku === '') {
            $this->jsonOut(['ok' => false, 'erro' => 'Informe um SKU.']);
        }
        $achados = ProductRepository::create()->porSkus([$sku]);
        if (!$achados) {
            $this->jsonOut(['ok' => false, 'erro' => 'SKU não encontrado (ou produto sem imagem).']);
        }
        $p = $achados[0];
        $this->jsonOut(['ok' => true, 'produto' => [
            'sku_pai'   => $p['sku_pai'],
            'nome'      => $p['nome'],
            'categoria' => $p['categoria'] ?? '',
            'imagem'    => $p['imagem_principal'] ?? '',
        ]]);
    }

    /* ========================= UPLOADS ========================= */

    /** @return array<string,mixed> */
    private function uploadImagem(string $tipo): array
    {
        $f = $_FILES['arquivo'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errCode = $f['error'] ?? UPLOAD_ERR_NO_FILE;
            $errosPhp = [
                UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite de tamanho configurado no php.ini do servidor (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite de tamanho especificado no formulário.',
                UPLOAD_ERR_PARTIAL    => 'O upload foi concluído apenas parcialmente.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'A pasta temporária de uploads do PHP no Windows está ausente ou sem permissão.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever o arquivo no disco (permissão de gravação da pasta TEMP do Windows).',
                UPLOAD_ERR_EXTENSION  => 'O upload foi interrompido por uma extensão ativa do PHP.'
            ];
            $msgErro = $errosPhp[$errCode] ?? 'Erro interno de código ' . $errCode;
            return ['ok' => false, 'erro' => 'Falha no envio do arquivo: ' . $msgErro];
        }

        $nomeOrig = (string) ($f['name'] ?? '');
        $ext = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));

        // Detecta mime-type de forma robusta e híbrida
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $f['tmp_name']) ?: '';
            finfo_close($finfo);
        }
        if ($mime === '' && isset($f['type'])) {
            $mime = strtolower((string) $f['type']);
        }

        // É vídeo se tiver extensão de vídeo OU se o mime-type for vídeo
        $isInputVideo = in_array($ext, ['mp4', 'webm', 'ogg'], true) 
            || str_starts_with($mime, 'video/')
            || ($mime === 'application/octet-stream' && in_array($ext, ['mp4', 'webm', 'ogg'], true));

        // Se for banner e detectarmos como vídeo
        if ($tipo === 'banner' && $isInputVideo) {
            if (($f['size'] ?? 0) > 20 * 1024 * 1024) {
                return ['ok' => false, 'erro' => 'Vídeo muito grande. Limite máximo: 20 MB.'];
            }
            
            // Fallback de extensão se vier vazia do SO do usuário
            $extFinal = $ext;
            if (!in_array($extFinal, ['mp4', 'webm', 'ogg'], true)) {
                $extFinal = 'mp4';
            }
            
            if (!is_dir(self::UPLOAD_DIR)) {
                @mkdir(self::UPLOAD_DIR, 0775, true);
            }
            $nome = 'banner_video_' . bin2hex(random_bytes(6)) . '.' . $extFinal;
            if (!@move_uploaded_file($f['tmp_name'], self::UPLOAD_DIR . '/' . $nome)) {
                return ['ok' => false, 'erro' => 'Não foi possível salvar o arquivo de vídeo.'];
            }
            return [
                'ok'      => true,
                'url'     => self::UPLOAD_URL . '/' . $nome,
                'largura' => 1920,
                'altura'  => 1080,
                'video'   => true
            ];
        }

        // Lógica padrão de imagem (Banner ou Categoria ou Logo)
        $regra = self::REGRAS_IMG[$tipo] ?? self::REGRAS_IMG['banner'];
        if (($f['size'] ?? 0) > 6 * 1024 * 1024) {
            return ['ok' => false, 'erro' => 'Imagem acima de 6 MB.'];
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'erro' => 'Arquivo não é uma imagem válida.'];
        }
        [$w, $h] = $info;
        $mime = $info['mime'] ?? '';
        $extPorMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extPorMime[$mime])) {
            return ['ok' => false, 'erro' => 'Use JPG, PNG ou WebP.'];
        }
        if ($w < $regra['min_w'] || $h < $regra['min_h']) {
            return ['ok' => false, 'erro' => sprintf(
                'Imagem muito pequena (%d×%d). Mínimo para %s: %d×%d px. Recomendado: %s.',
                $w, $h, $regra['rotulo'], $regra['min_w'], $regra['min_h'], $regra['rec']
            )];
        }
        if (!is_dir(self::UPLOAD_DIR)) {
            @mkdir(self::UPLOAD_DIR, 0775, true);
        }
        $nome = $tipo . '_' . bin2hex(random_bytes(6)) . '.' . $extPorMime[$mime];
        if (!@move_uploaded_file($f['tmp_name'], self::UPLOAD_DIR . '/' . $nome)) {
            return ['ok' => false, 'erro' => 'Não foi possível salvar a imagem.'];
        }
        return ['ok' => true, 'url' => self::UPLOAD_URL . '/' . $nome, 'largura' => $w, 'altura' => $h];
    }

    /** @return array<string,mixed> */
    private function uploadArquivoIa(): array
    {
        $f = $_FILES['arquivo'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'erro' => 'Falha no envio do arquivo.'];
        }
        if (($f['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['ok' => false, 'erro' => 'Arquivo acima de 2 MB.'];
        }
        $nomeOrig = (string) ($f['name'] ?? 'arquivo');
        $ext = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
        $permitidos = ['txt', 'md', 'csv', 'json'];
        if (!in_array($ext, $permitidos, true)) {
            return ['ok' => false, 'erro' => 'Para a IA, use arquivos de texto: ' . implode(', ', $permitidos) . '.'];
        }
        if (!is_dir(self::IA_DIR)) {
            @mkdir(self::IA_DIR, 0775, true);
        }
        $armazenado = 'ia_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($f['tmp_name'], self::IA_DIR . '/' . $armazenado)) {
            return ['ok' => false, 'erro' => 'Não foi possível salvar o arquivo.'];
        }
        return ['ok' => true, 'arquivo' => $armazenado, 'nome' => $nomeOrig, 'tipo' => $ext, 'tamanho' => (int) $f['size']];
    }

    /* ========================= HELPERS ========================= */

    private function exigirLogin(): void
    {
        if (!AdminAuth::logado()) {
            $this->redirect('/settings-admin');
        }
    }

    /** Decodifica um campo POST que veio como JSON (listas/mapas do front). */
    private function jsonInput(string $campo): mixed
    {
        $raw = $_POST[$campo] ?? '';
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $dec = json_decode($raw, true);
        return $dec === null && json_last_error() !== JSON_ERROR_NONE ? [] : $dec;
    }

    /** Primeira imagem real cadastrada para uma categoria (fallback do site). */
    private function imagemDeCategoria(string $categoria): ?string
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT imagem_principal FROM produtos
                 WHERE categoria = :c AND ativo = 1 AND imagem_principal IS NOT NULL AND imagem_principal <> ''
                 LIMIT 1"
            );
            $stmt->execute([':c' => $categoria]);
            $v = $stmt->fetchColumn();
            return $v !== false ? (string) $v : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function rateLimitLogin(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
        $arq = APP_ROOT . '/storage/cache/admin_rl_' . md5($ip) . '.json';
        $agora = time();
        $hits = is_file($arq) ? (json_decode((string) file_get_contents($arq), true) ?: []) : [];
        $hits = array_values(array_filter($hits, static fn ($t) => $t > $agora - 60));
        if (count($hits) >= 8) {
            return false;
        }
        $hits[] = $agora;
        @file_put_contents($arq, json_encode($hits), LOCK_EX);
        return true;
    }

    private function flash(string $msg): void
    {
        AdminAuth::iniciarSessao();
        $_SESSION['flash'] = $msg;
    }

    private function consumirFlash(): ?string
    {
        AdminAuth::iniciarSessao();
        $m = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_string($m) ? $m : null;
    }

    private function jsonOut(array $dados, int $http = 200): never
    {
        http_response_code($http);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /** Renderiza uma view do admin dentro do layout próprio (sem chrome do site). */
    private function render(string $view, array $dados = []): void
    {
        AdminAuth::iniciarSessao();
        $csrf = AdminAuth::csrf();
        extract($dados, EXTR_SKIP);
        ob_start();
        require APP_VIEWS . '/admin/' . $view . '.php';
        $conteudo = ob_get_clean();
        require APP_VIEWS . '/admin/layout.php';
    }
}
