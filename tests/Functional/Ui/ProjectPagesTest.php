<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Tests\Support\AdminFixtures;
use App\Tests\Support\EventSeeder;
use App\Tests\Support\ProjectFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectPagesTest extends WebTestCase
{
    use AdminFixtures;
    use EventSeeder;
    use ProjectFixtures;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->loginUser(self::createAdmin(self::getContainer()));
    }

    public function testShowsAnEmptyState(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty', 'No projects yet');
    }

    public function testListsProjectsWithReadings(): void
    {
        $project = self::createProject(self::getContainer(), 'Checkout');
        self::seedEvent(self::getContainer(), $project, ['message' => 'Queue is full']);
        self::seedEvent(self::getContainer(), $project, ['message' => 'Disk is full']);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.station__name', 'Checkout');
        self::assertSame(['2', '2'], $crawler->filter('.station .readings dd')->each(static fn ($node): string => $node->text()));
        self::assertCount(1, $crawler->filter('.station .trace polyline'));
        self::assertSelectorTextContains('tolemak-field', '1');
    }

    public function testCreatesProjectsFromTheList(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Create', ['name' => 'Home Dashboard']);

        self::assertResponseRedirects('/projects/home-dashboard/settings');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'Project created');
        self::assertInputValueSame('name', 'Home Dashboard');
    }

    public function testRejectsInvalidProjectNames(): void
    {
        $this->client->request('GET', '/');
        $this->client->submitForm('Create', ['name' => '   ']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--error', 'must be 1 to 100 characters');
    }

    public function testRejectsForgedCreateTokens(): void
    {
        $this->client->request('POST', '/projects', ['name' => 'X', '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testShowsSettingsWithDsnAndSnippets(): void
    {
        $project = self::createProject(self::getContainer(), 'Shop');

        $crawler = $this->client->request('GET', '/projects/shop/settings');

        self::assertResponseIsSuccessful();
        $dsn = 'http://'.$project->getPublicKey().'@localhost/'.self::projectId($project);
        self::assertSame($dsn, $crawler->filter('.copy__value')->attr('value'));
        self::assertCount(4, $crawler->filter('.snippets details'));
        self::assertStringContainsString($dsn, $crawler->filter('.snippets')->text());
    }

    public function testSavesSettings(): void
    {
        self::createProject(self::getContainer(), 'Shop');

        $this->client->request('GET', '/projects/shop/settings');
        $this->client->submitForm('Save', [
            'name' => 'Shop API',
            'origins' => "https://shop.example.com\nhttps://admin.example.com/",
            'retention' => '14',
        ]);

        self::assertResponseRedirects('/projects/shop/settings');
        $project = $this->project('shop');
        self::assertSame('Shop API', $project->getName());
        self::assertSame(['https://shop.example.com', 'https://admin.example.com'], $project->getAllowedOrigins());
        self::assertSame(14, $project->getRetentionDays());
    }

    public function testReportsInvalidSettings(): void
    {
        self::createProject(self::getContainer(), 'Shop');

        $this->client->request('GET', '/projects/shop/settings');
        $this->client->submitForm('Save', ['name' => '', 'origins' => 'ftp://nope', 'retention' => '999']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorCount(3, '.field__error');
        self::assertSame('Shop', $this->project('shop')->getName());
    }

    public function testRejectsForgedSettingsTokens(): void
    {
        self::createProject(self::getContainer(), 'Shop');

        $this->client->request('POST', '/projects/shop/settings', ['name' => 'X', '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRotatesTheKey(): void
    {
        $oldKey = self::createProject(self::getContainer(), 'Shop')->getPublicKey();

        $this->client->request('GET', '/projects/shop/settings');
        $this->client->submitForm('Rotate key');

        self::assertResponseRedirects('/projects/shop/settings');
        self::assertNotSame($oldKey, $this->project('shop')->getPublicKey());
    }

    public function testRejectsForgedRotateTokens(): void
    {
        $key = self::createProject(self::getContainer(), 'Shop')->getPublicKey();

        $this->client->request('POST', '/projects/shop/rotate-key', ['_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame($key, $this->project('shop')->getPublicKey());
    }

    public function testUnknownProjectsAreNotFound(): void
    {
        $this->client->request('GET', '/projects/missing/settings');

        self::assertResponseStatusCodeSame(404);
    }

    private function project(string $slug): Project
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ProjectRepository::class)->findOneBySlug($slug) ?? throw new \LogicException('Project not found.');
    }
}
