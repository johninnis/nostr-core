<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;

interface EventValidatorInterface
{
    public function validateEvent(Event $event): void;

    public function isEventValid(Event $event): bool;
}
