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

    /* ============================ SEO ============================ */

    /**
     * GET|POST /settings-admin/seo — painel de SEO: portão de qualidade, dados
     * operacionais, entidade, conteúdo próprio por produto/categoria, FAQ,
     * landings de ocasião, facetas promovidas, redirects, IndexNow e imagens locais.
     */
    public function seo(): void
    {
        $this->exigirLogin();
        $pdo = Database::connection();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!AdminAuth::csrfValido($_POST['csrf'] ?? null)) {
                $this->redirect('/settings-admin/seo');
            }
            $msg = $this->seoAcao($pdo, (string) ($_POST['acao'] ?? ''));
            Cache::flush();
            Seo::limparCacheCategorias();
            $this->flash($msg);
            $volta = (string) ($_POST['volta'] ?? '');
            $this->redirect('/settings-admin/seo' . ($volta !== '' ? '?' . ltrim($volta, '?') : '') . '#' . rawurlencode((string) ($_POST['ancora'] ?? '')));
        }

        // ---- produto em edição (busca por SKU)
        $produto = null;
        $skuBusca = trim((string) q('sku', ''));
        if ($skuBusca !== '') {
            $st = $pdo->prepare('SELECT * FROM produtos WHERE sku_pai = :s1 OR slug = :s2 LIMIT 1');
            $st->execute([':s1' => $skuBusca, ':s2' => $skuBusca]);
            $produto = $st->fetch() ?: null;
            if ($produto) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM imagens i JOIN variacoes v ON v.id = i.variacao_id WHERE v.produto_id = :id AND v.ativo = 1");
                $st->execute([':id' => $produto['id']]);
                $produto['imagens'] = (int) $st->fetchColumn();
                $produto['avaliacao'] = SeoGate::avaliarProduto($produto);
            }
        }

        $stats = Settings::get('seo_portao_stats', null);
        $this->render('seo', [
            'flash'        => $this->consumirFlash(),
            'portao_ativo' => Seo::portaoAtivo(),
            'stats'        => is_array($stats) ? $stats : null,
            'operacional'  => (array) (Settings::get('seo_operacional', []) ?: []),
            'entidade'     => (array) (Settings::get('seo_entidade', []) ?: []),
            'produto'      => $produto,
            'sku_busca'    => $skuBusca,
            'categorias'   => $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM produtos p WHERE p.categoria = c.nome AND p.ativo = 1) AS total FROM categorias c ORDER BY c.ordem, c.nome')->fetchAll(),
            'faqs'         => $pdo->query('SELECT * FROM seo_faq ORDER BY entidade, entidade_id, ordem, id')->fetchAll(),
            'ocasioes'     => $pdo->query('SELECT * FROM seo_ocasioes ORDER BY id')->fetchAll(),
            'facetas'      => $pdo->query('SELECT * FROM seo_facetas ORDER BY categoria_slug, valor_label')->fetchAll(),
            'redirects'    => $pdo->query('SELECT * FROM seo_redirects ORDER BY id DESC LIMIT 200')->fetchAll(),
            'materiais'    => $pdo->query("SELECT material, COUNT(*) n FROM produtos WHERE ativo = 1 AND material IS NOT NULL AND material <> '' GROUP BY material ORDER BY n DESC")->fetchAll(),
            'indexnow_key' => IndexNow::chave(),
            'indexnow_ultimo' => Settings::get('indexnow_ultimo', null),
            'imagens'      => ImagemLocal::status($pdo) + ['gd' => ImagemLocal::disponivel()],
        ]);
    }

    private function seoAcao(PDO $pdo, string $acao): string
    {
        $t = static fn (string $k, int $max = 65535): ?string => (($v = trim((string) ($_POST[$k] ?? ''))) === '') ? null : mb_substr($v, 0, $max);

        switch ($acao) {
            case 'portao':
                Settings::set('seo_portao_ativo', !empty($_POST['ativo']));
                return 'Portão de qualidade ' . (!empty($_POST['ativo']) ? 'ATIVADO: só produtos aprovados ficam indexáveis.' : 'desativado: todos os produtos com imagem seguem indexáveis (modo relatório).');

            case 'reavaliar':
                $s = SeoGate::reavaliarCatalogo($pdo);
                return sprintf('Portão reavaliado: %d de %d produtos aprovados.', $s['indexaveis'], $s['avaliados']);

            case 'operacional':
                Settings::set('seo_operacional', ['prazo' => $t('prazo', 40), 'tecnicas' => $t('tecnicas', 255), 'area' => $t('area', 120)]);
                return 'Dados operacionais globais salvos.';

            case 'entidade':
                Settings::set('seo_entidade', [
                    'razao_social' => $t('razao_social', 190), 'cnpj' => $t('cnpj', 30), 'endereco' => $t('endereco', 190),
                    'cidade' => $t('cidade', 90), 'uf' => $t('uf', 2), 'cep' => $t('cep', 12), 'perfis' => $t('perfis', 2000),
                ]);
                return 'Dados da empresa salvos.';

            case 'produto':
                $id = (int) ($_POST['id'] ?? 0);
                $st = $pdo->prepare(
                    'UPDATE produtos SET descricao_propria = :dp, seo_title = :st, seo_description = :sd,
                        prazo_producao = :pr, tecnicas_personalizacao = :tc, area_impressao = :ar,
                        conteudo_alterado_em = NOW()
                     WHERE id = :id'
                );
                $st->execute([
                    ':dp' => $t('descricao_propria'), ':st' => $t('seo_title', 70), ':sd' => $t('seo_description', 180),
                    ':pr' => $t('prazo_producao', 40), ':tc' => $t('tecnicas_personalizacao', 255), ':ar' => $t('area_impressao', 120), ':id' => $id,
                ]);
                // reavalia só este produto
                $p = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM imagens i JOIN variacoes v ON v.id = i.variacao_id WHERE v.produto_id = p.id AND v.ativo = 1) AS imagens FROM produtos p WHERE p.id = {$id}")->fetch();
                if ($p) {
                    $a = SeoGate::avaliarProduto($p);
                    $pdo->prepare('UPDATE produtos SET seo_indexavel = :i, seo_motivo = :m, seo_avaliado_em = NOW() WHERE id = :id')
                        ->execute([':i' => (int) $a['indexavel'], ':m' => $a['motivo'] ?: null, ':id' => $id]);
                    $this->indexNowUrl(Seo::canonical(Seo::urlProduto($p)));
                    return 'Produto salvo. Portão: ' . ($a['indexavel'] ? 'APROVADO' : 'reprovado (' . $a['motivo'] . ')');
                }
                return 'Produto salvo.';

            case 'categoria':
                $st = $pdo->prepare('UPDATE categorias SET intro_seo = :i, seo_title = :t, seo_description = :d, rotulo_seo = :r, seo_indexavel = :x WHERE id = :id');
                $st->execute([':i' => $t('intro_seo'), ':t' => $t('seo_title', 70), ':d' => $t('seo_description', 180), ':r' => $t('rotulo_seo', 120), ':x' => !empty($_POST['seo_indexavel']) ? 1 : 0, ':id' => (int) ($_POST['id'] ?? 0)]);
                return 'Categoria salva.';

            case 'faq_add':
                $ent = (string) ($_POST['entidade'] ?? 'global');
                if (!in_array($ent, ['produto', 'categoria', 'faceta', 'ocasiao', 'global'], true)) {
                    return 'Entidade inválida.';
                }
                $pergunta = $t('pergunta', 255);
                $resposta = $t('resposta');
                if (!$pergunta || !$resposta) {
                    return 'Pergunta e resposta são obrigatórias.';
                }
                $pdo->prepare('INSERT INTO seo_faq (entidade, entidade_id, pergunta, resposta, ordem) VALUES (:e, :i, :p, :r, :o)')
                    ->execute([':e' => $ent, ':i' => $ent === 'global' ? null : $t('entidade_id', 190), ':p' => $pergunta, ':r' => $resposta, ':o' => (int) ($_POST['ordem'] ?? 0)]);
                return 'Pergunta adicionada.';

            case 'faq_del':
                $pdo->prepare('DELETE FROM seo_faq WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
                return 'Pergunta removida.';

            case 'ocasiao_salvar':
                $slug = Seo::slugify((string) ($_POST['slug'] ?? $_POST['titulo'] ?? ''));
                if ($slug === '' || $slug === 'item' || in_array($slug, ['brindes', 'busca', 'sobre', 'atendimento', 'fidelidade', 'status', 'catalogo', 'produto'], true)) {
                    return 'Slug inválido ou reservado.';
                }
                $id = (int) ($_POST['id'] ?? 0);
                $dados = [
                    ':slug' => $slug, ':titulo' => $t('titulo', 190) ?? $slug, ':h1' => $t('h1', 190) ?? ($t('titulo', 190) ?? $slug),
                    ':st' => $t('seo_title', 70), ':sd' => $t('seo_description', 180), ':intro' => $t('intro') ?? '', ':corpo' => $t('corpo'),
                    ':skus' => $t('produtos_skus', 5000), ':cats' => $t('categorias', 500), ':idx' => !empty($_POST['seo_indexavel']) ? 1 : 0,
                ];
                if ($id > 0) {
                    $dados[':id'] = $id;
                    $pdo->prepare('UPDATE seo_ocasioes SET slug=:slug, titulo=:titulo, h1=:h1, seo_title=:st, seo_description=:sd, intro=:intro, corpo=:corpo, produtos_skus=:skus, categorias=:cats, seo_indexavel=:idx WHERE id=:id')->execute($dados);
                } else {
                    $pdo->prepare('INSERT INTO seo_ocasioes (slug, titulo, h1, seo_title, seo_description, intro, corpo, produtos_skus, categorias, seo_indexavel) VALUES (:slug,:titulo,:h1,:st,:sd,:intro,:corpo,:skus,:cats,:idx)')->execute($dados);
                }
                $this->indexNowUrl(Seo::canonical(Seo::urlOcasiao($slug)));
                return 'Landing "' . $slug . '" salva.';

            case 'ocasiao_del':
                $pdo->prepare('DELETE FROM seo_ocasioes WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
                return 'Landing removida.';

            case 'faceta_salvar':
                $catSlug = Seo::slugify((string) ($_POST['categoria_slug'] ?? ''));
                $valor   = $t('valor', 190);
                if ($catSlug === '' || !$valor || !Seo::categoriaPorSlug($catSlug)) {
                    return 'Categoria ou material inválido.';
                }
                $pdo->prepare(
                    'INSERT INTO seo_facetas (categoria_slug, atributo, valor, valor_slug, valor_label, intro_seo, volume_busca, seo_indexavel)
                     VALUES (:c, "material", :v, :vs, :vl, :i, :vb, :x)
                     ON DUPLICATE KEY UPDATE valor = VALUES(valor), valor_label = VALUES(valor_label), intro_seo = VALUES(intro_seo), volume_busca = VALUES(volume_busca), seo_indexavel = VALUES(seo_indexavel)'
                )->execute([':c' => $catSlug, ':v' => $valor, ':vs' => Seo::slugify($valor), ':vl' => $t('valor_label', 190) ?? $valor, ':i' => $t('intro_seo'), ':vb' => (int) ($_POST['volume_busca'] ?? 0) ?: null, ':x' => !empty($_POST['seo_indexavel']) ? 1 : 0]);
                return 'Faceta salva.';

            case 'faceta_del':
                $pdo->prepare('DELETE FROM seo_facetas WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
                return 'Faceta removida.';

            case 'redirect_add':
                $origem = '/' . ltrim((string) parse_url(trim((string) ($_POST['origem'] ?? '')), PHP_URL_PATH), '/');
                $destino = $t('destino', 255);
                $status  = (int) ($_POST['status'] ?? 301);
                if ($origem === '/' || (!$destino && $status !== 410)) {
                    return 'Informe a origem e o destino (ou marque 410).';
                }
                $pdo->prepare('INSERT INTO seo_redirects (origem, destino, status) VALUES (:o, :d, :s) ON DUPLICATE KEY UPDATE destino = VALUES(destino), status = VALUES(status)')
                    ->execute([':o' => rtrim($origem, '/') ?: '/', ':d' => $status === 410 ? null : $destino, ':s' => in_array($status, [301, 302, 410], true) ? $status : 301]);
                return 'Redirect salvo.';

            case 'redirect_del':
                $pdo->prepare('DELETE FROM seo_redirects WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
                return 'Redirect removido.';

            case 'indexnow':
                $r = IndexNow::enviarAlteracoes($pdo, date('Y-m-d H:i:s', strtotime('-7 days')));
                return 'IndexNow: ' . json_encode($r, JSON_UNESCAPED_UNICODE);

            case 'imagens':
                @set_time_limit(120);
                $r = ImagemLocal::processar($pdo, 40);
                return sprintf('Imagens: %d convertidas, %d com erro, %d restantes. %s', $r['processadas'], $r['erros'], $r['restantes'], implode(' ', $r['mensagens']));
        }
        return 'Nada para salvar.';
    }

    private function indexNowUrl(string $url): void
    {
        try {
            IndexNow::enviar([$url]);
        } catch (Throwable $e) {
        }
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
        // LOGO: grava como data URI base64 (vai para o banco em Settings), em vez de
        // um arquivo solto. A pasta public/assets/uploads NÃO é versionada no Git nem
        // enviada pelo FTP-Deploy, então um arquivo ali desaparece a cada limpeza/reset
        // do servidor e o logo caía no genérico. No banco, sobrevive a qualquer deploy.
        if ($tipo === 'logo') {
            if (($f['size'] ?? 0) > 1024 * 1024) {
                return ['ok' => false, 'erro' => 'Logo acima de 1 MB. Use um PNG leve (~600×200 px).'];
            }
            $bin = @file_get_contents($f['tmp_name']);
            if ($bin === false) {
                return ['ok' => false, 'erro' => 'Não foi possível ler a imagem do logo.'];
            }
            return [
                'ok'      => true,
                'url'     => 'data:' . $mime . ';base64,' . base64_encode($bin),
                'largura' => $w,
                'altura'  => $h,
            ];
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
