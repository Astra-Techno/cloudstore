<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Database\Connection;
use App\Core\Http\Request;
use App\Core\Http\Response;

final class RateLimitMiddleware
{
    public function __construct(
        private readonly Connection $db,
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $identifier = $this->getIdentifier($request);
        $endpoint = $request->method . ':' . $request->path;

        // Clean expired entries periodically (1% chance per request)
        if (random_int(1, 100) === 1) {
            $this->db->execute("DELETE FROM rate_limits WHERE expires_at < NOW()");
        }

        $record = $this->db->fetchOne(
            "SELECT * FROM rate_limits WHERE identifier = ? AND endpoint = ? AND expires_at > NOW()",
            [$identifier, $endpoint]
        );

        if ($record === null) {
            $expiresAt = date('Y-m-d H:i:s', time() + $this->windowSeconds);
            $this->db->execute(
                "INSERT INTO rate_limits (identifier, endpoint, hits, window_start, expires_at)
                 VALUES (?, ?, 1, NOW(), ?)
                 ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW(), expires_at = VALUES(expires_at)",
                [$identifier, $endpoint, $expiresAt]
            );

            return null;
        }

        $hits = (int) $record['hits'];

        if ($hits >= $this->maxRequests) {
            $retryAfter = max(1, strtotime($record['expires_at']) - time());

            return Response::tooManyRequests(
                "Rate limit exceeded. Try again in {$retryAfter} seconds."
            );
        }

        $this->db->execute(
            "UPDATE rate_limits SET hits = hits + 1 WHERE id = ?",
            [(int) $record['id']]
        );

        return null;
    }

    private function getIdentifier(Request $request): string
    {
        if (isset($request->authClaims['sub'])) {
            return 'user:' . $request->authClaims['sub'];
        }

        return 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
