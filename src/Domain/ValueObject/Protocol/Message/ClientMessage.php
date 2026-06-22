<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

abstract readonly class ClientMessage extends Message
{
    final public function toJson(): string
    {
        return self::encode($this->toArray());
    }
}
