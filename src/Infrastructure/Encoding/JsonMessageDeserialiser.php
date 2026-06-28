<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Encoding;

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

        return match ($type) {
            'EVENT' => ClientEventMessage::fromArray($data),
            'REQ' => ReqMessage::fromArray($data),
            'CLOSE' => CloseMessage::fromArray($data),
            'AUTH' => ClientAuthMessage::fromArray($data),
            'COUNT' => CountMessage::fromArray($data),
            default => null,
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

        return match ($type) {
            'EVENT' => RelayEventMessage::fromArray($data),
            'OK' => OkMessage::fromArray($data),
            'EOSE' => EoseMessage::fromArray($data),
            'CLOSED' => ClosedMessage::fromArray($data),
            'NOTICE' => NoticeMessage::fromArray($data),
            'AUTH' => RelayAuthMessage::fromArray($data),
            'COUNT' => RelayCountMessage::fromArray($data),
            default => null,
        };
    }

    /**
     * @return array{string, array<mixed>}|null
     */
    private function decodeTagged(string $json): ?array
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data || [] === $data) {
            return null;
        }

        return [is_string($data[0] ?? null) ? $data[0] : '', $data];
    }
}
