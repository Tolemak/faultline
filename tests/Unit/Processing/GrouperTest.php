<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processing;

use App\Processing\Grouper;
use PHPUnit\Framework\TestCase;

final class GrouperTest extends TestCase
{
    public function testGroupsByTypeAndInAppFramesIgnoringLineNumbers(): void
    {
        $grouper = new Grouper();
        $first = EventFactory::normalized(EventFactory::exception('TypeError', 'id 1 missing', [
            ['module' => 'vendor/lib', 'function' => 'call', 'lineno' => 1, 'in_app' => false],
            ['module' => 'app/orders', 'function' => 'load', 'lineno' => 10, 'in_app' => true],
        ]));
        $second = EventFactory::normalized(EventFactory::exception('TypeError', 'id 2 missing', [
            ['module' => 'vendor/lib', 'function' => 'other', 'lineno' => 99, 'in_app' => false],
            ['module' => 'app/orders', 'function' => 'load', 'lineno' => 12, 'in_app' => true],
        ]));

        self::assertSame(['type:TypeError', 'frame:app/orders|load'], $grouper->parts($first));
        self::assertSame($grouper->fingerprint($first), $grouper->fingerprint($second));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $grouper->fingerprint($first));
    }

    public function testFallsBackToAllFramesWithoutInAppOnes(): void
    {
        $event = EventFactory::normalized(EventFactory::exception('Error', 'x', [
            ['filename' => 'lib.js', 'function' => 'a'],
            ['abs_path' => '/srv/app/b.js', 'function' => 'b'],
        ]));

        self::assertSame(['type:Error', 'frame:lib.js|a', 'frame:/srv/app/b.js|b'], (new Grouper())->parts($event));
    }

    public function testUsesNormalizedValueWhenThereAreNoFrames(): void
    {
        $event = EventFactory::normalized(EventFactory::exception('Error', 'Timeout after 30s on 0xdeadbeef', []));

        self::assertSame(['type:Error', 'value:Timeout after <num>s on <hex>'], (new Grouper())->parts($event));
    }

    public function testFallsBackToTheMessageTemplate(): void
    {
        $grouper = new Grouper();
        $a = EventFactory::normalized(['message' => 'User 12 not found in "eu" (9ec79c33-ec99-42ab-8353-589fcb2e04dc)']);
        $b = EventFactory::normalized(['message' => 'User 99 not found in \'us\' (1ec79c33-ec99-42ab-8353-589fcb2e04dc)']);

        self::assertSame(['message:User <num> not found in <str> (<uuid>)'], $grouper->parts($a));
        self::assertSame($grouper->fingerprint($a), $grouper->fingerprint($b));
    }

    public function testFallsBackToThePlatformForEmptyEvents(): void
    {
        self::assertSame(['empty:python'], (new Grouper())->parts(EventFactory::normalized(['platform' => 'python', 'exception' => [['value' => null]]])));
    }

    public function testHonoursCustomFingerprints(): void
    {
        $grouper = new Grouper();
        $custom = EventFactory::normalized(['message' => 'a', 'fingerprint' => ['payments', 'timeout']]);
        $expanded = EventFactory::normalized(['message' => 'a', 'fingerprint' => ['{{ default }}', 'eu']]);
        $compact = EventFactory::normalized(['message' => 'a', 'fingerprint' => ['{{default}}']]);

        self::assertSame(['custom:payments', 'custom:timeout'], $grouper->parts($custom));
        self::assertSame(['message:a', 'custom:eu'], $grouper->parts($expanded));
        self::assertSame(['message:a'], $grouper->parts($compact));
    }
}
