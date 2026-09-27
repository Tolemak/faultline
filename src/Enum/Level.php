<?php

declare(strict_types=1);

namespace App\Enum;

enum Level: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Fatal = 'fatal';

    public static function fromSentry(mixed $value): self
    {
        if (!\is_string($value)) {
            return self::Error;
        }

        return match (strtolower(trim($value))) {
            'debug' => self::Debug,
            'info', 'log' => self::Info,
            'warning', 'warn' => self::Warning,
            'fatal', 'critical' => self::Fatal,
            default => self::Error,
        };
    }

    public function magnitude(): int
    {
        return match ($this) {
            self::Debug => 1,
            self::Info => 2,
            self::Warning => 3,
            self::Error => 4,
            self::Fatal => 5,
        };
    }
}
