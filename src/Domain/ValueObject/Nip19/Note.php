<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Override;

final readonly class Note implements Nip19EntityInterface
{
    private function __construct(private EventId $eventId)
    {
    }

    public static function fromEventId(EventId $eventId): self
    {
        return new self($eventId);
    }

    public function getEventId(): EventId
    {
        return $this->eventId;
    }

    #[Override]
    public function type(): Nip19EntityType
    {
        return Nip19EntityType::Note;
    }

    // Deliberate: the bech32 form and its hrp belong to the value object this wraps; this leaf exists only to make the sum type total over NIP-19 prefixes and must not restate either — see ADR-0060
    #[Override]
    public function toBech32(): string
    {
        return $this->eventId->toBech32();
    }
}
