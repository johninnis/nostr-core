<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

abstract readonly class RelayMessage extends Message
{
    final public function toJson(): string
    {
        $preSerialised = $this instanceof PreSerialisedMessageInterface ? $this->preSerialisedJson() : null;

        return $preSerialised ?? self::encode($this->toArray());
    }
}
