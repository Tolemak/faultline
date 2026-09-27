<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\SentryKeyExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SentryKeyExtractorTest extends TestCase
{
    private const string KEY = '0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a';

    public function testReadsTheAuthHeader(): void
    {
        $request = new Request();
        $request->headers->set('X-Sentry-Auth', 'Sentry sentry_version=7, sentry_client=sentry.php/4.0, sentry_key='.strtoupper(self::KEY));

        self::assertSame(self::KEY, (new SentryKeyExtractor())->fromRequest($request));
    }

    public function testReadsTheQueryParameter(): void
    {
        $request = new Request(['sentry_key' => self::KEY, 'sentry_version' => '7']);

        self::assertSame(self::KEY, (new SentryKeyExtractor())->fromRequest($request));
    }

    public function testIgnoresMalformedKeys(): void
    {
        $extractor = new SentryKeyExtractor();
        $header = new Request();
        $header->headers->set('X-Sentry-Auth', 'Sentry sentry_key=short');

        self::assertNull($extractor->fromRequest($header));
        self::assertNull($extractor->fromRequest(new Request(['sentry_key' => ['array']])));
        self::assertNull($extractor->fromRequest(new Request()));
    }

    public function testReadsTheDsn(): void
    {
        $extractor = new SentryKeyExtractor();

        self::assertSame(self::KEY, $extractor->fromDsn('https://'.self::KEY.'@errors.example.com/4'));
        self::assertNull($extractor->fromDsn('https://errors.example.com/4'));
        self::assertNull($extractor->fromDsn(42));
    }
}
