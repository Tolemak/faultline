<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    public function testRejectsUnknownEnvironment(): void
    {
        $kernel = new Kernel('staging', false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The environment "staging" is not registered as allowed');

        $kernel->boot();
    }
}
