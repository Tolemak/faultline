<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runtime;

use App\Runtime\EnvFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class EnvFilesTest extends TestCase
{
    private const array VARIABLES = ['APP_ENV', 'FAULTLINE_A', 'FAULTLINE_B', 'FAULTLINE_C', 'SYMFONY_DOTENV_VARS', 'SYMFONY_DOTENV_PATH'];

    private string $directory;

    /**
     * @var array<string, array{mixed, mixed}>
     */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/faultline-env-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->directory);

        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null];
            unset($_SERVER[$name], $_ENV[$name]);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);

        foreach ($this->saved as $name => [$server, $env]) {
            unset($_SERVER[$name], $_ENV[$name]);
            if (null !== $server) {
                $_SERVER[$name] = $server;
            }
            if (null !== $env) {
                $_ENV[$name] = $env;
            }
        }
    }

    public function testWorksWithoutAnyFile(): void
    {
        EnvFiles::load($this->directory);

        self::assertArrayNotHasKey('FAULTLINE_A', $_SERVER);
    }

    public function testLayersFilesInSymfonyOrder(): void
    {
        $this->write('.env', "FAULTLINE_A=base\nFAULTLINE_B=base\nFAULTLINE_C=base\n");
        $this->write('.env.local', "APP_ENV=staging\nFAULTLINE_B=local\n");
        $this->write('.env.staging', "FAULTLINE_C=staging\n");
        $this->write('.env.staging.local', "FAULTLINE_A=staging-local\n");

        EnvFiles::load($this->directory);

        self::assertSame('staging', $_SERVER['APP_ENV']);
        self::assertSame('staging-local', $_SERVER['FAULTLINE_A']);
        self::assertSame('local', $_SERVER['FAULTLINE_B']);
        self::assertSame('staging', $_SERVER['FAULTLINE_C']);
    }

    public function testSkipsTheLocalFileForTests(): void
    {
        $_SERVER['APP_ENV'] = 'test';
        $this->write('.env.local', "FAULTLINE_A=local\n");
        $this->write('.env.test', "FAULTLINE_A=test\nFAULTLINE_B=test\n");

        EnvFiles::load($this->directory);

        self::assertSame('test', $_SERVER['FAULTLINE_A']);
        self::assertSame('test', $_SERVER['FAULTLINE_B']);
    }

    public function testKeepsRealEnvironmentVariables(): void
    {
        $_SERVER['FAULTLINE_A'] = 'real';
        $this->write('.env', "FAULTLINE_A=file\n");

        EnvFiles::load($this->directory);

        self::assertSame('real', $_SERVER['FAULTLINE_A']);
    }

    public function testIgnoresUnsafeEnvironmentNames(): void
    {
        $_SERVER['APP_ENV'] = '../escape';
        $this->write('.env.prod', "FAULTLINE_A=prod\n");

        EnvFiles::load($this->directory, 'prod');

        self::assertSame('prod', $_SERVER['FAULTLINE_A']);
    }

    private function write(string $name, string $content): void
    {
        file_put_contents($this->directory.'/'.$name, $content);
    }
}
