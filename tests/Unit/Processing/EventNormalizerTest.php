<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processing;

use App\Enum\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventNormalizerTest extends TestCase
{
    public function testMapsAFullEvent(): void
    {
        $event = EventFactory::normalized([
            'timestamp' => 1790500000.25,
            'level' => 'warning',
            'platform' => 'php',
            'environment' => 'production',
            'release' => 'shop@1.2.0',
            'transaction' => 'GET /orders',
            'logentry' => ['message' => 'Order %s failed', 'formatted' => 'Order 42 failed'],
            'exception' => ['values' => [[
                'type' => 'RuntimeException',
                'value' => 'Boom',
                'module' => 'App',
                'mechanism' => ['type' => 'generic', 'handled' => true],
                'stacktrace' => ['frames' => [[
                    'filename' => 'src/Order.php',
                    'function' => 'load',
                    'lineno' => 10,
                    'colno' => 3,
                    'in_app' => true,
                    'context_line' => '$x = 1;',
                    'pre_context' => ['a', 1],
                    'post_context' => ['b'],
                    'vars' => ['id' => 42],
                ], 'not a frame']],
            ], 'not an exception']],
            'tags' => [['browser', 'Firefox'], ['os', 'Linux']],
            'contexts' => ['os' => ['name' => 'Linux']],
            'request' => ['url' => 'https://shop.example.com/a', 'method' => 'GET', 'headers' => ['Accept' => 'text/html'], 'query_string' => 'a=1', 'data' => ['q' => 1], 'env' => ['REMOTE_ADDR' => '192.0.2.1'], 'cookies' => ['sid' => 'x']],
            'user' => ['id' => '7', 'ip_address' => '192.0.2.10'],
            'extra' => ['k' => 'v'],
            'fingerprint' => ['{{ default }}', 'checkout', 3, ['bad']],
        ]);

        self::assertSame('2026-09-27T09:06:40.250000+00:00', $event->occurredAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(Level::Warning, $event->level);
        self::assertSame('php', $event->platform);
        self::assertSame('production', $event->environment);
        self::assertSame('shop@1.2.0', $event->release);
        self::assertSame('GET /orders', $event->transaction);
        self::assertSame('Order 42 failed', $event->message);
        self::assertSame('Order %s failed', $event->messageTemplate);
        self::assertCount(1, $event->exceptions);
        self::assertSame('RuntimeException', $event->exceptions[0]['type']);
        self::assertSame(['type' => 'generic', 'handled' => true], $event->exceptions[0]['mechanism']);
        self::assertCount(1, $event->exceptions[0]['frames']);
        self::assertSame(['a', ''], $event->exceptions[0]['frames'][0]['pre_context']);
        self::assertSame(10, $event->exceptions[0]['frames'][0]['lineno']);
        self::assertTrue($event->exceptions[0]['frames'][0]['in_app']);
        self::assertSame(['browser' => 'Firefox', 'os' => 'Linux'], $event->tags);
        self::assertSame(['os' => ['name' => 'Linux']], $event->contexts);
        self::assertSame(['url' => 'https://shop.example.com/a', 'method' => 'GET', 'query_string' => 'a=1', 'headers' => ['Accept' => 'text/html'], 'data' => ['q' => 1], 'env' => ['REMOTE_ADDR' => '192.0.2.1']], $event->request);
        self::assertSame(['id' => '7'], $event->user);
        self::assertSame(['k' => 'v'], $event->extra);
        self::assertSame(['{{ default }}', 'checkout', '3'], $event->fingerprint);
    }

    public function testFillsDefaultsForAnEmptyEvent(): void
    {
        $event = EventFactory::normalized(['user' => ['ip_address' => '192.0.2.1'], 'exception' => 'bad', 'fingerprint' => [['x']]]);

        self::assertSame('2026-09-27 12:00:00', $event->occurredAt->format('Y-m-d H:i:s'));
        self::assertSame(Level::Error, $event->level);
        self::assertSame('other', $event->platform);
        self::assertNull($event->message);
        self::assertSame([], $event->exceptions);
        self::assertSame([], $event->tags);
        self::assertNull($event->request);
        self::assertNull($event->user);
        self::assertNull($event->fingerprint);
    }

    public function testAcceptsPlainMessagesAndMessageObjects(): void
    {
        self::assertSame('Plain', EventFactory::normalized(['message' => 'Plain'])->message);

        $event = EventFactory::normalized(['message' => ['message' => 'User %s', 'formatted' => 'User 5']]);
        self::assertSame('User 5', $event->message);
        self::assertSame('User %s', $event->messageTemplate);

        self::assertSame('Only template', EventFactory::normalized(['logentry' => ['message' => 'Only template']])->message);
    }

    public function testAcceptsExceptionListsAndLimitsTags(): void
    {
        $tags = [];
        for ($i = 0; $i < 150; ++$i) {
            $tags['t'.$i] = (string) $i;
        }

        $event = EventFactory::normalized(['exception' => [['type' => 'E']], 'tags' => $tags]);

        self::assertSame('E', $event->exceptions[0]['type']);
        self::assertCount(100, $event->tags);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function timestamps(): iterable
    {
        yield 'iso string' => ['2026-09-27T10:00:00Z', '2026-09-27 10:00:00'];
        yield 'numeric string' => ['1790500000', '2026-09-27 09:06:40'];
        yield 'integer' => [1790500000, '2026-09-27 09:06:40'];
        yield 'garbage' => ['yesterday-ish', '2026-09-27 12:00:00'];
        yield 'too old' => [100, '2026-09-27 12:00:00'];
        yield 'future' => [1990000000, '2026-09-27 12:00:00'];
        yield 'wrong type' => [['x'], '2026-09-27 12:00:00'];
    }

    #[DataProvider('timestamps')]
    public function testParsesTimestamps(mixed $timestamp, string $expected): void
    {
        self::assertSame($expected, EventFactory::normalized(['timestamp' => $timestamp])->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testTruncatesLongStrings(): void
    {
        $event = EventFactory::normalized(['platform' => str_repeat('p', 100), 'environment' => true]);

        self::assertSame(64, mb_strlen($event->platform));
        self::assertSame('true', $event->environment);
    }
}
