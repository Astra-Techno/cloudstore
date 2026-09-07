<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class CorsMiddleware
{
    private array $allowedOrigins;

    public function __construct(string $allowedOrigins = '*')
    {
        $this->allowedOrigins = $allowedOrigins === '*'
            ? ['*']
            : array_map('trim', explode(',', $allowedOrigins));
    }

    public function handle(Request $request): ?Response
    {
        $origin = $request->header('origin');

        // Preflight
        if ($request->method === 'OPTIONS') {
            return $this->preflightResponse($origin);
        }

        // Store origin for post-response CORS headers
        if ($origin !== '' && $this->isAllowed($origin)) {
            $request->attributes['cors_origin'] = $origin;
        }

        return null;
    }

    /**
     * Send CORS headers. Called from Application after response is built.
     */
    public static function sendHeaders(Request $request): void
    {
        $origin = $request->attributes['cors_origin'] ?? null;
        if ($origin === null) {
            return;
        }

        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Token, X-Idempotency-Key');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }

    private function preflightResponse(string $origin): Response
    {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Token, X-Idempotency-Key');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400');

        return Response::success(null, status: 204);
    }

    private function isAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }
}
