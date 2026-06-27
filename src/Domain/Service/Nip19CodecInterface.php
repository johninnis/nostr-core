<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;

interface Nip19CodecInterface
{
    public function decodeComplexEntity(string $bech32): ?DecodedNip19Entity;

    public function encodeAddressableEvent(EventCoordinate $coordinate, RelayUrlCollection $relays = new RelayUrlCollection()): string;

    public function parseEventReference(string $input): EventId|EventCoordinate|null;
}
