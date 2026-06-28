<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

// Deliberate: an abstract base with final leaves expresses a closed sum type PHP cannot name, sharing the self-typed fromJson — see ADR-0016
abstract readonly class Message
{
    protected const string TYPE = '';

    /**
     * @return array<array-key, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @param array<array-key, mixed> $data
     */
    abstract public static function fromArray(array $data): ?static;

    final public function getType(): string
    {
        return static::TYPE;
    }

    final public static function fromJson(string $json): ?static
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data || [] === $data || !array_is_list($data)) {
            return null;
        }

        return static::fromArray($data);
    }

    final protected static function encode(mixed $value): string
    {
        return json_encode($value, JsonWireFormat::MESSAGE);
    }
}
