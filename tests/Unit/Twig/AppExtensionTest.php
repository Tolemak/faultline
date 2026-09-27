<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Http\CspNonce;
use App\Twig\AppExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;

final class AppExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function moments(): iterable
    {
        yield 'seconds' => ['2026-09-27 11:59:30', 'time.just_now'];
        yield 'minutes' => ['2026-09-27 11:55:00', 'time.minutes_ago'];
        yield 'hours' => ['2026-09-27 09:00:00', 'time.hours_ago'];
        yield 'days' => ['2026-09-20 12:00:00', 'time.days_ago'];
        yield 'future' => ['2026-09-27 12:30:00', 'time.just_now'];
    }

    #[DataProvider('moments')]
    public function testFormatsRelativeTimes(string $moment, string $key): void
    {
        self::assertSame($key, $this->extension()->ago(new \DateTimeImmutable($moment)));
    }

    public function testGroupsFramesByOrigin(): void
    {
        $groups = $this->extension()->frameGroups([
            ['function' => 'a', 'in_app' => false],
            ['function' => 'b', 'in_app' => false],
            ['function' => 'c', 'in_app' => true],
            ['function' => 'd', 'in_app' => null],
        ]);

        self::assertSame([false, true, false], array_column($groups, 'in_app'));
        self::assertSame(['b', 'a'], array_column($groups[2]['frames'], 'function'));
    }

    public function testShowsLonelyLibraryFrames(): void
    {
        self::assertTrue($this->extension()->frameGroups([['function' => 'a']])[0]['in_app']);
        self::assertSame([], $this->extension()->frameGroups([]));
    }

    public function testExposesThemeAndNonce(): void
    {
        $requests = new RequestStack();
        $extension = $this->extension($requests);
        self::assertNull($extension->theme());

        $request = new Request(cookies: ['faultline_theme' => 'dark']);
        $requests->push($request);
        self::assertSame('dark', $extension->theme());
        self::assertSame($extension->cspNonce(), $extension->cspNonce());
        self::assertSame('0.0,9.0 10.0,1.0', $extension->sparkline([0, 1], 10, 10));
    }

    private function extension(?RequestStack $requests = null): AppExtension
    {
        return new AppExtension(new CspNonce(), $requests ?? new RequestStack(), new IdentityTranslator(), new MockClock('2026-09-27 12:00:00'));
    }
}
