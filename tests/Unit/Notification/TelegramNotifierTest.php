<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\Level;
use App\Notification\IssueChange;
use App\Notification\TelegramNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TelegramNotifierTest extends TestCase
{
    public function testSendsAnEscapedMessage(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options['body'] ?? ''];

            return new MockResponse('{"ok":true}');
        });

        $this->notifier($client, '123456:ABC-def_ghi', '-1001234')->notify($this->issue(), IssueChange::New);

        self::assertCount(1, $requests);
        [$method, $url, $body] = $requests[0];
        self::assertSame('POST', $method);
        self::assertSame('https://api.telegram.org/bot123456:ABC-def_ghi/sendMessage', $url);
        $payload = json_decode((string) $body, true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('-1001234', $payload['chat_id']);
        self::assertSame('HTML', $payload['parse_mode']);
        self::assertSame(
            "<b>New issue</b> · Shop &lt;EU&gt; · M4\nTypeError: a &lt; b\n<code>app.js in pay</code>\nhttps://errors.example.com/projects/shop/issues/0",
            $payload['text'],
        );
    }

    public function testDescribesRegressions(): void
    {
        $body = '';
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = (string) ($options['body'] ?? '');

            return new MockResponse('{"ok":true}');
        });

        $issue = new Issue(new Project('Shop', 'shop', str_repeat('a', 32), new \DateTimeImmutable()), 'f', 'Boom', null, Level::Warning, new \DateTimeImmutable());
        $this->notifier($client, '1:x', '@alerts')->notify($issue, IssueChange::Regression);

        self::assertStringContainsString('<b>Regression</b> · Shop · M3\nBoom\nhttps://', str_replace("\n", '\n', (string) json_decode($body, true, 8, \JSON_THROW_ON_ERROR)['text']));
    }

    public function testStaysSilentWithoutConfiguration(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => throw new \LogicException('No request expected.'));

        foreach ([['', ''], ['123:abc', ''], ['', '-100'], ['not a token', '-100'], ['123:abc', 'chat']] as [$token, $chat]) {
            $notifier = $this->notifier($client, $token, $chat);
            self::assertFalse($notifier->isEnabled());
            $notifier->notify($this->issue(), IssueChange::New);
        }

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testLogsTransportFailures(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
        $client = new MockHttpClient(static fn (): MockResponse => throw new TransportException('offline'));

        $this->notifier($client, '1:x', '-1', $logger)->notify($this->issue(), IssueChange::New);

        self::assertSame(['Telegram notification failed: {reason}'], $logger->messages);
    }

    private function notifier(MockHttpClient $client, string $token, string $chat, ?AbstractLogger $logger = null): TelegramNotifier
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://errors.example.com/projects/shop/issues/0');

        return new TelegramNotifier($client, $urls, $logger ?? new \Psr\Log\NullLogger(), $token, $chat);
    }

    private function issue(): Issue
    {
        $project = new Project('Shop <EU>', 'shop', str_repeat('a', 32), new \DateTimeImmutable());

        return new Issue($project, 'f', 'TypeError: a < b', 'app.js in pay', Level::Error, new \DateTimeImmutable());
    }
}
