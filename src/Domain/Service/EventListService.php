<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class EventListService
{
    public function addTagToList(?Event $existingList, Tag $newTag, TagType $tagType): TagCollection
    {
        $existingTags = $existingList?->getTags()->findByType($tagType) ?? [];

        $filteredTags = array_filter(
            $existingTags,
            static fn (Tag $tag) => $tag->getValue(0) !== $newTag->getValue(0)
        );

        return new TagCollection([...$filteredTags, $newTag]);
    }

    public function removeTagFromList(?Event $existingList, string $value, TagType $tagType): TagCollection
    {
        $existingTags = $existingList?->getTags()->findByType($tagType) ?? [];

        $filteredTags = array_filter(
            $existingTags,
            static fn (Tag $tag) => $tag->getValue(0) !== $value
        );

        return new TagCollection($filteredTags);
    }
}
