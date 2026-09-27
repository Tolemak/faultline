<?php

declare(strict_types=1);

namespace App\Maintenance;

final readonly class PurgeResult
{
    public function __construct(
        public int $events,
        public int $issues,
    ) {
    }
}
