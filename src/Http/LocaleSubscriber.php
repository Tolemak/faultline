<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final class LocaleSubscriber
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $language = Preferences::language($request);

        if (null !== $language) {
            $request->setLocale($language);
        }
    }
}
