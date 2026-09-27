<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\Sparkline;
use PHPUnit\Framework\TestCase;

final class SparklineTest extends TestCase
{
    public function testScalesValuesIntoTheBox(): void
    {
        self::assertSame('0.0,9.0 50.0,5.0 100.0,1.0', Sparkline::points([0, 1, 2], 100, 10));
        self::assertSame('0.0,9.0 100.0,9.0', Sparkline::points([0, 0], 100, 10));
        self::assertSame('50.0,1.0', Sparkline::points([3], 100, 10));
        self::assertSame('', Sparkline::points([], 100, 10));
    }
}
