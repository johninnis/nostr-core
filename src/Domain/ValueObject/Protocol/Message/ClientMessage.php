<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;

abstract readonly class ClientMessage extends Message
{
    abstract public function type(): ClientMessageType;

    final public function toJson(): string
    {
        return self::encode($this->toArray());
    }
}
