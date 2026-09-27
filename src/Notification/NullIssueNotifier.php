<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Issue;

final class NullIssueNotifier implements IssueNotifierInterface
{
    public function notify(Issue $issue, IssueChange $change): void
    {
    }
}
