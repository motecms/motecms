<?php
declare(strict_types=1);

namespace MoteCMS;

final class Logger
{
    private string $logDir;
    private string $logFile;
    private bool $enabled;
    private bool $logToFile;

    public function __construct(bool $enabled = true, string $logDir = null, bool $logToFile = true)
    {
        $this->enabled = $enabled;
        $this->logToFile = $logToFile;
        $this->logDir = $logDir ?: sys_get_temp_dir() . '/motecms-logs';
        $this->logFile = $this->logDir . '/motecms-' . date('Ym') . '.log';

        if ($this->logToFile && !is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $user = $_SESSION['user'] ?? ($_SESSION['auth'] ? 'admin' : 'anonymous');
        $role = $_SESSION['role'] ?? '';

        $logEntry = sprintf(
            "[%s] %s: %s (user=%s, role=%s, ip=%s)\n",
            $timestamp,
            strtoupper($level),
            $message,
            $user,
            $role,
            $ip
        );

        if (!empty($context)) {
            $logEntry .= "  Context: " . json_encode($context) . "\n";
        }

        if ($this->logToFile) {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }

        // Also log to PHP error_log for critical errors
        if ($level === 'error') {
            error_log($logEntry);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @return array<int, string> */
    public function getLogs(int $limit = 100): array
    {
        if (!$this->enabled || !$this->logToFile) {
            return [];
        }

        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        return array_slice(array_reverse($lines), 0, $limit);
    }

    public function clearLogs(): void
    {
        if ($this->logToFile && file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
