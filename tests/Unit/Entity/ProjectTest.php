<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    public function testManagesOriginsAndSettings(): void
    {
        $project = new Project('Shop', 'shop', str_repeat('a', 32), new \DateTimeImmutable('2026-01-01'));
        $project->setAllowedOrigins(['https://shop.example.com', 'https://shop.example.com']);

        self::assertSame(['https://shop.example.com'], $project->getAllowedOrigins());
        self::assertTrue($project->allowsOrigin('https://shop.example.com'));
        self::assertFalse($project->allowsOrigin('https://evil.example.com'));

        $project->setAllowedOrigins(['*']);
        self::assertTrue($project->allowsOrigin('https://any.example.com'));

        $project->rename('Store');
        $project->setRetentionDays(7);
        $project->rotateKey(str_repeat('b', 32));

        self::assertSame('Store', $project->getName());
        self::assertSame('shop', $project->getSlug());
        self::assertSame(7, $project->getRetentionDays());
        self::assertSame(str_repeat('b', 32), $project->getPublicKey());
        self::assertEquals(new \DateTimeImmutable('2026-01-01'), $project->getCreatedAt());
    }
}
