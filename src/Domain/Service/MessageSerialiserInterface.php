<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;

interface MessageSerialiserInterface
{
    public function deserialiseClientMessage(string $json): ClientMessage;

    public function deserialiseRelayMessage(string $json): RelayMessage;
}
