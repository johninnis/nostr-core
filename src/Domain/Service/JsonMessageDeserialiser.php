<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
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
            ClientMessageType::Event => ClientEventMessage::tryFromArray($data),
            ClientMessageType::Req => ReqMessage::tryFromArray($data),
            ClientMessageType::Close => CloseMessage::tryFromArray($data),
            ClientMessageType::Auth => ClientAuthMessage::tryFromArray($data),
            ClientMessageType::Count => CountMessage::tryFromArray($data),
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
            RelayMessageType::Event => RelayEventMessage::tryFromArray($data),
            RelayMessageType::Ok => OkMessage::tryFromArray($data),
            RelayMessageType::Eose => EoseMessage::tryFromArray($data),
            RelayMessageType::Closed => ClosedMessage::tryFromArray($data),
            RelayMessageType::Notice => NoticeMessage::tryFromArray($data),
            RelayMessageType::Auth => RelayAuthMessage::tryFromArray($data),
            RelayMessageType::Count => RelayCountMessage::tryFromArray($data),
            null => null,
        };
    }

    /**
     * @return array{string, array<mixed>}|null
     */
    private function decodeTagged(string $json): ?array
    {
        $data = JsonWireFormat::decodeList($json);

        if (null === $data) {
            return null;
        }

        return [is_string($data[0]) ? $data[0] : '', $data];
    }
}
