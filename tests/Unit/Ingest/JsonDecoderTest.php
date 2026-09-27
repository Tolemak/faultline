<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\IngestException;
use App\Ingest\JsonDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonDecoderTest extends TestCase
{
    public function testDecodesObjects(): void
    {
        $decoder = new JsonDecoder(8);

        self::assertSame(['a' => 1, 'b' => ['c' => true]], $decoder->decodeObject('{"a":1,"b":{"c":true}}'));
        self::assertSame([], $decoder->decodeObject('{}'));
        self::assertSame(['1' => 'x'], $decoder->decodeObject('{"1":"x"}'));
    }

    public function testKeepsBigIntegersAsStrings(): void
    {
        self::assertSame(['n' => '123456789012345678901234567890'], (new JsonDecoder(8))->decodeObject('{"n":123456789012345678901234567890}'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'syntax' => ['{"a":'];
        yield 'list' => ['[1,2]'];
        yield 'scalar' => ['"text"'];
        yield 'too deep' => [str_repeat('{"a":', 9).'1'.str_repeat('}', 9)];
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsInvalidDocuments(string $json): void
    {
        $this->expectException(IngestException::class);

        (new JsonDecoder(8))->decodeObject($json);
    }
}
