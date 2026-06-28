<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class TagReferenceExtractor
{
    private function __construct()
    {
    }

    public static function extract(TagCollection $tags): TagReferences
    {
        return new TagReferences(
            new EventReferenceCollection(self::collect($tags, self::eventReference(...))),
            new PubkeyReferenceCollection(self::collect($tags, self::pubkeyReference(...))),
            new EventReferenceCollection(self::collect($tags, self::quotedEvent(...))),
            new EventCoordinateCollection(self::collect($tags, self::coordinate(...))),
            new RelayReferenceCollection(self::collect($tags, self::relayReference(...))),
            self::collect($tags, self::challenge(...)),
        );
    }

    /**
     * @template TReference
     *
     * @param callable(Tag): (TReference|null) $parse
     *
     * @return list<TReference>
     */
    private static function collect(TagCollection $tags, callable $parse): array
    {
        $references = [];

        foreach ($tags as $tag) {
            $reference = $parse($tag);

            if (null !== $reference) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    private static function eventReference(Tag $tag): ?EventReference
    {
        if (TagType::EVENT !== (string) $tag->getType()) {
            return null;
        }

        $value = $tag->getValue(0);
        $eventId = null !== $value ? EventId::fromHex($value) : null;

        if (null === $eventId) {
            return null;
        }

        $author = $tag->getValue(3);

        return new EventReference(
            $eventId,
            RelayUrl::fromString($tag->getValue(1)),
            $tag->getValue(2),
            null !== $author ? PublicKey::fromHex($author) : null,
        );
    }

    private static function pubkeyReference(Tag $tag): ?PubkeyReference
    {
        if (TagType::PUBKEY !== (string) $tag->getType()) {
            return null;
        }

        $value = $tag->getValue(0);
        $pubkey = null !== $value ? PublicKey::fromHex($value) : null;

        if (null === $pubkey) {
            return null;
        }

        return new PubkeyReference(
            $pubkey,
            RelayUrl::fromString($tag->getValue(1)),
            $tag->getValue(2),
        );
    }

    private static function quotedEvent(Tag $tag): ?EventReference
    {
        if (TagType::QUOTE !== (string) $tag->getType()) {
            return null;
        }

        $value = $tag->getValue(0);

        if (null === $value || str_contains($value, ':')) {
            return null;
        }

        $eventId = EventId::fromHex($value);

        if (null === $eventId) {
            return null;
        }

        $author = $tag->getValue(2);

        return new EventReference(
            $eventId,
            RelayUrl::fromString($tag->getValue(1)),
            null,
            null !== $author ? PublicKey::fromHex($author) : null,
        );
    }

    private static function coordinate(Tag $tag): ?EventCoordinate
    {
        $value = $tag->getValue(0);

        return match ((string) $tag->getType()) {
            TagType::ADDRESSABLE => null !== $value ? EventCoordinate::fromATag($tag->toArray()) : null,
            TagType::QUOTE => null !== $value && str_contains($value, ':')
                ? EventCoordinate::fromString($value, $tag->getValue(1))
                : null,
            default => null,
        };
    }

    private static function relayReference(Tag $tag): ?RelayReference
    {
        if (TagType::REFERENCE !== (string) $tag->getType()) {
            return null;
        }

        $value = $tag->getValue(0);
        $relayUrl = null !== $value ? RelayUrl::fromString($value) : null;

        if (null === $relayUrl) {
            return null;
        }

        return new RelayReference($relayUrl, $tag->getValue(1));
    }

    private static function challenge(Tag $tag): ?string
    {
        return TagType::CHALLENGE === (string) $tag->getType() ? $tag->getValue(0) : null;
    }
}
