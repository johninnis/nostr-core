<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class EventVersion
{
    // Deliberate: a public constructor for the two values a store already holds, and of() for an Event in hand — see ADR-0067
    public function __construct(
        private Timestamp $createdAt,
        private EventId $id,
    ) {
    }

    public static function of(Event $event): self
    {
        return new self($event->getCreatedAt(), $event->getId());
    }

    public function getId(): EventId
    {
        return $this->id;
    }

    // Deliberate: the tie-break is lower id wins, which NIP-01 states and which reads backwards next to the later-timestamp rule above it — see ADR-0067
    public function supersedes(self $other): bool
    {
        if (!$this->createdAt->equals($other->createdAt)) {
            return $this->createdAt->isAfter($other->createdAt);
        }

        return strcmp($this->id->toBytes(), $other->id->toBytes()) < 0;
    }
}
