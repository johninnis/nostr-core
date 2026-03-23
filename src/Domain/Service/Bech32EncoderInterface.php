<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface Bech32EncoderInterface
{
    public function decodeComplexEntity(string $bech32): array;

    public function encodeAddressableEvent(string $identifier, PublicKey $pubkey, int $kind, array $relays = []): string;

    public function parseEventReference(string $input): EventId|EventCoordinate|null;
}
