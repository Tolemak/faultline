<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\EventId;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    public function testNormalizesUuids(): void
    {
        self::assertSame('9ec79c33ec9942ab8353589fcb2e04dc', EventId::normalize('9EC79C33-EC99-42AB-8353-589FCB2E04DC'));
        self::assertNull(EventId::normalize('not-an-id'));
        self::assertNull(EventId::normalize(123));
    }

    public function testGeneratesValidIds(): void
    {
        $id = EventId::generate();

        self::assertSame($id, EventId::normalize($id));
        self::assertNotSame($id, EventId::generate());
    }
}
