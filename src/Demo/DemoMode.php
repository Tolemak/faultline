<?php

declare(strict_types=1);

namespace App\Demo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DemoMode
{
    public const string USERNAME = 'demo';

    public function __construct(
        #[Autowire(env: 'bool:FAULTLINE_DEMO')]
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
