<?php

declare(strict_types=1);

namespace App\Tests\Unit\Query;

use App\Enum\IssueStatus;
use App\Enum\Level;
use App\Query\IssueCriteria;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class IssueCriteriaTest extends TestCase
{
    public function testReadsAValidQuery(): void
    {
        $criteria = IssueCriteria::fromRequest(new Request([
            'status' => 'resolved', 'level' => 'fatal', 'environment' => ' production ', 'release' => 'v1', 'q' => 'timeout', 'sort' => 'events', 'page' => '3',
        ]));

        self::assertSame(IssueStatus::Resolved, $criteria->status);
        self::assertSame(Level::Fatal, $criteria->level);
        self::assertSame('production', $criteria->environment);
        self::assertSame('v1', $criteria->release);
        self::assertSame('timeout', $criteria->search);
        self::assertSame('events', $criteria->sort);
        self::assertSame(3, $criteria->page);
        self::assertSame(['status' => 'resolved', 'level' => 'fatal', 'environment' => 'production', 'release' => 'v1', 'q' => 'timeout', 'sort' => 'events'], $criteria->toQuery());
    }

    public function testFallsBackOnInvalidValues(): void
    {
        $criteria = IssueCriteria::fromRequest(new Request([
            'status' => ['x'], 'level' => 'loud', 'environment' => '', 'q' => str_repeat('a', 500), 'sort' => 'random', 'page' => '-4',
        ]));

        self::assertSame(IssueStatus::Unresolved, $criteria->status);
        self::assertNull($criteria->level);
        self::assertNull($criteria->environment);
        self::assertSame(200, mb_strlen((string) $criteria->search));
        self::assertSame('last_seen', $criteria->sort);
        self::assertSame(1, $criteria->page);
        self::assertSame(['status' => 'unresolved', 'q' => str_repeat('a', 200)], $criteria->toQuery());
    }

    public function testSupportsAllStatuses(): void
    {
        $criteria = IssueCriteria::fromRequest(new Request(['status' => 'all']));

        self::assertNull($criteria->status);
        self::assertSame(['status' => 'all'], $criteria->toQuery());
    }
}
