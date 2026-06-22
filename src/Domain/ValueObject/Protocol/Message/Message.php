<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

abstract readonly class Message
{
    protected const string TYPE = '';

    abstract public function toArray(): array;

    abstract public static function fromArray(array $data): ?static;

    final public function getType(): string
    {
        return static::TYPE;
    }

    final public static function fromJson(string $json): ?static
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data || [] === $data) {
            return null;
        }

        return static::fromArray($data);
    }

    final protected static function encode(mixed $value): string
    {
        return json_encode($value, JsonWireFormat::MESSAGE);
    }
}
