<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ChallengeCollection;
use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\RelayMarker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
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
            new ChallengeCollection(self::collect($tags, self::challenge(...))),
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
        return $tag->getType()->is(TagType::EVENT) ? EventReference::tryFromTag($tag) : null;
    }

    private static function pubkeyReference(Tag $tag): ?PubkeyReference
    {
        return $tag->getType()->is(TagType::PUBKEY) ? PubkeyReference::tryFromTag($tag) : null;
    }

    private static function quotedEvent(Tag $tag): ?EventReference
    {
        return $tag->getType()->is(TagType::QUOTE) ? EventReference::tryFromTag($tag) : null;
    }

    private static function coordinate(Tag $tag): ?EventCoordinate
    {
        $namesCoordinate = $tag->getType()->is(TagType::ADDRESSABLE) || $tag->getType()->is(TagType::QUOTE);

        return $namesCoordinate ? EventCoordinate::tryFromTag($tag) : null;
    }

    private static function relayReference(Tag $tag): ?RelayReference
    {
        if (!$tag->getType()->is(TagType::REFERENCE)) {
            return null;
        }

        $value = $tag->getValue(0);
        $relayUrl = null !== $value ? RelayUrl::tryFromString($value) : null;

        if (null === $relayUrl) {
            return null;
        }

        return new RelayReference($relayUrl, RelayMarker::fromTagValue($tag->getValue(1)));
    }

    private static function challenge(Tag $tag): ?Challenge
    {
        return $tag->getType()->is(TagType::CHALLENGE) ? Challenge::tryFromString($tag->getValue(0)) : null;
    }
}
