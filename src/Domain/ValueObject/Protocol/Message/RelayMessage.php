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

    final public static function fromJson(string $json): ?static
    {
        if (!json_validate($json)) {
            return null;
        }

        $data = json_decode($json, true);

        if (!is_array($data) || empty($data)) {
            return null;
        }

        return static::fromArray($data);
    }
}
