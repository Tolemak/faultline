<?php

declare(strict_types=1);

namespace App\Tests\Functional\Contract;

use App\Entity\Event;
use App\Entity\Issue;
use App\Entity\Project;
use App\Enum\Level;
use App\Message\ProcessEvent;
use App\Processing\ProcessEventHandler;
use App\Processing\Scrubber;
use App\Tests\Support\KernelSentryHttpClient;
use App\Tests\Support\ProjectFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Sentry\ClientBuilder;
use Sentry\Severity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class SentryContractTest extends WebTestCase
{
    use ProjectFixtures;

    private KernelBrowser $client;
    private Project $project;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->project = self::createProject(self::getContainer(), 'Shop', ['https://shop.example.com']);
    }

    public function testPhpSdkExceptionsAreStoredAndGrouped(): void
    {
        $sentry = $this->phpSdk(compress: true);

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $this->chargeCard('4111 1111 1111 1111');
            } catch (\RuntimeException $e) {
                $sentry->captureException($e);
            }
            self::assertResponseIsSuccessful();
            $this->processQueued();
        }

        $issues = $this->issues();
        self::assertCount(1, $issues);
        self::assertSame('RuntimeException: Charging card [card] failed', $issues[0]->getTitle());
        self::assertSame(2, $issues[0]->getEventCount());
        self::assertStringContainsString('chargeCard', (string) $issues[0]->getCulprit());

        $event = $this->events()[0];
        self::assertSame('php', $event->getPlatform());
        self::assertSame('contract', $event->getEnvironment());
        self::assertSame('shop@1.0.0', $event->getRelease());
        self::assertNotSame([], $event->getException()[0]['frames'] ?? []);
    }

    public function testPhpSdkMessagesWithoutCompression(): void
    {
        $this->phpSdk(compress: false)->captureMessage('Queue backlog above 500 jobs', Severity::warning());

        self::assertResponseIsSuccessful();
        $this->processQueued();

        $issue = $this->issues()[0];
        self::assertSame('Queue backlog above 500 jobs', $issue->getTitle());
        self::assertSame(Level::Warning, $issue->getLevel());
    }

    public function testNodeEnvelope(): void
    {
        $this->client->request('POST', $this->url('?sentry_key='.$this->project->getPublicKey().'&sentry_version=7&sentry_client=sentry.javascript.node%2F8.40.0'), content: $this->fixture('node'));

        self::assertResponseIsSuccessful();
        self::assertSame('{"id":"4c9e21d0a8f64b5a9d3c3e7f1b2a6d10"}', $this->client->getResponse()->getContent());
        $this->processQueued();

        $issue = $this->issues()[0];
        self::assertSame("TypeError: Cannot read properties of undefined (reading 'id')", $issue->getTitle());
        self::assertSame('orders in loadOrder', $issue->getCulprit());
        self::assertSame('shop-api@2.3.1', $issue->getLastRelease());

        $event = $this->events()[0];
        self::assertSame('node', $event->getPlatform());
        self::assertSame(['region' => 'eu-central'], $event->getTags());
        $request = $event->getRequest() ?? [];
        self::assertSame('https://api.example.com/orders/17?session=[filtered]', $request['url'] ?? null);
        self::assertArrayNotHasKey('cookie', \is_array($request['headers'] ?? null) ? $request['headers'] : []);
    }

    public function testBrowserEnvelopeWithCors(): void
    {
        $this->client->request('POST', $this->url('?sentry_key='.$this->project->getPublicKey().'&sentry_version=7'), server: [
            'HTTP_ORIGIN' => 'https://shop.example.com',
            'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        ], content: $this->fixture('browser'));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://shop.example.com');
        $this->processQueued();

        $issue = $this->issues()[0];
        self::assertSame('Error: Checkout button handler failed', $issue->getTitle());
        self::assertSame('https://shop.example.com/assets/app-9c1d.js in submitCheckout', $issue->getCulprit());
        self::assertNull($this->events()[0]->getUser());
    }

    public function testPythonEnvelopeGzipped(): void
    {
        $this->client->request('POST', $this->url(), server: [
            'HTTP_X_SENTRY_AUTH' => 'Sentry sentry_key='.$this->project->getPublicKey().', sentry_version=7, sentry_client=sentry.python.django/2.18.0',
            'HTTP_CONTENT_ENCODING' => 'gzip',
            'CONTENT_TYPE' => 'application/x-sentry-envelope',
        ], content: (string) gzencode($this->fixture('python')));

        self::assertResponseIsSuccessful();
        $this->processQueued();

        $issue = $this->issues()[0];
        self::assertSame('PaymentError: Charging card [card] failed', $issue->getTitle());
        self::assertSame('/invoices/{id}/pay', $issue->getCulprit());

        $event = $this->events()[0];
        self::assertSame('staging', $event->getEnvironment());
        self::assertSame(['id' => '42', 'email' => 'customer@example.com'], $event->getUser());
        self::assertSame(['sys.argv' => ['manage.py', 'runserver'], 'secret_setting' => Scrubber::FILTERED], $event->getExtra());
        $request = $event->getRequest() ?? [];
        self::assertSame('token=[filtered]&page=2', $request['query_string'] ?? null);
        self::assertSame(['iban' => '[iban]', 'note' => 'ok'], $request['data'] ?? null);
        self::assertSame(['Content-Type' => 'application/json', 'X-Forwarded-For' => '203.0.113.9'], $request['headers'] ?? null);
        $vars = $event->getException()[1]['frames'][1]['vars'] ?? [];
        self::assertSame(Scrubber::FILTERED, \is_array($vars) ? ($vars['password'] ?? null) : null);
        self::assertSame(Scrubber::FILTERED, \is_array($vars) ? ($vars['api_key'] ?? null) : null);
    }

    public function testPlainPythonEnvelopeIsAcceptedToo(): void
    {
        $this->client->request('POST', $this->url(), server: [
            'HTTP_X_SENTRY_AUTH' => 'Sentry sentry_key='.$this->project->getPublicKey().', sentry_version=7',
        ], content: $this->fixture('python'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->transport()->getSent());
    }

    private function chargeCard(string $card): never
    {
        throw new \RuntimeException(\sprintf('Charging card %s failed', $card));
    }

    private function phpSdk(bool $compress): \Sentry\ClientInterface
    {
        return ClientBuilder::create([
            'dsn' => 'http://'.$this->project->getPublicKey().'@localhost/'.self::projectId($this->project),
            'environment' => 'contract',
            'release' => 'shop@1.0.0',
            'default_integrations' => false,
            'http_compression' => $compress,
            'in_app_include' => [\dirname(__DIR__, 2)],
        ])->setHttpClient(new KernelSentryHttpClient($this->client))->getClient();
    }

    private function url(string $query = ''): string
    {
        return '/api/'.self::projectId($this->project).'/envelope/'.$query;
    }

    private function fixture(string $name): string
    {
        return str_replace('__KEY__', $this->project->getPublicKey(), (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/sentry/'.$name.'.envelope'));
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function processQueued(): void
    {
        $handler = self::getContainer()->get(ProcessEventHandler::class);

        foreach ($this->transport()->getSent() as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(ProcessEvent::class, $message);
            $handler($message);
        }
    }

    /**
     * @return list<Issue>
     */
    private function issues(): array
    {
        return $this->entityManager()->getRepository(Issue::class)->findBy(['project' => $this->project]);
    }

    /**
     * @return list<Event>
     */
    private function events(): array
    {
        return $this->entityManager()->getRepository(Event::class)->findBy(['project' => $this->project], ['id' => 'ASC']);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
