<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

interface EventValidatorInterface
{
    public function validateEvent(Event $event, Timestamp $reference): void;

    public function isEventValid(Event $event, Timestamp $reference): bool;
}
