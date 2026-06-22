<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;

final class EmbeddedEventExtractor
{
    public static function extract(Event $event): ?Event
    {
        if (!$event->isRepost()) {
            return null;
        }

        $content = (string) $event->getContent();
        if ('' === $content) {
            return null;
        }

        $embeddedData = JsonWireFormat::decodeArray($content);
        if (null === $embeddedData) {
            return null;
        }

        if (!isset($embeddedData['id'], $embeddedData['pubkey'], $embeddedData['created_at'], $embeddedData['kind'])) {
            return null;
        }

        return Event::fromArray($embeddedData);
    }
}
