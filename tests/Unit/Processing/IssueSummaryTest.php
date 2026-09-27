<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processing;

use App\Processing\IssueSummary;
use PHPUnit\Framework\TestCase;

final class IssueSummaryTest extends TestCase
{
    public function testUsesTheOutermostExceptionAndInAppFrame(): void
    {
        $summary = IssueSummary::of(EventFactory::normalized(['exception' => ['values' => [
            ['type' => 'InnerError', 'value' => 'inner'],
            ['type' => 'OuterError', 'value' => "outer\nsecond line", 'stacktrace' => ['frames' => [
                ['module' => 'app.orders', 'function' => 'load', 'in_app' => true],
                ['module' => 'lib.http', 'function' => 'send', 'in_app' => false],
            ]]],
        ]]]));

        self::assertSame('OuterError: outer', $summary->title);
        self::assertSame('app.orders in load', $summary->culprit);
    }

    public function testPrefersTheTransactionAsCulprit(): void
    {
        $summary = IssueSummary::of(EventFactory::normalized(['transaction' => 'POST /pay'] + EventFactory::exception('E', 'v', [['filename' => 'a.js', 'function' => 'f']])));

        self::assertSame('POST /pay', $summary->culprit);
    }

    public function testUsesTheLastFrameWithoutInAppFrames(): void
    {
        $summary = IssueSummary::of(EventFactory::normalized(EventFactory::exception('E', 'v', [
            ['filename' => 'first.js', 'function' => 'a'],
            ['filename' => 'last.js'],
        ])));

        self::assertSame('last.js', $summary->culprit);
    }

    public function testBuildsTitlesFromWhateverIsPresent(): void
    {
        self::assertSame('TypeOnly', IssueSummary::of(EventFactory::normalized(['exception' => [['type' => 'TypeOnly']]]))->title);
        self::assertSame('value only', IssueSummary::of(EventFactory::normalized(['exception' => [['value' => 'value only']]]))->title);
        self::assertSame('A message', IssueSummary::of(EventFactory::normalized(['message' => 'A message']))->title);

        $empty = IssueSummary::of(EventFactory::normalized([]));
        self::assertSame('<unlabeled event>', $empty->title);
        self::assertNull($empty->culprit);
        self::assertNull(IssueSummary::of(EventFactory::normalized(EventFactory::exception('E', 'v', [['lineno' => 1]])))->culprit);
    }

    public function testTruncatesLongTitles(): void
    {
        self::assertSame(255, mb_strlen(IssueSummary::of(EventFactory::normalized(['message' => str_repeat('x', 400)]))->title));
    }
}
