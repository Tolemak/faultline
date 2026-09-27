<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Controller\Api\DigestController;
use App\Query\DigestQuery;
use App\Repository\IssueRepository;
use App\Tests\Support\EventSeeder;
use App\Tests\Support\ProjectFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class DigestControllerTest extends WebTestCase
{
    use EventSeeder;
    use ProjectFixtures;

    private const string TOKEN = 'test-digest-token-0123456789';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRequiresTheBearerToken(): void
    {
        $this->client->request('GET', '/api/digest');
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');

        $this->client->request('GET', '/api/digest', server: ['HTTP_AUTHORIZATION' => 'Bearer wrong']);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/digest', server: ['HTTP_AUTHORIZATION' => 'Basic '.self::TOKEN]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testIsDisabledWithoutAConfiguredToken(): void
    {
        $controller = new DigestController(self::getContainer()->get(DigestQuery::class), '');

        self::assertSame(404, $controller(new Request())->getStatusCode());
    }

    public function testSummarisesTheLastDay(): void
    {
        $container = self::getContainer();
        $project = self::createProject($container, 'Shop');
        self::seedEvent($container, $project, ['message' => 'Old and quiet'], new \DateTimeImmutable('-3 days'));
        self::seedEvent($container, $project, ['message' => 'Came back'], new \DateTimeImmutable('-3 days'));
        $issues = $container->get(IssueRepository::class);
        $back = $issues->findOneBy(['title' => 'Came back']) ?? throw new \LogicException();
        $back->resolve();
        $container->get(EntityManagerInterface::class)->flush();
        self::seedEvent($container, $project, ['message' => 'Came back'], new \DateTimeImmutable('-1 hour'));
        for ($i = 0; $i < 3; ++$i) {
            self::seedEvent($container, $project, ['message' => 'Fresh and loud', 'level' => 'fatal'], new \DateTimeImmutable('-30 minutes'));
        }

        $this->client->request('GET', '/api/digest', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $digest = json_decode((string) $this->client->getResponse()->getContent(), true, 16, \JSON_THROW_ON_ERROR);
        self::assertIsArray($digest);

        self::assertSame(24, $digest['window']['hours'] ?? null);
        self::assertSame(['events' => 4, 'open_issues' => 3], $digest['totals'] ?? null);
        self::assertSame(['Fresh and loud'], array_column($digest['new_issues'] ?? [], 'title'));
        self::assertSame(['Came back'], array_column($digest['regressions'] ?? [], 'title'));
        self::assertSame(['Fresh and loud', 'Came back'], array_column($digest['top_issues'] ?? [], 'title'));

        $top = $digest['top_issues'][0] ?? [];
        self::assertIsArray($top);
        self::assertSame(3, $top['events_in_window']);
        self::assertSame('fatal', $top['level']);
        self::assertSame(['slug' => 'shop', 'name' => 'Shop'], $top['project']);
        self::assertStringStartsWith('http://localhost/projects/shop/issues/', (string) $top['url']);
    }
}
