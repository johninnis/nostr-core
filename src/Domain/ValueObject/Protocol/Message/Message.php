<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

abstract readonly class Message
{
    /**
     * @return array<array-key, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @param array<array-key, mixed> $data
     */
    abstract public static function tryFromArray(array $data): ?static;

    final public static function tryFromJson(string $json): ?static
    {
        $data = JsonWireFormat::decodeList($json);

        return null === $data ? null : static::tryFromArray($data);
    }

    final protected static function encode(mixed $value): string
    {
        return JsonWireFormat::encode($value, JsonWireFormat::MESSAGE);
    }
}
