<?php

declare(strict_types=1);

namespace App\Notification;

enum IssueChange: string
{
    case New = 'new';
    case Regression = 'regression';
}
