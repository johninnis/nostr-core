<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nip19EntityInterface;

// Deliberate: there is no encode method here; each entity is minted through its own named constructor and encodes itself, so there is one way to produce a NIP-19 string — see ADR-0060
interface Nip19CodecInterface
{
    public function decodeComplexEntity(string $bech32): ?Nip19EntityInterface;

    public function parseEventReference(string $input): EventId|EventCoordinate|null;
}
