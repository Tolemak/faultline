<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\CspNonce;
use PHPUnit\Framework\TestCase;

final class CspNonceTest extends TestCase
{
    public function testIsStableUntilReset(): void
    {
        $nonce = new CspNonce();
        self::assertFalse($nonce->used());

        $value = $nonce->get();
        self::assertTrue($nonce->used());
        self::assertSame($value, $nonce->get());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9+\/]{24}$/', $value);

        $nonce->reset();
        self::assertFalse($nonce->used());
        self::assertNotSame($value, $nonce->get());
    }
}
