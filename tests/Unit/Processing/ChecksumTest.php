<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processing;

use App\Processing\Checksum;
use PHPUnit\Framework\TestCase;

final class ChecksumTest extends TestCase
{
    public function testLuhn(): void
    {
        self::assertTrue(Checksum::luhn('4111 1111 1111 1111'));
        self::assertTrue(Checksum::luhn('378282246310005'));
        self::assertFalse(Checksum::luhn('4111 1111 1111 1112'));
        self::assertFalse(Checksum::luhn('123456789012'));
        self::assertFalse(Checksum::luhn(str_repeat('4', 20)));
    }

    public function testIban(): void
    {
        self::assertTrue(Checksum::iban('DE89 3704 0044 0532 0130 00'));
        self::assertTrue(Checksum::iban('pl61109010140000071219812874'));
        self::assertFalse(Checksum::iban('DE00370400440532013000'));
        self::assertFalse(Checksum::iban('not an iban'));
    }
}
