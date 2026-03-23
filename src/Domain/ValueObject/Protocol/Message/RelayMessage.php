<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

abstract readonly class RelayMessage
{
    abstract public function getType(): string;
    abstract public function toArray(): array;
    abstract public static function fromArray(array $data): static;

    final public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    final public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (!\is_array($data) || empty($data)) {
            throw new \InvalidArgumentException('Invalid JSON message format');
        }

        return static::fromArray($data);
    }
}
