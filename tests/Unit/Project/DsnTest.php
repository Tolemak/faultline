<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project;

use App\Entity\Project;
use App\Project\Dsn;
use PHPUnit\Framework\TestCase;

final class DsnTest extends TestCase
{
    public function testBuildsDsnsFromTheBaseUri(): void
    {
        $project = new Project('Shop', 'shop', str_repeat('a', 32), new \DateTimeImmutable());
        $key = str_repeat('a', 32);

        self::assertSame("https://{$key}@errors.example.com/0", (new Dsn('https://errors.example.com'))->for($project));
        self::assertSame("http://{$key}@localhost:8000/tracker/0", (new Dsn('http://localhost:8000/tracker/'))->for($project));
        self::assertSame("https://{$key}@localhost/0", (new Dsn(''))->for($project));
    }
}
