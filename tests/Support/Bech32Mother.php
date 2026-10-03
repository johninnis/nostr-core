<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Enum\Bech32Variant;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use RuntimeException;

final class Bech32Mother
{
    /**
     * @return non-empty-string
     */
    public static function encode(string $hrp, string $bytes, Bech32Variant $variant = Bech32Variant::Bech32): string
    {
        return Bech32Codec::encode($hrp, $bytes, $variant) ?? throw new RuntimeException('Test payload does not fit a bech32 string');
    }
}
