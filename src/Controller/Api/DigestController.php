<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Query\DigestQuery;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class DigestController
{
    public function __construct(
        private DigestQuery $digest,
        #[Autowire(env: 'DIGEST_TOKEN')]
        private string $token,
    ) {
    }

    #[Route('/api/digest', name: 'api_digest', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (\strlen($this->token) < 16) {
            return new JsonResponse(['detail' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $header = (string) $request->headers->get('Authorization');
        $provided = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        if (!hash_equals($this->token, $provided)) {
            return new JsonResponse(['detail' => 'Invalid token.'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
        }

        $response = new JsonResponse($this->digest->build());
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
