<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;

abstract readonly class RelayMessage extends Message
{
    abstract public function type(): RelayMessageType;

    final public function toJson(): string
    {
        $preSerialised = $this instanceof PreSerialisedMessageInterface ? $this->preSerialisedJson() : null;

        return $preSerialised ?? self::encode($this->toArray());
    }
}
