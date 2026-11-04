<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final class DeletionCoverage
{
    private const array GIFT_WRAP_KINDS = [EventKind::GIFT_WRAP, EventKind::EPHEMERAL_GIFT_WRAP];

    private function __construct()
    {
    }

    // Deliberate: the one judgement of the shared deletion rule, pinned by the corpus both cores and both relays run — see ADR-0145 and nostr-adrs ADR-0127
    public static function covers(Event $deletion, Event $target): bool
    {
        return $deletion->isDeletion()
            && !$target->isDeletion()
            && (self::namesById($deletion, $target) || self::namesByCoordinate($deletion, $target));
    }

    public static function deletedCoordinates(Event $deletion): EventCoordinateCollection
    {
        if (!$deletion->isDeletion()) {
            return new EventCoordinateCollection();
        }

        $own = [];
        foreach ($deletion->getTags()->getCoordinates() as $coordinate) {
            if ($coordinate->getPubkey()->equals($deletion->getPubkey())) {
                $own[(string) $coordinate] ??= $coordinate->withRelayHint(null);
            }
        }

        return new EventCoordinateCollection(array_values($own));
    }

    public static function lookupFilters(Event $target): FilterCollection
    {
        if ($target->isDeletion()) {
            return new FilterCollection();
        }

        $byId = Filter::from(
            authors: self::keysThatDelete($target),
            kinds: EventKindCollection::fromInts([EventKind::EVENT_DELETION]),
            tags: TagFilter::fromValues([TagType::EVENT => [$target->getId()->toHex()]]),
        );
        $coordinate = EventCoordinate::tryFromEvent($target);

        return new FilterCollection(null === $coordinate ? [$byId] : [$byId, Filter::from(
            authors: new PublicKeyCollection([$target->getPubkey()]),
            kinds: EventKindCollection::fromInts([EventKind::EVENT_DELETION]),
            tags: TagFilter::fromValues([TagType::ADDRESSABLE => [(string) $coordinate]]),
            since: $target->getCreatedAt(),
        )]);
    }

    private static function namesById(Event $deletion, Event $target): bool
    {
        return $deletion->getTags()->getEventIds()->contains($target->getId())
            && self::keysThatDelete($target)->contains($deletion->getPubkey());
    }

    // Deliberate: a coordinate deletes only the versions no later than the request, so a later version restores it — see nostr-adrs ADR-0124
    private static function namesByCoordinate(Event $deletion, Event $target): bool
    {
        return !$target->getCreatedAt()->isAfter($deletion->getCreatedAt())
            && array_any(
                self::deletedCoordinates($deletion)->toArray(),
                static fn (EventCoordinate $deleted): bool => $deleted->matchesEvent($target),
            );
    }

    // Deliberate: a gift wrap is also deleted by a key it is addressed to, not only by its one-time signer — see nostr-adrs ADR-0124
    private static function keysThatDelete(Event $target): PublicKeyCollection
    {
        $signer = new PublicKeyCollection([$target->getPubkey()]);

        return in_array($target->getKind()->toInt(), self::GIFT_WRAP_KINDS, true)
            ? $signer->merge($target->getTags()->getPubkeys())->unique()
            : $signer;
    }
}
