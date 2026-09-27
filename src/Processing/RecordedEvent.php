<?php

declare(strict_types=1);

namespace App\Processing;

use App\Entity\Issue;
use App\Notification\IssueChange;

final readonly class RecordedEvent
{
    public function __construct(
        public Issue $issue,
        public ?IssueChange $change,
    ) {
    }
}
