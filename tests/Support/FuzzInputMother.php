<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

final class FuzzInputMother
{
    private const string BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    private const int MAX_ARRAY_DEPTH = 3;

    private function __construct()
    {
    }

    public static function hostileString(): string
    {
        return match (random_int(0, 4)) {
            0 => self::randomBytes(256),
            1 => bin2hex(self::randomBytes(96)),
            2 => self::bech32Shaped(),
            3 => self::asciiNoise(random_int(0, 256)),
            default => '',
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function hostileArray(): array
    {
        return self::randomArray(0);
    }

    private static function bech32Shaped(): string
    {
        $body = '';
        for ($i = 0, $length = random_int(0, 120); $i < $length; ++$i) {
            $body .= self::BECH32_CHARSET[random_int(0, 31)];
        }

        return self::asciiLower(random_int(1, 8)).'1'.$body;
    }

    private static function asciiNoise(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; ++$i) {
            $out .= chr(random_int(33, 126));
        }

        return $out;
    }

    private static function asciiLower(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; ++$i) {
            $out .= chr(random_int(97, 122));
        }

        return $out;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function randomArray(int $depth): array
    {
        $out = [];
        for ($i = 0, $count = random_int(0, 6); $i < $count; ++$i) {
            $key = 1 === random_int(0, 1) ? self::asciiNoise(random_int(1, 8)) : $i;
            $out[$key] = self::randomValue($depth);
        }

        return $out;
    }

    private static function randomValue(int $depth): mixed
    {
        $ceiling = $depth >= self::MAX_ARRAY_DEPTH ? 4 : 5;

        return match (random_int(0, $ceiling)) {
            0 => random_int(-1_000_000, 1_000_000),
            1 => self::asciiNoise(random_int(0, 64)),
            2 => 1 === random_int(0, 1),
            3 => null,
            4 => self::randomBytes(16),
            default => self::randomArray($depth + 1),
        };
    }

    private static function randomBytes(int $maxLength): string
    {
        $length = random_int(0, $maxLength);

        return 0 === $length ? '' : random_bytes($length);
    }
}
