<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Encoding;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\MessageDeserialiserInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\AuthMessage as ClientAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage as RelayAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\CountMessage as RelayCountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage as RelayEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Override;

final readonly class JsonMessageDeserialiser implements MessageDeserialiserInterface
{
    #[Override]
    public function deserialiseClientMessage(string $json): ?ClientMessage
    {
        $tagged = $this->decodeTagged($json);

        if (null === $tagged) {
            return null;
        }

        [$type, $data] = $tagged;

        return match (ClientMessageType::tryFrom($type)) {
            ClientMessageType::Event => ClientEventMessage::fromArray($data),
            ClientMessageType::Req => ReqMessage::fromArray($data),
            ClientMessageType::Close => CloseMessage::fromArray($data),
            ClientMessageType::Auth => ClientAuthMessage::fromArray($data),
            ClientMessageType::Count => CountMessage::fromArray($data),
            null => null,
        };
    }

    #[Override]
    public function deserialiseRelayMessage(string $json): ?RelayMessage
    {
        $tagged = $this->decodeTagged($json);

        if (null === $tagged) {
            return null;
        }

        [$type, $data] = $tagged;

        return match (RelayMessageType::tryFrom($type)) {
            RelayMessageType::Event => RelayEventMessage::fromArray($data),
            RelayMessageType::Ok => OkMessage::fromArray($data),
            RelayMessageType::Eose => EoseMessage::fromArray($data),
            RelayMessageType::Closed => ClosedMessage::fromArray($data),
            RelayMessageType::Notice => NoticeMessage::fromArray($data),
            RelayMessageType::Auth => RelayAuthMessage::fromArray($data),
            RelayMessageType::Count => RelayCountMessage::fromArray($data),
            null => null,
        };
    }

    /**
     * @return array{string, array<mixed>}|null
     */
    private function decodeTagged(string $json): ?array
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data || [] === $data || !array_is_list($data)) {
            return null;
        }

        return [is_string($data[0]) ? $data[0] : '', $data];
    }
}
