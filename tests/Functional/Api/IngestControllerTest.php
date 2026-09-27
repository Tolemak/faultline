<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Project;
use App\Message\ProcessEvent;
use App\Tests\Support\ProjectFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class IngestControllerTest extends WebTestCase
{
    use ProjectFixtures;

    private const string EVENT_ID = '9ec79c33ec9942ab8353589fcb2e04dc';

    private KernelBrowser $client;
    private Project $project;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->project = self::createProject(self::getContainer(), 'Shop', ['https://shop.example.com']);
    }

    public function testAcceptsAnEnvelopeAndQueuesOnlyEvents(): void
    {
        $body = $this->envelope(['event_id' => self::EVENT_ID], [
            ['event', ['event_id' => self::EVENT_ID, 'message' => 'Hello']],
            ['transaction', ['type' => 'transaction']],
            ['session', ['sid' => 'x']],
            ['client_report', ['discarded_events' => []]],
        ]);

        $this->post('envelope', $body, ['HTTP_X_SENTRY_AUTH' => $this->auth()]);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => self::EVENT_ID], $this->json());
        $messages = $this->queued();
        self::assertCount(1, $messages);
        self::assertSame(self::EVENT_ID, $messages[0]->eventId);
        self::assertSame(self::projectId($this->project), $messages[0]->projectId);
        self::assertStringContainsString('Hello', $messages[0]->payload);
    }

    public function testAcceptsEnvelopesWithoutEvents(): void
    {
        $this->post('envelope', $this->envelope([], [['session', ['sid' => 'x']]]), ['HTTP_X_SENTRY_AUTH' => $this->auth()]);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => ''], $this->json());
        self::assertSame([], $this->queued());
    }

    public function testGeneratesIdsForEventsWithoutOne(): void
    {
        $this->post('envelope', $this->envelope([], [['event', ['message' => 'no id']]]), ['HTTP_X_SENTRY_AUTH' => $this->auth()]);

        $id = $this->json()['id'] ?? null;
        self::assertIsString($id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
        self::assertSame($id, $this->queued()[0]->eventId);
    }

    public function testAuthenticatesThroughTheEnvelopeDsn(): void
    {
        $dsn = 'https://'.$this->project->getPublicKey().'@errors.example.com/'.self::projectId($this->project);

        $this->post('envelope', $this->envelope(['dsn' => $dsn], [['event', ['message' => 'x']]]));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->queued());
    }

    public function testAcceptsTheStoreEndpointWithQueryKey(): void
    {
        $this->post('store', (string) json_encode(['event_id' => self::EVENT_ID, 'message' => 'legacy']), [], '?sentry_key='.$this->project->getPublicKey());

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => self::EVENT_ID], $this->json());
        self::assertCount(1, $this->queued());
    }

    public function testStoreRequiresAKey(): void
    {
        $this->post('store', '{"message":"x"}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAcceptsGzipAndDeflateBodies(): void
    {
        $body = $this->envelope([], [['event', ['message' => 'compressed']]]);

        $this->post('envelope', (string) gzencode($body), ['HTTP_X_SENTRY_AUTH' => $this->auth(), 'HTTP_CONTENT_ENCODING' => 'gzip']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->queued());

        $this->post('envelope', (string) gzcompress($body), ['HTTP_X_SENTRY_AUTH' => $this->auth(), 'HTTP_CONTENT_ENCODING' => 'deflate']);
        self::assertResponseIsSuccessful();
    }

    public function testRejectsAWrongKey(): void
    {
        $this->post('envelope', $this->envelope([], [['event', ['message' => 'x']]]), ['HTTP_X_SENTRY_AUTH' => 'Sentry sentry_key='.str_repeat('0', 32)]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->queued());
    }

    public function testRejectsAMissingKey(): void
    {
        $this->post('envelope', $this->envelope([], [['event', ['message' => 'x']]]));

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsUnknownProjects(): void
    {
        $this->client->request('POST', '/api/999999/envelope/', server: ['HTTP_X_SENTRY_AUTH' => $this->auth()], content: '{}');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('OPTIONS', '/api/999999/envelope/');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRejectsOversizedBodies(): void
    {
        $this->post('envelope', str_repeat('a', 1_048_577), ['HTTP_X_SENTRY_AUTH' => $this->auth()]);

        self::assertResponseStatusCodeSame(413);
    }

    public function testRejectsOversizedDeclaredLength(): void
    {
        $this->post('envelope', '{}', ['HTTP_X_SENTRY_AUTH' => $this->auth(), 'CONTENT_LENGTH' => '5000000']);

        self::assertResponseStatusCodeSame(413);
    }

    public function testRejectsInflateBombs(): void
    {
        $bomb = (string) gzencode(str_repeat('a', 20_000_000), 9);
        self::assertLessThan(1_048_576, \strlen($bomb));

        $this->post('envelope', $bomb, ['HTTP_X_SENTRY_AUTH' => $this->auth(), 'HTTP_CONTENT_ENCODING' => 'gzip']);

        self::assertResponseStatusCodeSame(413);
        self::assertSame([], $this->queued());
    }

    public function testRejectsDeeplyNestedJson(): void
    {
        $deep = str_repeat('{"a":', 100).'1'.str_repeat('}', 100);

        $this->post('envelope', "{}\n{\"type\":\"event\"}\n".$deep, ['HTTP_X_SENTRY_AUTH' => $this->auth()]);

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('Invalid JSON', (string) ($this->json()['detail'] ?? ''));
    }

    public function testRejectsUnsupportedEncodings(): void
    {
        $this->post('envelope', 'x', ['HTTP_X_SENTRY_AUTH' => $this->auth(), 'HTTP_CONTENT_ENCODING' => 'br']);

        self::assertResponseStatusCodeSame(415);
    }

    public function testAppliesCorsForAllowedOrigins(): void
    {
        $this->post('envelope', $this->envelope([], [['event', ['message' => 'x']]]), ['HTTP_ORIGIN' => 'https://shop.example.com'], '?sentry_key='.$this->project->getPublicKey());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://shop.example.com');
        self::assertResponseHeaderSame('Vary', 'Origin');
    }

    public function testRejectsDisallowedOrigins(): void
    {
        $this->post('envelope', $this->envelope([], [['event', ['message' => 'x']]]), ['HTTP_ORIGIN' => 'https://evil.example.com'], '?sentry_key='.$this->project->getPublicKey());

        self::assertResponseStatusCodeSame(403);
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
        self::assertSame([], $this->queued());
    }

    public function testAnswersPreflightRequests(): void
    {
        $id = self::projectId($this->project);

        $this->client->request('OPTIONS', "/api/{$id}/envelope/", server: ['HTTP_ORIGIN' => 'https://shop.example.com', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST']);
        self::assertResponseStatusCodeSame(204);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://shop.example.com');
        self::assertResponseHeaderSame('Access-Control-Allow-Methods', 'POST, OPTIONS');
        self::assertStringContainsString('x-sentry-auth', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Headers'));

        $this->client->request('OPTIONS', "/api/{$id}/envelope/", server: ['HTTP_ORIGIN' => 'https://evil.example.com']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRateLimitsPerKey(): void
    {
        $this->client->disableReboot();
        $body = $this->envelope([], [['event', ['message' => 'x']]]);

        for ($i = 0; $i < 5; ++$i) {
            $this->post('envelope', $body, ['HTTP_X_SENTRY_AUTH' => $this->auth()]);
            self::assertResponseIsSuccessful();
        }

        $this->post('envelope', $body, ['HTTP_X_SENTRY_AUTH' => $this->auth()]);

        self::assertResponseStatusCodeSame(429);
        $retryAfter = (string) $this->client->getResponse()->headers->get('Retry-After');
        self::assertMatchesRegularExpression('/^\d+$/', $retryAfter);
        self::assertResponseHeaderSame('X-Sentry-Rate-Limits', $retryAfter.'::organization');
    }

    /**
     * @param array<string, string> $server
     */
    private function post(string $endpoint, string $body, array $server = [], string $query = ''): void
    {
        $this->client->request('POST', '/api/'.self::projectId($this->project).'/'.$endpoint.'/'.$query, server: $server, content: $body);
    }

    private function auth(): string
    {
        return 'Sentry sentry_version=7, sentry_client=test/1.0, sentry_key='.$this->project->getPublicKey();
    }

    /**
     * @param array<string, mixed>                      $headers
     * @param list<array{string, array<string, mixed>}> $items
     */
    private function envelope(array $headers, array $items): string
    {
        $lines = [json_encode((object) $headers, \JSON_THROW_ON_ERROR)];
        foreach ($items as [$type, $payload]) {
            $lines[] = json_encode(['type' => $type], \JSON_THROW_ON_ERROR);
            $lines[] = json_encode($payload, \JSON_THROW_ON_ERROR);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * @return list<ProcessEvent>
     */
    private function queued(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(ProcessEvent::class, $message);
            $messages[] = $message;
        }

        return $messages;
    }
}
