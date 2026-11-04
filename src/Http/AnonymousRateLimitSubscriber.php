<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Per-IP limit for web traffic, so a flood (e.g. a scanner) gets a cheap 429 before the firewall,
 * controllers and database. /health and /api (ingest, digest; own limits) are exempt.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final readonly class AnonymousRateLimitSubscriber
{
    private const array EXEMPT_PREFIXES = ['/health', '/api/'];

    public function __construct(
        #[Target('anonymous')]
        private RateLimiterFactoryInterface $anonymousLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $limit = $this->anonymousLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $event->setResponse(new Response('Too many requests.', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())]));
        }
    }
}
