<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

abstract readonly class ClientMessage
{
    abstract public function getType(): string;

    abstract public function toArray(): array;

    abstract public static function fromArray(array $data): static;

    final public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
