<?php

declare(strict_types=1);

namespace App\Demo;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class DemoGuard
{
    private const array ALLOWED_WRITES = ['/logout'];

    public function __construct(private DemoMode $demo)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->demo->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api/')) {
            $event->setResponse(new Response('', Response::HTTP_NOT_FOUND));

            return;
        }

        if ($request->isMethodSafe() || \in_array($path, self::ALLOWED_WRITES, true)) {
            return;
        }

        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', 'demo.read_only');
            }
        }

        $event->setResponse(new RedirectResponse(self::back($request), Response::HTTP_SEE_OTHER));
    }

    private static function back(Request $request): string
    {
        $referer = $request->headers->get('referer');
        $parts = null === $referer ? false : parse_url($referer);
        if (!\is_array($parts) || ($parts['host'] ?? null) !== $request->getHost()) {
            return $request->getBasePath().'/';
        }

        $path = $parts['path'] ?? '/';

        return isset($parts['query']) ? $path.'?'.$parts['query'] : $path;
    }
}
