<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

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
}
