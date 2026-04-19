<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Exception\InvalidReferenceException;
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
        return 'reply' === $this->marker;
    }

    public function isRoot(): bool
    {
        return 'root' === $this->marker;
    }

    public function isMention(): bool
    {
        return 'mention' === $this->marker;
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

    public static function fromArray(array $data): self
    {
        return new self(
            EventId::fromHex($data['event_id']) ?? throw new InvalidReferenceException('Corrupt event_id in serialised EventReference'),
            isset($data['relay_url']) ? RelayUrl::fromString($data['relay_url']) : null,
            $data['marker'] ?? null,
            isset($data['author']) ? PublicKey::fromHex($data['author']) : null
        );
    }
}
