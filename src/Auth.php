<?php
declare(strict_types=1);

namespace MoteCMS;

final class Auth
{
    public function __construct(private Config $config) {}
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name($this->config->getString('session_name'));
        session_set_cookie_params(['httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite' => 'Strict', 'path' => $this->config->getString('base_path') ?: '/']);
        session_start();
    }
    public function check(): bool { return !empty($_SESSION['auth']); }
    public function login(string $password): bool
    {
        $hash = getenv('MOTECMS_ADMIN_PASSWORD_HASH') ?: '';
        if ($hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['auth'] = true;
            return true;
        }
        return false;
    }
    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
}
