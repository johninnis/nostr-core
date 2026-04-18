<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use InvalidArgumentException;

final class NativeRandomBytesGeneratorAdapter implements RandomBytesGeneratorInterface
{
    public function bytes(int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Random byte length must be positive');
        }

        return random_bytes($length);
    }
}
