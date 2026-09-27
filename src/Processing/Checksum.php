<?php

declare(strict_types=1);

namespace App\Processing;

final class Checksum
{
    public static function luhn(string $candidate): bool
    {
        $digits = preg_replace('/\D/', '', $candidate) ?? '';
        $length = \strlen($digits);
        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < $length; ++$i) {
            $digit = (int) $digits[$length - 1 - $i];
            if (1 === $i % 2) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return 0 === $sum % 10;
    }

    public static function iban(string $candidate): bool
    {
        $iban = strtoupper(str_replace(' ', '', $candidate));
        if (1 !== preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $char) {
            $value = ctype_digit($char) ? $char : (string) (\ord($char) - 55);
            foreach (str_split($value) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return 1 === $remainder;
    }
}
