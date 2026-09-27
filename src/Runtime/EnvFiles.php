<?php

declare(strict_types=1);

namespace App\Runtime;

use Symfony\Component\Dotenv\Dotenv;

final class EnvFiles
{
    public const array TEST_ENVS = ['test'];

    public static function load(string $projectDir, string $defaultEnv = 'dev'): void
    {
        $dotenv = new Dotenv();

        self::loadIfExists($dotenv, $projectDir.'/.env');
        $env = self::env($defaultEnv);

        if (!\in_array($env, self::TEST_ENVS, true)) {
            self::loadIfExists($dotenv, $projectDir.'/.env.local');
            $env = self::env($defaultEnv);
        }

        self::loadIfExists($dotenv, $projectDir.'/.env.'.$env);
        self::loadIfExists($dotenv, $projectDir.'/.env.'.$env.'.local');
    }

    private static function env(string $default): string
    {
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;

        return \is_string($env) && 1 === preg_match('/^[a-z0-9_]+$/', $env) ? $env : $default;
    }

    private static function loadIfExists(Dotenv $dotenv, string $path): void
    {
        if (is_file($path)) {
            $dotenv->load($path);
        }
    }
}
