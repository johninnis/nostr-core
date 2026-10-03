<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

abstract readonly class Message
{
    abstract public static function type(): ClientMessageType|RelayMessageType;

    /**
     * @return list<mixed>
     */
    abstract protected function toPayload(): array;

    /**
     * @param list<mixed> $payload
     */
    abstract protected static function tryFromPayload(array $payload): ?static;

    /**
     * @return list<mixed>
     */
    final public function toArray(): array
    {
        return [static::type()->value, ...$this->toPayload()];
    }

    final public function toJson(): string
    {
        return JsonWireFormat::encode($this->toArray(), JsonWireFormat::MESSAGE);
    }

    /**
     * @return class-string<self>|null
     */
    abstract protected static function messageClassFor(string $tag): ?string;

    final protected static function tryFromTaggedList(mixed $data): ?static
    {
        if (!is_array($data) || !array_is_list($data) || !is_string($data[0] ?? null)) {
            return null;
        }

        $messageClass = static::messageClassFor($data[0]);

        if (null === $messageClass || !is_a($messageClass, static::class, true)) {
            return null;
        }

        return $messageClass::tryFromPayload(array_slice($data, 1));
    }
}
