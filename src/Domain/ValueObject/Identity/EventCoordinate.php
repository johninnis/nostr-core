<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\EventKindCategory;
use Innis\Nostr\Core\Domain\Service\DecimalIntegerParser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;
use Stringable;

final readonly class EventCoordinate implements Stringable
{
    private function __construct(
        private EventKind $kind,
        private PublicKey $pubkey,
        private string $identifier,
        private ?RelayUrl $relayHint = null,
    ) {
    }

    // Deliberate: a replaceable kind is addressed with an empty identifier and an addressable kind with any, including empty, per NIP-01 and NIP-19 — see ADR-0082
    public static function tryFrom(EventKind $kind, PublicKey $pubkey, string $identifier): ?self
    {
        if (!mb_check_encoding($identifier, 'UTF-8')) {
            return null;
        }

        $isCoordinate = match ($kind->category()) {
            EventKindCategory::Replaceable => '' === $identifier,
            EventKindCategory::Addressable => true,
            EventKindCategory::Regular, EventKindCategory::Ephemeral => false,
        };

        return $isCoordinate ? new self($kind, $pubkey, $identifier) : null;
    }

    public static function tryFromEvent(Event $event): ?self
    {
        $identifier = EventKindCategory::Addressable === $event->getKind()->category()
            ? $event->getTags()->getIdentifier()
            : '';

        return null === $identifier ? null : self::tryFrom($event->getKind(), $event->getPubkey(), $identifier);
    }

    public static function tryFromString(string $coordinate, ?string $relayHint = null): ?self
    {
        $parts = explode(':', $coordinate);

        if (count($parts) < 3) {
            return null;
        }

        $kindNumber = DecimalIntegerParser::tryParse($parts[0]);
        $kind = null === $kindNumber ? null : EventKind::tryFromInt($kindNumber);
        $pubkey = PublicKey::tryFromHex($parts[1]);
        $coordinate = null === $kind || null === $pubkey ? null : self::tryFrom($kind, $pubkey, implode(':', array_slice($parts, 2)));

        return $coordinate?->withRelayHint(RelayUrl::tryFromString($relayHint));
    }

    public static function tryFromTag(Tag $tag): ?self
    {
        $value = $tag->getValue(0);

        return null === $value ? null : self::tryFromString($value, $tag->getValue(1));
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

    #[Override]
    public function __toString(): string
    {
        return $this->kind->toInt().':'.$this->pubkey->toHex().':'.$this->identifier;
    }

    public function toATag(): Tag
    {
        return Tag::fromArray([TagType::ADDRESSABLE, (string) $this, ...(null === $this->relayHint ? [] : [(string) $this->relayHint])]);
    }

    public function matchesEvent(Event $event): bool
    {
        $address = self::tryFromEvent($event);

        return null !== $address && $this->equals($address);
    }

    public function equals(self $other): bool
    {
        return $this->kind->equals($other->kind)
            && $this->pubkey->equals($other->pubkey)
            && $this->identifier === $other->identifier;
    }

    /**
     * @return array<string, mixed>
     */
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

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $kind = is_int($data['kind'] ?? null) ? EventKind::tryFromInt($data['kind']) : null;
        $pubkey = is_string($data['pubkey'] ?? null) ? PublicKey::tryFromHex($data['pubkey']) : null;
        $identifier = $data['identifier'] ?? null;
        $relayHint = $data['relay_hint'] ?? null;

        if (null === $kind || null === $pubkey || !is_string($identifier) || (null !== $relayHint && !is_string($relayHint))) {
            return null;
        }

        return self::tryFrom($kind, $pubkey, $identifier)?->withRelayHint(RelayUrl::tryFromString($relayHint));
    }
}
