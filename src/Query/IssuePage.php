<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\Issue;

final readonly class IssuePage
{
    /**
     * @param list<Issue>           $issues
     * @param array<int, list<int>> $trends
     */
    public function __construct(
        public array $issues,
        public array $trends,
        public int $total,
        public int $page,
        public int $pages,
    ) {
    }
}
