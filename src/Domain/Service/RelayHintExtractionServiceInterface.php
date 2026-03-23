<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;

interface RelayHintExtractionServiceInterface
{
    public function extractRelayHints(Event $event): array;

    public function extractRelayHintsFromTags(TagCollection $tags): array;

    public function extractRelayHintsFromContent(string $content): array;

    public function extractRelayHintFromNevent(string $nevent): ?RelayUrl;
}
