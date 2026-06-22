<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

abstract readonly class RelayMessage extends Message
{
    final public function toJson(): string
    {
        $preSerialised = $this instanceof PreSerialisedMessageInterface ? $this->preSerialisedJson() : null;

        return $preSerialised ?? self::encode($this->toArray());
    }

    final public static function fromJson(string $json): ?static
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data || [] === $data) {
            return null;
        }

        return static::fromArray($data);
    }
}
