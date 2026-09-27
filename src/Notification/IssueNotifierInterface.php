<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Issue;

interface IssueNotifierInterface
{
    public function notify(Issue $issue, IssueChange $change): void;
}
