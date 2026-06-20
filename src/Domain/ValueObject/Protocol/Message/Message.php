<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

abstract readonly class Message
{
    public const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    abstract public function getType(): string;

    abstract public function toArray(): array;

    abstract public static function fromArray(array $data): ?static;

    final protected static function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }
}
