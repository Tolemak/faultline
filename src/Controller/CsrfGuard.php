<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;

trait CsrfGuard
{
    private function assertCsrf(Request $request, string $tokenId): void
    {
        $token = $request->request->all()['_token'] ?? null;

        if (!\is_string($token) || !$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
