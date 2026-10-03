<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\AuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Override;

abstract readonly class ClientMessage extends Message
{
    #[Override]
    abstract public static function type(): ClientMessageType;

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
        return match (ClientMessageType::tryFrom($tag)) {
            ClientMessageType::Event => EventMessage::class,
            ClientMessageType::Req => ReqMessage::class,
            ClientMessageType::Close => CloseMessage::class,
            ClientMessageType::Auth => AuthMessage::class,
            ClientMessageType::Count => CountMessage::class,
            null => null,
        };
    }
}
