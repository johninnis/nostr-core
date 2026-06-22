<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

final readonly class EventReference
{
    public function __construct(
        private EventId $eventId,
        private ?RelayUrl $relayUrl = null,
        private ?string $marker = null,
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

    public function getMarker(): ?string
    {
        return $this->marker;
    }

    public function getAuthor(): ?PublicKey
    {
        return $this->author;
    }

    public function isReply(): bool
    {
        return Nip10Marker::Reply->value === $this->marker;
    }

    public function isRoot(): bool
    {
        return Nip10Marker::Root->value === $this->marker;
    }

    public function isMention(): bool
    {
        return Nip10Marker::Mention->value === $this->marker;
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

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId->toHex(),
            'relay_url' => null !== $this->relayUrl ? (string) $this->relayUrl : null,
            'marker' => $this->marker,
            'author' => $this->author?->toHex(),
        ];
    }

    public static function fromArray(array $data): ?self
    {
        $eventIdHex = $data['event_id'] ?? null;
        if (!is_string($eventIdHex)) {
            return null;
        }

        $eventId = EventId::fromHex($eventIdHex);
        if (null === $eventId) {
            return null;
        }

        return new self(
            $eventId,
            isset($data['relay_url']) && is_string($data['relay_url']) ? RelayUrl::fromString($data['relay_url']) : null,
            isset($data['marker']) && is_string($data['marker']) ? $data['marker'] : null,
            isset($data['author']) && is_string($data['author']) ? PublicKey::fromHex($data['author']) : null,
        );
    }
}
