<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrlCollection;

final readonly class DecodedNip19Entity
{
    public function __construct(
        private Nip19EntityType $type,
        private ?PublicKey $publicKey = null,
        private ?EventId $eventId = null,
        private ?string $identifier = null,
        private ?EventKind $kind = null,
        private RelayUrlCollection $relays = new RelayUrlCollection(),
    ) {
    }

    public function getType(): Nip19EntityType
    {
        return $this->type;
    }

    public function getPublicKey(): ?PublicKey
    {
        return $this->publicKey;
    }

    public function getEventId(): ?EventId
    {
        return $this->eventId;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getKind(): ?EventKind
    {
        return $this->kind;
    }

    public function getRelays(): RelayUrlCollection
    {
        return $this->relays;
    }
}
