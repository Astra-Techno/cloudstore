<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class LegalController
{
    public function privacyPolicy(Request $request, array $params): Response
    {
        return Response::success([
            'content' => 'This is the privacy policy for our platform. We collect and process your personal data '
                . '(name, phone number, email, delivery address) solely to provide our food ordering and delivery '
                . 'services. Your data is never sold to third parties. You may request deletion of your account '
                . 'and personal data at any time through the app settings.',
            'last_updated' => '2026-01-01',
        ]);
    }

    public function terms(Request $request, array $params): Response
    {
        return Response::success([
            'content' => 'These are the terms of service for our platform. By using this application you agree '
                . 'to abide by these terms. Orders placed through the app are subject to availability and delivery '
                . 'area restrictions. Prices displayed include applicable taxes unless stated otherwise. '
                . 'We reserve the right to refuse service or cancel orders at our discretion.',
            'last_updated' => '2026-01-01',
        ]);
    }
}
