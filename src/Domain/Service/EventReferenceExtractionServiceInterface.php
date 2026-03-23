<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\EventReferences;

interface EventReferenceExtractionServiceInterface
{
    public function extractReferences(Event $event): EventReferences;
}
