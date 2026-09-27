<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Project\OriginList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OriginListTest extends TestCase
{
    public function testNormalizesOrigins(): void
    {
        self::assertSame(
            ['https://shop.example.com', 'http://localhost:3000', '*'],
            OriginList::parse(['HTTPS://Shop.Example.com/', ' http://localhost:3000 ', '', '*', 'https://shop.example.com']),
        );
        self::assertSame(['https://a.example.com', 'https://b.example.com'], OriginList::fromText("https://a.example.com,\nhttps://b.example.com"));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidOrigins(): iterable
    {
        yield 'path' => ['https://shop.example.com/app'];
        yield 'scheme' => ['ftp://shop.example.com'];
        yield 'no scheme' => ['shop.example.com'];
        yield 'not a string' => [42];
    }

    #[DataProvider('invalidOrigins')]
    public function testRejectsInvalidOrigins(mixed $origin): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OriginList::parse([$origin]);
    }
}
