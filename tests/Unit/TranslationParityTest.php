<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class TranslationParityTest extends TestCase
{
    public function testEveryKeyExistsInBothLanguages(): void
    {
        $en = $this->keys('en');
        $pl = $this->keys('pl');

        self::assertNotEmpty($en);
        self::assertSame([], array_values(array_diff($en, $pl)), 'Missing in Polish');
        self::assertSame([], array_values(array_diff($pl, $en)), 'Missing in English');
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $messages = Yaml::parseFile(\dirname(__DIR__, 2).'/translations/messages+intl-icu.'.$locale.'.yaml');
        self::assertIsArray($messages);

        return $this->flatten($messages);
    }

    /**
     * @param array<mixed> $messages
     *
     * @return list<string>
     */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $keys = [];
        foreach ($messages as $key => $value) {
            $path = $prefix.$key;
            if (\is_array($value)) {
                array_push($keys, ...$this->flatten($value, $path.'.'));
            } else {
                self::assertIsString($value, $path);
                self::assertNotSame('', trim($value), $path);
                $keys[] = $path;
            }
        }

        return $keys;
    }
}
