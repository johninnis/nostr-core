<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use InvalidArgumentException;

final class HexCodec
{
    private function __construct()
    {
    }

    /**
     * @param positive-int $byteLength
     *
     * @return non-empty-string|null
     */
    public static function tryCanonical(string $hex, int $byteLength): ?string
    {
        if (strlen($hex) !== $byteLength * 2) {
            return null;
        }

        if (1 !== preg_match('/^[0-9a-f]+$/D', $hex)) {
            return null;
        }

        return $hex;
    }

    /**
     * @return ($hex is non-empty-string ? non-empty-string : string)
     */
    public static function decode(string $hex): string
    {
        $bytes = hex2bin($hex);
        if (false === $bytes) {
            throw new InvalidArgumentException('Hexadecimal string must contain an even number of valid hex digits');
        }

        return $bytes;
    }

    /**
     * @return ($bytes is non-empty-string ? non-empty-string : string)
     */
    public static function encode(string $bytes): string
    {
        return bin2hex($bytes);
    }
}
