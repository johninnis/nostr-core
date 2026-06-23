<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use InvalidArgumentException;

final class HexCodec
{
    private function __construct()
    {
    }

    public static function isValid(string $hex, int $byteLength): bool
    {
        return 1 === preg_match('/^[0-9a-f]{'.($byteLength * 2).'}$/', $hex);
    }

    public static function toBytes(string $hex): string
    {
        $bytes = hex2bin($hex);
        if (false === $bytes) {
            throw new InvalidArgumentException('Hexadecimal string must contain an even number of valid hex digits');
        }

        return $bytes;
    }

    public static function fromBytes(string $bytes): string
    {
        return bin2hex($bytes);
    }
}
