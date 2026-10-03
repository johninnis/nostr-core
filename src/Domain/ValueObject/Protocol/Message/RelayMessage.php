<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Override;

abstract readonly class RelayMessage extends Message
{
    #[Override]
    abstract public static function type(): RelayMessageType;

    final public static function tryFromArray(mixed $data): ?static
    {
        return static::tryFromTaggedList($data);
    }

    final public static function tryFromJson(string $json): ?static
    {
        return static::tryFromTaggedList(JsonWireFormat::decode($json));
    }

    #[Override]
    final protected static function messageClassFor(string $tag): ?string
    {
        return match (RelayMessageType::tryFrom($tag)) {
            RelayMessageType::Event => EventMessage::class,
            RelayMessageType::Ok => OkMessage::class,
            RelayMessageType::Eose => EoseMessage::class,
            RelayMessageType::Closed => ClosedMessage::class,
            RelayMessageType::Notice => NoticeMessage::class,
            RelayMessageType::Auth => AuthMessage::class,
            RelayMessageType::Count => CountMessage::class,
            null => null,
        };
    }
}
