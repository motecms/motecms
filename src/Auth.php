<?php
declare(strict_types=1);

namespace MoteCMS;

final class Auth
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_ATTEMPT_WINDOW = 3600; // 1 hour in seconds

    public function __construct(private Config $config) {}
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() !== $this->config->getString('session_name')) {
                session_write_close();
                session_name($this->config->getString('session_name'));
                session_start();
            }
            return;
        }
        session_name($this->config->getString('session_name'));
        session_set_cookie_params(['httponly' => true, 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'samesite' => 'Strict', 'path' => $this->config->getString('base_path') ?: '/']);
        session_start();
    }
    public function check(): bool { return !empty($_SESSION['auth']); }
    public function login(string $password): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $now = time();
        
        // Initialize attempts array if not exists
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        // Clean old attempts
        $_SESSION['login_attempts'] = array_filter(
            $_SESSION['login_attempts'],
            fn($t) => $now - $t < self::LOGIN_ATTEMPT_WINDOW
        );
        
        // Check if too many attempts
        if (count($_SESSION['login_attempts']) >= self::MAX_LOGIN_ATTEMPTS) {
            error_log("Too many failed login attempts from IP: $ip");
            return false;
        }
        
        $hash = getenv('MOTECMS_ADMIN_PASSWORD_HASH') ?: '';
        if ($hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['auth'] = true;
            return true;
        }
        
        // Record failed attempt
        $_SESSION['login_attempts'][] = $now;
        return false;
    }
    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
}
