<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Validation;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class NipEventValidatorAdapter
{
    public function validateNip01Compliance(Event $event): void
    {
        $this->validateBasicStructure($event);
        $this->validateSignature($event);
        $this->validateEventId($event);
    }

    public function validateNip02Compliance(Event $event): void
    {
        if (EventKind::FOLLOW_LIST !== $event->getKind()->toInt()) {
            throw new InvalidEventException('NIP-02 events must be kind 3');
        }

        $this->validateNip01Compliance($event);
    }

    public function validateNip04Compliance(Event $event): void
    {
        if (EventKind::ENCRYPTED_DIRECT_MESSAGE !== $event->getKind()->toInt()) {
            throw new InvalidEventException('NIP-04 events must be kind 4');
        }

        $pTags = $event->getTags()->findByType(TagType::pubkey());
        if (empty($pTags)) {
            throw new InvalidEventException('NIP-04 events must have a p tag');
        }

        $this->validateNip01Compliance($event);
    }

    public function validateNip09Compliance(Event $event): void
    {
        if (EventKind::EVENT_DELETION !== $event->getKind()->toInt()) {
            throw new InvalidEventException('NIP-09 events must be kind 5');
        }

        $eTags = $event->getTags()->findByType(TagType::event());
        $aTags = $event->getTags()->findByType(TagType::addressable());

        if (empty($eTags) && empty($aTags)) {
            throw new InvalidEventException('NIP-09 events must have at least one e or a tag');
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

    private function validateSignature(Event $event): void
    {
        if (!$event->isSigned()) {
            throw new InvalidEventException('Event must be signed for NIP compliance');
        }

        if (!$event->verify()) {
            throw new InvalidEventException('Event signature is invalid');
        }
    }

    private function validateEventId(Event $event): void
    {
        $calculatedId = $event->calculateId();
        $eventId = $event->getId();

        if (!$calculatedId->equals($eventId)) {
            throw new InvalidEventException('Event ID does not match calculated ID');
        }
    }
}
