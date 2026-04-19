<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;

final class EventValidationService implements EventValidationServiceInterface
{
    private const MAX_CONTENT_LENGTH = 65536;
    private const MAX_TAGS_COUNT = 5000;

    public function __construct(
        private readonly SignatureServiceInterface $signatureService,
        private readonly NipComplianceValidatorInterface $nipValidator,
    ) {
    }

    public function validateEvent(Event $event): void
    {
        $this->validateTimestamp($event);
        $this->validateContent($event);
        $this->validateTags($event);
        $this->validateSignature($event);

        if ($event->isDeletion()) {
            $this->nipValidator->validateNip09Compliance($event);
        }
    }

    public function isEventValid(Event $event): bool
    {
        try {
            $this->validateEvent($event);

            return true;
        } catch (InvalidEventException) {
            return false;
        }
    }

    private function validateTimestamp(Event $event): void
    {
        if (!$event->getCreatedAt()->isReasonable()) {
            throw new InvalidEventException('Event timestamp is not reasonable');
        }
    }

    private function validateContent(Event $event): void
    {
        if ($event->getContent()->getLength() > self::MAX_CONTENT_LENGTH) {
            throw new InvalidEventException('Event content exceeds maximum length');
        }
    }

    private function validateTags(Event $event): void
    {
        if ($event->getTags()->count() > self::MAX_TAGS_COUNT) {
            throw new InvalidEventException('Event has too many tags');
        }
    }

    private function validateSignature(Event $event): void
    {
        if ($event->isSigned() && !$event->verify($this->signatureService)) {
            throw new InvalidEventException('Event signature is invalid');
        }
    }
}
