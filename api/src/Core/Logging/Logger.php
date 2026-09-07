<?php

declare(strict_types=1);

namespace App\Core\Logging;

final class Logger
{
    private const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];

    public function __construct(
        private readonly string $logPath,
        private readonly string $minLevel = 'debug',
    ) {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
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

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $entry = json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $file = $this->logPath . '/' . date('Y-m-d') . '.log';
        file_put_contents($file, $entry . "\n", FILE_APPEND | LOCK_EX);
    }

    private function shouldLog(string $level): bool
    {
        $minIndex = array_search($this->minLevel, self::LEVELS, true);
        $levelIndex = array_search($level, self::LEVELS, true);

        return $levelIndex >= $minIndex;
    }
}
