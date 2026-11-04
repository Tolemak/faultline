<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnonymousRateLimitTest extends WebTestCase
{
    public function testLimitsAnonymousWebTrafficPerIp(): void
    {
        $client = self::freshClient();

        for ($i = 0; $i < 120; ++$i) {
            $client->request('GET', '/login', server: ['REMOTE_ADDR' => '192.0.2.70']);
            self::assertLessThan(429, $client->getResponse()->getStatusCode());
        }

        $client->request('GET', '/login', server: ['REMOTE_ADDR' => '192.0.2.70']);
        self::assertResponseStatusCodeSame(429);
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', (string) $client->getResponse()->headers->get('Retry-After'));

        $client->request('GET', '/login', server: ['REMOTE_ADDR' => '192.0.2.71']);
        self::assertLessThan(429, $client->getResponse()->getStatusCode());
    }

    public function testDoesNotLimitHealth(): void
    {
        $client = self::freshClient();

        for ($i = 0; $i < 140; ++$i) {
            $client->request('GET', '/health', server: ['REMOTE_ADDR' => '192.0.2.72']);
            self::assertResponseIsSuccessful();
        }
    }

    private static function freshClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();

        self::getContainer()->get('cache.global_clearer')->clearPool('cache.anonymous_limiter');

        return $client;
    }
}
