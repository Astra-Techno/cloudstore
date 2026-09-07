<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class HealthController
{
    public function index(Request $request, array $params): Response
    {
        return Response::success([
            'status' => 'healthy',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'version' => '0.1.0',
        ]);
    }

    public function ready(Request $request, array $params): Response
    {
        return Response::success(['status' => 'ready']);
    }

    public function live(Request $request, array $params): Response
    {
        return Response::success(['status' => 'alive']);
    }
}
