<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\Project;

final readonly class ProjectOverview
{
    /**
     * @param list<int> $hourly
     */
    public function __construct(
        public Project $project,
        public int $openIssues,
        public array $hourly,
    ) {
    }

    public function eventsLastDay(): int
    {
        return array_sum($this->hourly);
    }
}
