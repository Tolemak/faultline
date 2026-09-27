<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\BodyDecoder;
use App\Ingest\IngestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BodyDecoderTest extends TestCase
{
    private BodyDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new BodyDecoder(1024, 4096);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function plainEncodings(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'identity' => ['identity'];
        yield 'none' => ['None'];
    }

    #[DataProvider('plainEncodings')]
    public function testPassesPlainBodies(?string $encoding): void
    {
        self::assertSame('{"a":1}', $this->decoder->decode('{"a":1}', $encoding));
    }

    public function testInflatesGzip(): void
    {
        self::assertSame('hello', $this->decoder->decode((string) gzencode('hello'), 'gzip'));
        self::assertSame('hello', $this->decoder->decode((string) gzencode('hello'), 'X-Gzip'));
    }

    public function testInflatesZlibAndRawDeflate(): void
    {
        self::assertSame('hello', $this->decoder->decode((string) gzcompress('hello'), 'deflate'));
        self::assertSame('hello', $this->decoder->decode((string) gzdeflate('hello'), 'deflate'));
    }

    public function testRejectsOversizedWireBody(): void
    {
        $this->expectExceptionObject(IngestException::payloadTooLarge());

        $this->decoder->decode(str_repeat('a', 1025), null);
    }

    public function testRejectsOversizedPlainBodyAboveInflateLimit(): void
    {
        $decoder = new BodyDecoder(8192, 4096);

        $this->assertStatus(413, fn () => $decoder->decode(str_repeat('a', 5000), null));
    }

    public function testStopsInflateBombs(): void
    {
        $bomb = (string) gzencode(str_repeat("\0", 1_000_000), 9);
        self::assertLessThan(1024, \strlen($bomb));

        $this->assertStatus(413, fn () => $this->decoder->decode($bomb, 'gzip'));
    }

    public function testRejectsCorruptGzip(): void
    {
        $this->assertStatus(400, fn () => $this->decoder->decode('not gzip at all', 'gzip'));
    }

    public function testRejectsTruncatedGzip(): void
    {
        $compressed = (string) gzencode(str_repeat('abc', 200));

        $this->assertStatus(400, fn () => $this->decoder->decode(substr($compressed, 0, 20), 'gzip'));
    }

    public function testRejectsCorruptDeflate(): void
    {
        $this->assertStatus(400, fn () => $this->decoder->decode("\xff\xff\xff\xff", 'deflate'));
    }

    public function testRejectsUnsupportedEncoding(): void
    {
        $this->assertStatus(415, fn () => $this->decoder->decode('x', 'br'));
    }

    public function testChecksDeclaredLength(): void
    {
        $this->decoder->assertDeclaredLength(null);
        $this->decoder->assertDeclaredLength('1024');
        $this->decoder->assertDeclaredLength('garbage');

        $this->assertStatus(413, fn () => $this->decoder->assertDeclaredLength('1025'));
    }

    private function assertStatus(int $status, callable $callback): void
    {
        try {
            $callback();
        } catch (IngestException $e) {
            self::assertSame($status, $e->statusCode);

            return;
        }

        self::fail('Expected an IngestException.');
    }
}
