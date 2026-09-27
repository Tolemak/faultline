<?php

declare(strict_types=1);

namespace App\Project;

final class OriginList
{
    private const string ORIGIN = '~^https?://[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$~i';

    /**
     * @param iterable<mixed> $values
     *
     * @return list<string>
     */
    public static function parse(iterable $values): array
    {
        $origins = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('Origin must be a string.');
            }

            $origin = strtolower(rtrim(trim($value), '/'));
            if ('' === $origin) {
                continue;
            }
            if ('*' !== $origin && 1 !== preg_match(self::ORIGIN, $origin)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a valid origin (scheme://host[:port] or *).', $value));
            }

            $origins[] = $origin;
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return list<string>
     */
    public static function fromText(string $text): array
    {
        return self::parse(preg_split('/[\s,]+/', $text) ?: []);
    }
}
