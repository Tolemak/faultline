<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processing;

use App\Processing\Scrubber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScrubberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveKeys(): iterable
    {
        foreach (['password', 'db_passwd', 'client_secret', 'accessToken', 'api_key', 'apikey', 'Authorization', 'set_cookie', 'session_id', 'csrf', 'SENTRY_DSN', 'private_key'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('sensitiveKeys')]
    public function testFiltersSensitiveKeysAtAnyDepth(string $key): void
    {
        $scrubbed = (new Scrubber())->scrub(['outer' => [['inner' => [$key => 'value', 'safe' => 'kept']]]]);

        self::assertSame(['outer' => [['inner' => [$key => Scrubber::FILTERED, 'safe' => 'kept']]]], $scrubbed);
    }

    public function testMasksCardNumbersPassingLuhn(): void
    {
        $scrubber = new Scrubber();

        self::assertSame('paid with [card] today', $scrubber->scrubString('paid with 4111 1111 1111 1111 today'));
        self::assertSame('card [card]', $scrubber->scrubString('card 5500-0000-0000-0004'));
        self::assertSame('order 4111111111111112', $scrubber->scrubString('order 4111111111111112'));
        self::assertSame('id 1234567890', $scrubber->scrubString('id 1234567890'));
    }

    public function testMasksValidIbans(): void
    {
        $scrubber = new Scrubber();

        self::assertSame('to [iban]', $scrubber->scrubString('to DE89 3704 0044 0532 0130 00'));
        self::assertSame('to [iban]', $scrubber->scrubString('to GB82WEST12345698765432'));
        self::assertSame('to GB00WEST12345698765432', $scrubber->scrubString('to GB00WEST12345698765432'));
    }

    public function testFiltersQueryStringsAndUrls(): void
    {
        $scrubber = new Scrubber();

        self::assertSame('page=2&token=[filtered]&api%5Fkey=[filtered]', $scrubber->scrubQueryString('page=2&token=abc&api%5Fkey=x'));
        self::assertSame('', $scrubber->scrubQueryString(''));
        self::assertSame('https://shop.example.com/a?session=[filtered]&q=1#top', $scrubber->scrubUrl('https://shop.example.com/a?session=s1&q=1#top'));
        self::assertSame('https://shop.example.com/a?q=1', $scrubber->scrubUrl('https://shop.example.com/a?q=1'));
        self::assertSame('https://shop.example.com/a', $scrubber->scrubUrl('https://shop.example.com/a'));
    }

    public function testScrubsAWholeEvent(): void
    {
        $event = EventFactory::normalized([
            'message' => 'Card 4111 1111 1111 1111 declined',
            'logentry' => ['message' => 'Card 4111 1111 1111 1111 declined'],
            'exception' => ['values' => [[
                'type' => 'PaymentError',
                'value' => 'IBAN DE89370400440532013000 rejected',
                'stacktrace' => ['frames' => [['function' => 'pay', 'vars' => ['password' => 'hunter2', 'amount' => 10]], ['function' => 'main']]],
            ]]],
            'tags' => ['auth_mode' => 'basic', 'browser' => 'Firefox'],
            'contexts' => ['app' => ['secret' => 'x', 'name' => 'shop']],
            'request' => [
                'url' => 'https://shop.example.com/pay?token=abc&step=2',
                'query_string' => 'token=abc&step=2',
                'headers' => ['Authorization' => 'Bearer abc', 'Cookie' => 'sid=1', 'X-Api-Key' => 'k', 'Accept' => 'json'],
                'data' => ['card' => '4111111111111111', 'csrf_token' => 't'],
            ],
            'user' => ['id' => '1', 'email' => 'someone@example.com', 'session' => 's'],
            'extra' => ['private' => 'x', 'note' => 'ok'],
        ]);

        $scrubbed = (new Scrubber())->scrubEvent($event);

        self::assertSame('Card [card] declined', $scrubbed->message);
        self::assertSame('Card [card] declined', $scrubbed->messageTemplate);
        self::assertSame('IBAN [iban] rejected', $scrubbed->exceptions[0]['value']);
        self::assertSame(['password' => Scrubber::FILTERED, 'amount' => 10], $scrubbed->exceptions[0]['frames'][0]['vars']);
        self::assertNull($scrubbed->exceptions[0]['frames'][1]['vars']);
        self::assertSame(['auth_mode' => Scrubber::FILTERED, 'browser' => 'Firefox'], $scrubbed->tags);
        self::assertSame(['app' => ['secret' => Scrubber::FILTERED, 'name' => 'shop']], $scrubbed->contexts);
        $request = $scrubbed->request ?? [];
        self::assertSame(['card' => '[card]', 'csrf_token' => Scrubber::FILTERED], $request['data']);
        self::assertSame(['X-Api-Key' => Scrubber::FILTERED, 'Accept' => 'json'], $request['headers']);
        self::assertSame('https://shop.example.com/pay?token=[filtered]&step=2', $request['url']);
        self::assertSame('token=[filtered]&step=2', $request['query_string']);
        self::assertSame(['id' => '1', 'email' => 'someone@example.com', 'session' => Scrubber::FILTERED], $scrubbed->user);
        self::assertSame(['private' => Scrubber::FILTERED, 'note' => 'ok'], $scrubbed->extra);
    }

    public function testScrubsArrayQueryStringsAndKeepsEmptyParts(): void
    {
        $event = EventFactory::normalized(['request' => ['query_string' => ['token' => 'x', 'q' => 'y'], 'headers' => ['Cookie' => 'a']]]);

        $scrubbed = (new Scrubber())->scrubEvent($event);

        self::assertSame(['query_string' => ['token' => Scrubber::FILTERED, 'q' => 'y']], $scrubbed->request);

        $empty = (new Scrubber())->scrubEvent(EventFactory::normalized([]));
        self::assertNull($empty->message);
        self::assertNull($empty->request);
        self::assertNull($empty->user);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function authSensitiveKeys(): iterable
    {
        foreach (['auth', 'auth_token', 'x-auth-token', 'Authorization', 'authorisation', 'X-Auth', 'AUTH', 'basicAuth', 'basic_auth', 'bearer'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('authSensitiveKeys')]
    public function testAuthKeyIsSensitiveAsSegment(string $key): void
    {
        self::assertTrue((new Scrubber())->isSensitiveKey($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function authNonSensitiveKeys(): iterable
    {
        foreach (['author', 'author_id', 'authority', 'oauth_provider_name', 'oauth', 'coauthor'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('authNonSensitiveKeys')]
    public function testAuthKeyIsNotSensitiveWhenNotASegment(string $key): void
    {
        self::assertFalse((new Scrubber())->isSensitiveKey($key));
    }

    public function testExceptionTypeScrubbed(): void
    {
        $event = EventFactory::normalized([
            'exception' => ['values' => [[
                'type' => 'Error 4111 1111 1111 1111',
                'value' => 'IBAN DE89370400440532013000 found',
                'stacktrace' => ['frames' => []],
            ]]],
        ]);

        $scrubbed = (new Scrubber())->scrubEvent($event);

        self::assertSame('Error [card]', $scrubbed->exceptions[0]['type']);
        self::assertSame('IBAN [iban] found', $scrubbed->exceptions[0]['value']);
    }

    public function testExceptionTypeNullPassthrough(): void
    {
        $event = EventFactory::normalized([
            'exception' => ['values' => [[
                'type' => null,
                'value' => null,
                'stacktrace' => ['frames' => []],
            ]]],
        ]);

        $scrubbed = (new Scrubber())->scrubEvent($event);

        self::assertNull($scrubbed->exceptions[0]['type']);
        self::assertNull($scrubbed->exceptions[0]['value']);
    }
}
