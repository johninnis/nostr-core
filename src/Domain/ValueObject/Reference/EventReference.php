<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class EventReference
{
    public function __construct(
        private EventId $eventId,
        private ?RelayUrl $relayUrl = null,
        private ?Nip10Marker $marker = null,
        private ?PublicKey $author = null,
    ) {
    }

    public function getEventId(): EventId
    {
        return $this->eventId;
    }

    public function getRelayUrl(): ?RelayUrl
    {
        return $this->relayUrl;
    }

    public function getMarker(): ?Nip10Marker
    {
        return $this->marker;
    }

    public function getAuthor(): ?PublicKey
    {
        return $this->author;
    }

    public function isReply(): bool
    {
        return Nip10Marker::Reply === $this->marker;
    }

    public function isRoot(): bool
    {
        return Nip10Marker::Root === $this->marker;
    }

    public function isMention(): bool
    {
        return Nip10Marker::Mention === $this->marker;
    }

    public function toETag(): Tag
    {
        $optional = [
            null === $this->relayUrl ? '' : (string) $this->relayUrl,
            $this->marker->value ?? '',
            $this->author?->toHex() ?? '',
        ];
        $written = array_keys(array_filter($optional, static fn (string $element): bool => '' !== $element));

        return Tag::fromArray([
            TagType::EVENT,
            $this->eventId->toHex(),
            ...array_slice($optional, 0, [] === $written ? 0 : max($written) + 1),
        ]);
    }

    public function equals(self $other): bool
    {
        return $this->eventId->equals($other->eventId)
            && $this->marker === $other->marker
            && (null === $this->relayUrl ? null === $other->relayUrl :
                (null !== $other->relayUrl && $this->relayUrl->equals($other->relayUrl)))
            && (null === $this->author ? null === $other->author :
                (null !== $other->author && $this->author->equals($other->author)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId->toHex(),
            'relay_url' => null !== $this->relayUrl ? (string) $this->relayUrl : null,
            'marker' => $this->marker?->value,
            'author' => $this->author?->toHex(),
        ];
    }

    public static function tryFromTag(Tag $tag): ?self
    {
        $eventId = EventId::tryFromHex($tag->getValue(0) ?? '');
        $isEventTag = $tag->getType()->is(TagType::EVENT);
        // Deliberate: an e tag's author is read by its length, never its marker — a fifth element is the NIP-10 slot, else the fourth is the NIP-22/NIP-25 one; an E or q tag has no marker slot and names its author in its fourth — see nostr-adrs ADR-0012
        $author = $isEventTag ? $tag->getValue(3) ?? $tag->getValue(2) : $tag->getValue(2);

        return null === $eventId ? null : new self(
            $eventId,
            RelayUrl::tryFromString($tag->getValue(1)),
            $isEventTag ? Nip10Marker::tryFrom($tag->getValue(2) ?? '') : null,
            null === $author ? null : PublicKey::tryFromHex($author),
        );
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $eventIdHex = $data['event_id'] ?? null;
        if (!is_string($eventIdHex)) {
            return null;
        }

        $eventId = EventId::tryFromHex($eventIdHex);
        if (null === $eventId) {
            return null;
        }

        return new self(
            $eventId,
            isset($data['relay_url']) && is_string($data['relay_url']) ? RelayUrl::tryFromString($data['relay_url']) : null,
            Nip10Marker::tryFrom(is_string($data['marker'] ?? null) ? $data['marker'] : ''),
            isset($data['author']) && is_string($data['author']) ? PublicKey::tryFromHex($data['author']) : null,
        );
    }
}
