<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;

interface RelayHintExtractorInterface
{
    public function extractRelayHints(Event $event): array;
}
