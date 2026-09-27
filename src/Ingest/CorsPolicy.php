<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Entity\Project;
use Symfony\Component\HttpFoundation\Request;

final class CorsPolicy
{
    private const string ALLOWED_HEADERS = 'content-type, x-sentry-auth, sentry-trace, baggage';
    private const string EXPOSED_HEADERS = 'x-sentry-error, x-sentry-rate-limits, retry-after';

    /**
     * @return array<string, string>
     */
    public function responseHeaders(Request $request, Project $project): array
    {
        $origin = $request->headers->get('Origin');
        if (null === $origin) {
            return [];
        }

        if (!$project->allowsOrigin($origin)) {
            throw IngestException::forbiddenOrigin();
        }

        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Expose-Headers' => self::EXPOSED_HEADERS,
            'Vary' => 'Origin',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function preflightHeaders(Request $request, Project $project): array
    {
        return $this->responseHeaders($request, $project) + [
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => self::ALLOWED_HEADERS,
            'Access-Control-Max-Age' => '86400',
        ];
    }
}
