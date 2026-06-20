<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Encoding;

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
use InvalidArgumentException;
use Override;

final class JsonMessageDeserialiser implements MessageDeserialiserInterface
{
    #[Override]
    public function deserialiseClientMessage(string $json): ClientMessage
    {
        if (!json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON for client message');
        }

        $data = json_decode($json, true);

        if (!is_array($data) || [] === $data) {
            throw new InvalidArgumentException('Invalid JSON for client message');
        }

        $type = is_string($data[0] ?? null) ? $data[0] : '';

        $message = match ($type) {
            'EVENT' => ClientEventMessage::fromArray($data),
            'REQ' => ReqMessage::fromArray($data),
            'CLOSE' => CloseMessage::fromArray($data),
            'AUTH' => ClientAuthMessage::fromArray($data),
            'COUNT' => CountMessage::fromArray($data),
            default => throw new InvalidArgumentException("Unknown client message type: {$type}"),
        };

        return $message
            ?? throw new InvalidArgumentException("Malformed {$type} client message");
    }

    #[Override]
    public function deserialiseRelayMessage(string $json): RelayMessage
    {
        if (!json_validate($json)) {
            throw new InvalidArgumentException('Invalid JSON for relay message');
        }

        $data = json_decode($json, true);

        if (!is_array($data) || [] === $data) {
            throw new InvalidArgumentException('Invalid JSON for relay message');
        }

        $type = is_string($data[0] ?? null) ? $data[0] : '';

        $message = match ($type) {
            'EVENT' => RelayEventMessage::fromArray($data),
            'OK' => OkMessage::fromArray($data),
            'EOSE' => EoseMessage::fromArray($data),
            'CLOSED' => ClosedMessage::fromArray($data),
            'NOTICE' => NoticeMessage::fromArray($data),
            'AUTH' => RelayAuthMessage::fromArray($data),
            'COUNT' => RelayCountMessage::fromArray($data),
            default => throw new InvalidArgumentException("Unknown relay message type: {$type}"),
        };

        return $message
            ?? throw new InvalidArgumentException("Malformed {$type} relay message");
    }
}
