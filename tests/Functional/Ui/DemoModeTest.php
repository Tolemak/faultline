<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Demo\DemoMode;
use App\Repository\ProjectRepository;
use App\Tests\Support\AdminFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoModeTest extends WebTestCase
{
    use AdminFixtures;

    protected function tearDown(): void
    {
        unset($_SERVER['FAULTLINE_DEMO'], $_ENV['FAULTLINE_DEMO']);
        parent::tearDown();
    }

    public function testDemoLoginIsHiddenOutsideDemoMode(): void
    {
        $client = static::createClient();
        self::createAdmin(self::getContainer(), DemoMode::USERNAME);

        $client->request('GET', '/demo');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/login');
        self::assertSelectorNotExists('a[href="/demo"]');
        self::assertSelectorNotExists('.flash--demo');
    }

    public function testVisitorsEnterWithoutAPasswordAndCannotWrite(): void
    {
        $client = $this->demoClient();

        $client->request('GET', '/login');
        self::assertSelectorExists('a[href="/demo"]');
        self::assertSelectorNotExists('input[name="_password"]');

        $client->request('GET', '/demo');
        self::assertResponseRedirects('/');
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash--demo');
        self::assertSelectorExists('form[action="/projects"] button[disabled]');

        $client->request('POST', '/projects', ['name' => 'Spam'], server: ['HTTP_REFERER' => 'http://localhost/?page=2']);
        self::assertResponseRedirects('/?page=2', 303);
        self::assertNull(self::getContainer()->get(ProjectRepository::class)->findOneBySlug('spam'));
        $client->followRedirect();
        self::assertSelectorTextContains('.flash--error', 'The demo is read-only.');

        $client->request('POST', '/projects', ['name' => 'Spam'], server: ['HTTP_REFERER' => 'https://elsewhere.test/']);
        self::assertResponseRedirects('/', 303);
    }

    public function testDemoInstancesRejectIngestAndDigest(): void
    {
        $client = $this->demoClient();

        $client->request('POST', '/api/1/envelope/');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/api/digest');
        self::assertResponseStatusCodeSame(404);
    }

    public function testLogoutStillWorks(): void
    {
        $client = $this->demoClient();
        $client->request('GET', '/demo');
        $client->request('GET', '/');

        $client->submitForm('Log out');

        self::assertResponseRedirects('/login');
    }

    private function demoClient(): KernelBrowser
    {
        $_SERVER['FAULTLINE_DEMO'] = $_ENV['FAULTLINE_DEMO'] = '1';
        $client = static::createClient();
        self::createAdmin(self::getContainer(), DemoMode::USERNAME);

        return $client;
    }
}
