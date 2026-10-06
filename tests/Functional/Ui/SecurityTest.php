<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Tests\Support\AdminFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityTest extends WebTestCase
{
    use AdminFixtures;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAnonymousUsersAreRedirectedToLogin(): void
    {
        self::createAdmin(self::getContainer());

        foreach (['/', '/projects/any/issues', '/projects/any/settings', '/projects/any/issues/1'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/login', 302, $url);
        }

        $this->client->request('POST', '/projects');
        self::assertResponseRedirects('/login');
    }

    public function testHealthStaysPublic(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
    }

    public function testLoginPageHasStrictHeaders(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        $csp = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertMatchesRegularExpression("/style-src 'self' 'nonce-[A-Za-z0-9+\\/=]+';/", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000');

        $html = (string) $this->client->getResponse()->getContent();
        self::assertDoesNotMatchRegularExpression('/<style\b/i', $html);
        self::assertDoesNotMatchRegularExpression('/\sstyle=/i', $html);
        preg_match("/'nonce-([^']+)'/", $csp, $nonce);
        $scriptNonces = $this->client->getCrawler()->filter('script')->extract(['nonce']);
        self::assertNotEmpty($scriptNonces);
        self::assertSame(array_fill(0, \count($scriptNonces), $nonce[1] ?? null), $scriptNonces);
        self::assertStringNotContainsString('https://', implode('', $this->client->getCrawler()->filter('script[src], link[href]')->extract(['src', 'href'])));
    }

    public function testSuccessfulLoginAndLogout(): void
    {
        self::createAdmin(self::getContainer());

        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', ['_username' => 'admin', '_password' => self::ADMIN_PASSWORD]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Projects');

        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/');
        $this->client->submitForm('Log out');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testLogoutRequiresAValidToken(): void
    {
        $this->client->loginUser(self::createAdmin(self::getContainer()));

        $this->client->request('POST', '/logout', ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRejectsWrongPasswords(): void
    {
        self::createAdmin(self::getContainer(), 'wrong-password');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', ['_username' => 'wrong-password', '_password' => 'nope']);
        $this->client->followRedirect();

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash--error', 'Invalid credentials.');
        self::assertInputValueSame('_username', 'wrong-password');
    }

    public function testRejectsForgedLoginTokens(): void
    {
        self::createAdmin(self::getContainer(), 'forged');

        $this->client->request('POST', '/login', ['_username' => 'forged', '_password' => self::ADMIN_PASSWORD, '_csrf_token' => 'forged']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--error', 'Invalid CSRF token.');
    }

    public function testThrottlesRepeatedFailures(): void
    {
        $username = 'throttled-'.bin2hex(random_bytes(4));
        self::createAdmin(self::getContainer(), $username);

        for ($attempt = 0; $attempt < 6; ++$attempt) {
            $this->client->request('GET', '/login');
            $this->client->submitForm('Sign in', ['_username' => $username, '_password' => 'wrong']);
            $this->client->followRedirect();
        }

        self::assertSelectorTextContains('.flash--error', 'Too many failed login attempts');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', ['_username' => $username, '_password' => self::ADMIN_PASSWORD]);
        self::assertResponseRedirects('/login');
    }
}
