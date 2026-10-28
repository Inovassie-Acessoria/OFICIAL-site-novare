<?php

declare(strict_types=1);

/**
 * /settings-admin/estoque: botão de atualizar estoque, histórico e relatório.
 *
 * Mesma proteção do resto do painel: exige login e todo POST checa CSRF.
 * A lógica fica em EstoqueSync; aqui só recebe o clique e mostra o resultado.
 */
final class AdminEstoqueController
{
    /** GET|POST /settings-admin/estoque */
    public function pagina(): void
    {
        $this->exigirLogin();
        if (!Estoque::pronto()) {
            Estoque::migrar(); // cria colunas e tabelas se ainda não existirem
        }
        $sync = EstoqueSync::create();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!AdminAuth::csrfValido($_POST['csrf'] ?? null)) {
                $this->redirect('/settings-admin/estoque');
            }
            $usuario = AdminAuth::email();
            $id = (int) ($_POST['id'] ?? 0);
            switch ((string) ($_POST['acao'] ?? '')) {
                case 'simular':
                    $r = $sync->simular($usuario);
                    $this->flash($r['mensagem'], $r['ok']);
                    break;
                case 'aplicar':
                    // trava: confirmação só vale com a caixa marcada E a palavra digitada
                    $confirmado = !empty($_POST['confirmar']) && mb_strtoupper(trim((string) ($_POST['palavra'] ?? ''))) === 'CONFIRMO';
                    $r = $sync->aplicar($id, $confirmado, $usuario);
                    $this->flash($r['mensagem'], $r['ok']);
                    if ($r['ok']) {
                        $this->redirect('/settings-admin/estoque?aplicada=' . $r['id']);
                    }
                    break;
                case 'descartar':
                    $this->flash($sync->descartar($id) ? 'Simulação descartada. Nada mudou no site.' : 'Esta simulação já não estava pendente.', true);
                    break;
            }
            $this->redirect('/settings-admin/estoque');
        }

        $pendente = $sync->pendente();
        $this->render('estoque', [
            'flash'      => $this->consumirFlash(),
            'pendente'   => $pendente,
            'previa'     => $pendente ? $sync->mudancas((int) $pendente['id']) : null,
            'aplicada'   => qint('aplicada', 0),
            'historico'  => $sync->historico(30),
            'fora_hoje'  => $sync->totalForaDoAr(),
            'trava'      => ['percentual' => EstoqueSync::TRAVA_PERCENTUAL, 'minimo' => EstoqueSync::TRAVA_MIN_PRODUTOS, 'validade' => EstoqueSync::VALIDADE_MINUTOS],
        ]);
    }

    /**
     * GET /settings-admin/estoque/relatorio?id=N   (o que mudou numa atualização)
     * GET /settings-admin/estoque/relatorio?tipo=fora   (tudo que está fora do site hoje)
     * Página de impressão: o navegador salva em PDF.
     */
    public function relatorio(): void
    {
        $this->exigirLogin();
        if (!Estoque::pronto()) {
            Estoque::migrar();
        }
        $sync = EstoqueSync::create();
        $dados = ['tipo' => 'atualizacao', 'run' => null, 'grupos' => null, 'conferir' => [], 'fora' => []];

        if (q('tipo', '') === 'fora') {
            $dados['tipo'] = 'fora';
            $dados['fora'] = $sync->foraDoArHoje();
        } else {
            $run = $sync->atualizacao(qint('id', 0));
            if (!$run) {
                $this->redirect('/settings-admin/estoque');
            }
            $aplicada = $run['status'] === 'aplicada';
            $todas = $sync->mudancas((int) $run['id']);
            $grupos = [];
            $conferir = [];
            foreach ($todas as $tipo => $linhas) {
                foreach ($linhas as $l) {
                    $vale = $aplicada ? $l['situacao'] === 'aplicado' : $l['situacao'] === 'previsto';
                    if (in_array($tipo, ['fora_api', 'incerto'], true)) {
                        if ($vale) {
                            $conferir[] = $l;
                        }
                    } elseif ($vale) {
                        $grupos[$tipo][] = $l;
                    } elseif ($aplicada && in_array($l['situacao'], ['divergente', 'nao_aplicado'], true) && in_array($tipo, ['saiu', 'cor_saiu'], true)) {
                        $conferir[] = $l;
                    }
                }
            }
            $dados['run'] = $run;
            $dados['grupos'] = $grupos + ['saiu' => [], 'voltou' => [], 'cor_saiu' => [], 'cor_voltou' => []];
            $dados['conferir'] = $conferir;
        }

        header('X-Robots-Tag: noindex, nofollow');
        $this->renderSolto('estoque-relatorio', $dados);
    }

    /** View sem o layout do painel (página de impressão), em escopo limpo. */
    private function renderSolto(string $view, array $dados): void
    {
        extract($dados, EXTR_SKIP);
        require APP_VIEWS . '/admin/' . $view . '.php';
    }

    // ------------------------------------------------------------

    private function exigirLogin(): void
    {
        if (!AdminAuth::logado()) {
            $this->redirect('/settings-admin');
        }
    }

    private function flash(string $msg, bool $ok): void
    {
        AdminAuth::iniciarSessao();
        $_SESSION['flash_estoque'] = ['msg' => $msg, 'ok' => $ok];
    }

    private function consumirFlash(): ?array
    {
        AdminAuth::iniciarSessao();
        $m = $_SESSION['flash_estoque'] ?? null;
        unset($_SESSION['flash_estoque']);
        return is_array($m) ? $m : null;
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

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
