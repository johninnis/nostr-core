<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\EventKindCategory;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\ValueObject\EventLimits;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;

final readonly class EventValidator implements EventValidatorInterface
{
    // Deliberate: NIP-01 sets none of these limits; they are the host's acceptance policy, injected with this package's defaults — see ADR-0131
    public function __construct(
        private SignatureServiceInterface $signatureService,
        private NipComplianceValidatorInterface $nipValidator,
        private EventLimits $limits = new EventLimits(),
    ) {
    }

    // Deliberate: the reference instant is an argument, not a clock read or an injected port, so this domain validator stays a pure function of its inputs — see ADR-0080
    #[Override]
    public function validateEvent(Event $event, Timestamp $reference): void
    {
        $this->validateCreatedAt($event, $reference);
        $this->validateContent($event);
        $this->validateTags($event);
        $this->validateSignature($event);

        // Deliberate: only the NIP-09 shape, as the NIP-01 baseline was checked above and a deletion's signature is verified once — see ADR-0099
        if ($event->isDeletion()) {
            $this->nipValidator->validateNip09Shape($event);
        }
    }

    #[Override]
    public function isEventValid(Event $event, Timestamp $reference): bool
    {
        try {
            $this->validateEvent($event, $reference);

            return true;
        } catch (InvalidEventException) {
            return false;
        }
    }

    private function validateCreatedAt(Event $event, Timestamp $reference): void
    {
        if (!$this->limits->admitsCreatedAt($event->getCreatedAt(), $reference)) {
            throw new InvalidEventException('Event created_at is outside the accepted window');
        }
    }

    private function validateContent(Event $event): void
    {
        if (!$this->limits->admitsContentLength($event->getContent()->getLength())) {
            throw new InvalidEventException('Event content exceeds maximum length');
        }
    }

    private function validateTags(Event $event): void
    {
        if (!$this->limits->admitsTagCount($event->getTags()->count())) {
            throw new InvalidEventException('Event has too many tags');
        }

        if (EventKindCategory::Addressable === $event->getKind()->category() && null === $event->getTags()->getIdentifier()) {
            throw new InvalidEventException('Addressable event d tags disagree');
        }
    }

    // Deliberate: keeps its own signature gate wrapping Event::verify, scoped to this validator rather than merged into NipComplianceValidator — see ADR-0099
    private function validateSignature(Event $event): void
    {
        if (!$event->verify($this->signatureService)) {
            throw new InvalidEventException('Event signature is invalid');
        }
    }
}
