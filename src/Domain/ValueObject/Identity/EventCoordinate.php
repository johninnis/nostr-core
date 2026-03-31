<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class EventCoordinate
{
    private function __construct(
        private EventKind $kind,
        private PublicKey $pubkey,
        private string $identifier,
        private ?RelayUrl $relayHint = null,
    ) {
    }

    public static function fromParts(int $kind, string $pubkeyHex, string $identifier, ?string $relayHint = null): ?self
    {
        $eventKind = EventKind::fromInt($kind);
        if (!$eventKind->isParameterisedReplaceable()) {
            return null;
        }

        $pubkey = PublicKey::fromHex($pubkeyHex);
        if (null === $pubkey || '' === $identifier) {
            return null;
        }

        return new self(
            $eventKind,
            $pubkey,
            $identifier,
            null !== $relayHint ? RelayUrl::fromString($relayHint) : null
        );
    }

    public static function fromString(string $coordinate, ?string $relayHint = null): ?self
    {
        $parts = explode(':', $coordinate);

        if (count($parts) < 3) {
            return null;
        }

        $kind = (int) $parts[0];
        $pubkey = $parts[1];
        $identifier = implode(':', array_slice($parts, 2));

        return self::fromParts($kind, $pubkey, $identifier, $relayHint);
    }

    public static function fromATag(array $tag): ?self
    {
        if (!isset($tag[0]) || 'a' !== $tag[0] || !isset($tag[1])) {
            return null;
        }

        $relayHint = isset($tag[2]) && is_string($tag[2]) && '' !== $tag[2] ? $tag[2] : null;

        return self::fromString($tag[1], $relayHint);
    }

    public function getKind(): EventKind
    {
        return $this->kind;
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRelayHint(): ?RelayUrl
    {
        return $this->relayHint;
    }

    public function withRelayHint(?RelayUrl $relayHint): self
    {
        return new self($this->kind, $this->pubkey, $this->identifier, $relayHint);
    }

    public function toString(): string
    {
        return $this->kind->toInt().':'.$this->pubkey->toHex().':'.$this->identifier;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toATag(): array
    {
        $tag = ['a', $this->toString()];

        if (null !== $this->relayHint) {
            $tag[] = (string) $this->relayHint;
        }

        return $tag;
    }

    public function matchesEvent(Event $event): bool
    {
        if ($event->getKind()->toInt() !== $this->kind->toInt()) {
            return false;
        }

        if (!$event->getPubkey()->equals($this->pubkey)) {
            return false;
        }

        $dTags = $event->getTags()->getValuesByType(TagType::identifier());

        return in_array($this->identifier, $dTags, true);
    }

    public function equals(self $other, bool $includeRelayHint = false): bool
    {
        $baseEquals = $this->kind->toInt() === $other->kind->toInt()
            && $this->pubkey->toHex() === $other->pubkey->toHex()
            && $this->identifier === $other->identifier;

        if (!$includeRelayHint) {
            return $baseEquals;
        }

        $thisHint = null !== $this->relayHint ? (string) $this->relayHint : null;
        $otherHint = null !== $other->relayHint ? (string) $other->relayHint : null;

        return $baseEquals && $thisHint === $otherHint;
    }

    public function toArray(): array
    {
        $data = [
            'kind' => $this->kind->toInt(),
            'pubkey' => $this->pubkey->toHex(),
            'identifier' => $this->identifier,
        ];

        if (null !== $this->relayHint) {
            $data['relay_hint'] = (string) $this->relayHint;
        }

        return $data;
    }

    public static function fromArray(array $data): ?self
    {
        if (!isset($data['kind'], $data['pubkey'], $data['identifier'])) {
            return null;
        }

        return self::fromParts(
            (int) $data['kind'],
            $data['pubkey'],
            $data['identifier'],
            $data['relay_hint'] ?? null
        );
    }
}
