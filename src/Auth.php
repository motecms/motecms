<?php
declare(strict_types=1);

namespace MoteCMS;

final class Auth
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_ATTEMPT_WINDOW = 3600; // 1 hour in seconds

    public function __construct(
        private Config $config,
        private ?UserRepository $userRepo = null,
        private ?Logger $logger = null
    ) {}
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
    public function check(string $requiredRole = 'editor'): bool
    {
        if (empty($_SESSION['auth'])) {
            return false;
        }
        
        if ($this->userRepo === null) {
            return true; // Legacy mode: single user is admin
        }
        
        $userRole = $_SESSION['role'] ?? 'editor';
        
        if ($userRole === 'admin') {
            return true; // Admin can do everything
        }
        
        return $userRole === $requiredRole;
    }

    public function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    public function getUser(): ?string
    {
        return $_SESSION['user'] ?? null;
    }

    public function getRole(): ?string
    {
        return $_SESSION['role'] ?? null;
    }
    public function login(string $username, string $password): bool
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
            $this->logger?->warning("Too many failed login attempts", ['ip' => $ip]);
            return false;
        }
        
        // Try multi-user mode first
        if ($this->userRepo !== null) {
            $user = $this->userRepo->findByUsername($username);
            if ($user !== null && $this->userRepo->validatePassword($username, $password)) {
                session_regenerate_id(true);
                $_SESSION = [];
                $_SESSION['auth'] = true;
                $_SESSION['user'] = $username;
                $_SESSION['role'] = $user['role'];
                $this->logger?->info('User logged in', ['user' => $username, 'ip' => $ip]);
                return true;
            }
        }
        
        // Fallback to legacy single-user mode
        $hash = getenv('MOTECMS_ADMIN_PASSWORD_HASH') ?: '';
        if ($hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['auth'] = true;
            $_SESSION['user'] = 'admin';
            $_SESSION['role'] = 'admin';
            $this->logger?->info('User logged in (legacy mode)', ['ip' => $ip]);
            return true;
        }
        
        // Record failed attempt
        $_SESSION['login_attempts'][] = $now;
        $this->logger?->warning('Failed login attempt', ['username' => $username, 'ip' => $ip]);
        return false;
    }
    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
}
