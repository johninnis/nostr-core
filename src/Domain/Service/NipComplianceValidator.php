<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final class NipComplianceValidator implements NipComplianceValidatorInterface
{
    public function __construct(
        private readonly SignatureServiceInterface $signatureService,
    ) {
    }

    #[Override]
    public function validateNip01Compliance(Event $event): void
    {
        $this->validateBasicStructure($event);
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

        $pTags = $event->getTags()->findByType(TagType::pubkey());
        if ([] === $pTags) {
            throw new InvalidEventException('NIP-04 events must have a p tag');
        }

        $this->validateNip01Compliance($event);
    }

    #[Override]
    public function validateNip09Compliance(Event $event): void
    {
        if (!$event->getKind()->is(EventKind::EVENT_DELETION)) {
            throw new InvalidEventException('NIP-09 events must be kind 5');
        }

        $eTags = $event->getTags()->findByType(TagType::event());
        $aTags = $event->getTags()->findByType(TagType::addressable());

        if ([] === $eTags && [] === $aTags) {
            throw new InvalidEventException('NIP-09 events must have at least one e or a tag');
        }

        $kTags = $event->getTags()->findByType(TagType::parentKind());

        if ([] === $kTags) {
            throw new InvalidEventException('NIP-09 events must have at least one k tag');
        }

        foreach ($kTags as $kTag) {
            $kindValue = $kTag->getValue();
            if (null !== $kindValue && (string) EventKind::EVENT_DELETION === $kindValue) {
                throw new InvalidEventException('NIP-09 events cannot target kind 5 deletion events');
            }
        }

        $this->validateNip01Compliance($event);
    }

    private function validateBasicStructure(Event $event): void
    {
        if ($event->getKind()->toInt() < 0) {
            throw new InvalidEventException('Event kind cannot be negative');
        }

        if (!$event->getCreatedAt()->isReasonable()) {
            throw new InvalidEventException('Event timestamp is not reasonable');
        }
    }

    // Deliberate: keeps its own signature gate wrapping Event::verify, not merged with EventValidator's distinct wording — see ADR-0017
    private function validateSignature(Event $event): void
    {
        if (!$event->isSigned()) {
            throw new InvalidEventException('Event must be signed for NIP compliance');
        }

        if (!$event->verify($this->signatureService)) {
            throw new InvalidEventException('Event signature is invalid');
        }
    }
}
