<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Override;

final readonly class NipComplianceValidator implements NipComplianceValidatorInterface
{
    public function __construct(
        private SignatureServiceInterface $signatureService,
    ) {
    }

    #[Override]
    public function validateNip01Compliance(Event $event): void
    {
        $this->validateSignature($event);
    }

    #[Override]
    public function validateNip02Compliance(Event $event): void
    {
        if (!$event->getKind()->is(EventKind::FOLLOW_LIST)) {
            throw new InvalidEventException('NIP-02 events must be kind 3');
        }

        $this->validateNip01Compliance($event);
    }

    #[Override]
    public function validateNip04Compliance(Event $event): void
    {
        if (!$event->getKind()->is(EventKind::ENCRYPTED_DIRECT_MESSAGE)) {
            throw new InvalidEventException('NIP-04 events must be kind 4');
        }

        if ($event->getTags()->getPubkeys()->isEmpty()) {
            throw new InvalidEventException('NIP-04 events must have a p tag naming a public key');
        }

        $this->validateNip01Compliance($event);
    }

    #[Override]
    public function validateNip09Compliance(Event $event): void
    {
        $this->validateNip09Shape($event);
        $this->validateNip01Compliance($event);
    }

    #[Override]
    public function validateNip09Shape(Event $event): void
    {
        if (!$event->isDeletion()) {
            throw new InvalidEventException('NIP-09 events must be kind 5');
        }

        if (!self::namesADeletionTarget($event->getTags())) {
            throw new InvalidEventException('NIP-09 events must have at least one e or a tag naming an event id or a coordinate');
        }
    }

    private static function namesADeletionTarget(TagCollection $tags): bool
    {
        return !$tags->getEventIds()->isEmpty() || !$tags->getCoordinates()->isEmpty();
    }

    // Deliberate: keeps its own signature gate wrapping Event::verify, scoped to this validator rather than merged into EventValidator — see ADR-0099
    private function validateSignature(Event $event): void
    {
        if (!$event->verify($this->signatureService)) {
            throw new InvalidEventException('Event signature is invalid');
        }
    }
}
