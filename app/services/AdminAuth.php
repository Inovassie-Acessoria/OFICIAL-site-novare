<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Autenticação do painel oculto /settings-admin.
 *
 * - Sessão PHP própria (cookie httponly/SameSite), separada do site.
 * - Senha guardada como hash (password_hash) na tabela admin_users.
 * - Credenciais erradas são bloqueadas; o controller ainda aplica rate-limit.
 * - Token CSRF para proteger os POSTs do painel.
 */
final class AdminAuth
{
    private const EMAIL_PADRAO = 'novarebrindes@gmail.com';
    private const SENHA_PADRAO = '102030';

    /** Cria a tabela e semeia o admin padrão (apenas se não houver nenhum). */
    public static function ensure(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_users (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email      VARCHAR(190)    NOT NULL,
                senha_hash VARCHAR(255)    NOT NULL,
                criado_em  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $existe = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($existe === 0) {
            $stmt = $pdo->prepare('INSERT INTO admin_users (email, senha_hash) VALUES (:e, :h)');
            $stmt->execute([
                ':e' => self::EMAIL_PADRAO,
                ':h' => password_hash(self::SENHA_PADRAO, PASSWORD_DEFAULT),
            ]);
        }
    }

    public static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('novare_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);
        session_start();
    }

    public static function tentarLogin(string $email, string $senha): bool
    {
        self::ensure();
        $stmt = Database::connection()->prepare(
            'SELECT id, email, senha_hash FROM admin_users WHERE email = :e LIMIT 1'
        );
        $stmt->execute([':e' => mb_strtolower(trim($email))]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($senha, (string) $u['senha_hash'])) {
            return false;
        }
        self::iniciarSessao();
        session_regenerate_id(true);
        $_SESSION['admin_id']    = (int) $u['id'];
        $_SESSION['admin_email'] = $u['email'];
        return true;
    }

    public static function logado(): bool
    {
        self::iniciarSessao();
        return !empty($_SESSION['admin_id']);
    }

    public static function logout(): void
    {
        self::iniciarSessao();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? true));
        }
        session_destroy();
    }

    public static function email(): string
    {
        self::iniciarSessao();
        return (string) ($_SESSION['admin_email'] ?? '');
    }

    public static function csrf(): string
    {
        self::iniciarSessao();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfValido(?string $token): bool
    {
        self::iniciarSessao();
        return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
    }
}
