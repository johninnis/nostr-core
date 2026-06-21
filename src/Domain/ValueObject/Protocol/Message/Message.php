<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

abstract readonly class Message
{
    abstract public function getType(): string;

    abstract public function toArray(): array;

    abstract public static function fromArray(array $data): ?static;

    final protected static function encode(mixed $value): string
    {
        return json_encode($value, JsonWireFormat::MESSAGE);
    }
}
