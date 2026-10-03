<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

interface IdentityKeyedInterface
{
    public function identityKey(): int|string;
}
