<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class HexCodec
{
    public static function isValid(string $hex, int $byteLength): bool
    {
        return 1 === preg_match('/^[0-9a-f]{'.($byteLength * 2).'}$/', $hex);
    }

    public static function toBytes(string $hex): string
    {
        $bytes = hex2bin($hex);
        assert(false !== $bytes);

        return $bytes;
    }

    public static function fromBytes(string $bytes): string
    {
        return bin2hex($bytes);
    }
}
