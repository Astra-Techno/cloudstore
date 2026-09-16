<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Catches unhandled exceptions, logs them for monitoring, and returns
 * a clean JSON error response. Also logs slow requests (> threshold).
 */
final class ErrorMonitoringMiddleware
{
    private const SLOW_REQUEST_MS = 3000;

    public function handle(Request $request, callable $next): Response
    {
        $start = hrtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->logError($request, $e);

            return Response::error(
                'An internal error occurred. Please try again.',
                'INTERNAL_ERROR',
                500
            );
        }

        // Log slow requests
        $durationMs = (hrtime(true) - $start) / 1_000_000;
        if ($durationMs > self::SLOW_REQUEST_MS) {
            $this->logSlow($request, $durationMs);
        }

        return $response;
    }

    private function logError(Request $request, \Throwable $e): void
    {
        $logDir = dirname(__DIR__, 4) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'ERROR',
            'method'    => $request->method,
            'uri'       => $request->path,
            'ip'        => $request->ip(),
            'error'     => $e->getMessage(),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => array_slice(
                array_map(
                    fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                    $e->getTrace()
                ),
                0,
                10
            ),
        ];

        @file_put_contents(
            $logDir . '/errors.jsonl',
            json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );

        // Also log to stderr for container environments
        error_log("[CloudStore ERROR] {$request->method} {$request->path}: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
    }

    private function logSlow(Request $request, float $durationMs): void
    {
        $logDir = dirname(__DIR__, 4) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'SLOW',
            'method'    => $request->method,
            'uri'       => $request->path,
            'duration'  => round($durationMs) . 'ms',
            'ip'        => $request->ip(),
        ];

        @file_put_contents(
            $logDir . '/slow_requests.jsonl',
            json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
