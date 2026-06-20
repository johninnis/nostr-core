<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use InvalidArgumentException;
use Override;

final class NativeRandomBytesGenerator implements RandomBytesGeneratorInterface
{
    #[Override]
    public function bytes(int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Random byte length must be positive');
        }

        return random_bytes($length);
    }
}
