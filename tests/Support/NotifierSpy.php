<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Issue;
use App\Notification\IssueChange;
use App\Notification\IssueNotifierInterface;

final class NotifierSpy implements IssueNotifierInterface
{
    /**
     * @var list<array{Issue, IssueChange}>
     */
    public array $calls = [];

    public function notify(Issue $issue, IssueChange $change): void
    {
        $this->calls[] = [$issue, $change];
    }
}
