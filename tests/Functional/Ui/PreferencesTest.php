<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Tests\Support\AdminFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class PreferencesTest extends WebTestCase
{
    use AdminFixtures;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRendersPolishFromTheLanguageCookie(): void
    {
        $this->client->getCookieJar()->set(new Cookie('faultline_lang', 'pl'));
        $this->client->loginUser(self::createAdmin(self::getContainer()));

        $this->client->request('GET', '/');

        self::assertSelectorTextContains('h1', 'Projekty');
        self::assertSelectorTextContains('.masthead__logout', 'Wyloguj');
        self::assertSame('pl', $this->client->getCrawler()->filter('html')->attr('lang'));
    }

    public function testFallsBackToTheAcceptLanguageHeader(): void
    {
        $this->client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'pl-PL,pl;q=0.9,en;q=0.5']);
        self::assertSelectorTextContains('h1', 'Logowanie');

        $this->client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertSelectorTextContains('h1', 'Sign in');
    }

    public function testIgnoresUnknownLanguageCookies(): void
    {
        $this->client->getCookieJar()->set(new Cookie('faultline_lang', 'xx'));

        $this->client->request('GET', '/login');

        self::assertSelectorTextContains('h1', 'Sign in');
    }

    public function testRendersTheThemeFromTheCookie(): void
    {
        $this->client->getCookieJar()->set(new Cookie('faultline_theme', 'light'));
        $this->client->request('GET', '/login');
        self::assertSame('light', $this->client->getCrawler()->filter('html')->attr('data-theme'));

        $this->client->getCookieJar()->set(new Cookie('faultline_theme', 'neon'));
        $this->client->request('GET', '/login');
        self::assertNull($this->client->getCrawler()->filter('html')->attr('data-theme'));
    }

    public function testRendersTheSharedBar(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $bar = $crawler->filter('tolemak-bar');
        self::assertSame('pl,en', $bar->attr('langs'));
        self::assertSame('faultline-theme', $bar->attr('theme-key'));
    }
}
