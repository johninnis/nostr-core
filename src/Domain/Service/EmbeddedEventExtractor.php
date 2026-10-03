<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;

final class EmbeddedEventExtractor
{
    private function __construct()
    {
    }

    public static function extract(Event $event): ?Event
    {
        if (!$event->isRepost()) {
            return null;
        }

        return Event::tryFromJson((string) $event->getContent());
    }
}
