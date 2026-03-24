<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Adapter;

use Innis\Nostr\Core\Domain\Service\MessageSerialiserInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\AuthMessage as ClientAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CloseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage as RelayAuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\ClosedMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EoseMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\EventMessage as RelayEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\NoticeMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;

final class JsonMessageSerialiserAdapter implements MessageSerialiserInterface
{
    public function deserialiseClientMessage(string $json): ClientMessage
    {
        $data = json_decode($json, true);

        if (null === $data || !is_array($data) || empty($data)) {
            throw new InvalidArgumentException('Invalid JSON for client message');
        }

        $type = $data[0] ?? '';

        return match ($type) {
            'EVENT' => ClientEventMessage::fromArray($data),
            'REQ' => ReqMessage::fromArray($data),
            'CLOSE' => CloseMessage::fromArray($data),
            'AUTH' => ClientAuthMessage::fromArray($data),
            'COUNT' => CountMessage::fromArray($data),
            default => throw new InvalidArgumentException("Unknown client message type: {$type}"),
        };
    }

    public function deserialiseRelayMessage(string $json): RelayMessage
    {
        $data = json_decode($json, true);

        if (null === $data || !is_array($data) || empty($data)) {
            throw new InvalidArgumentException('Invalid JSON for relay message');
        }

        $type = $data[0] ?? '';

        return match ($type) {
            'EVENT' => RelayEventMessage::fromArray($data),
            'OK' => OkMessage::fromArray($data),
            'EOSE' => EoseMessage::fromArray($data),
            'CLOSED' => ClosedMessage::fromArray($data),
            'NOTICE' => NoticeMessage::fromArray($data),
            'AUTH' => RelayAuthMessage::fromArray($data),
            default => throw new InvalidArgumentException("Unknown relay message type: {$type}"),
        };
    }
}
