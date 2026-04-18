<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

interface RandomBytesGeneratorInterface
{
    public function bytes(int $length): string;
}
