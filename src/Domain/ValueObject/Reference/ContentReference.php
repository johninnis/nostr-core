<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nip19EntityInterface;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Note;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Npub;
use InvalidArgumentException;

final readonly class ContentReference
{
    public function __construct(
        private ContentReferenceType $type,
        private string $rawText,
        private string $identifier,
        private int $position,
        private ?Nip19EntityInterface $decoded = null,
    ) {
        if ($position < 0) {
            throw new InvalidArgumentException('Position must be non-negative');
        }
    }

    public function getType(): ContentReferenceType
    {
        return $this->type;
    }

    public function getRawText(): string
    {
        return $this->rawText;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getDecoded(): ?Nip19EntityInterface
    {
        return $this->decoded;
    }

    public function getDecodedType(): ?Nip19EntityType
    {
        return $this->decoded?->type();
    }

    // Deliberate: the flattening lives here, where this type's wire shape needs it, rather than as nullable getters every entity would have to answer — see ADR-0060
    public function getEventId(): ?EventId
    {
        return match (true) {
            $this->decoded instanceof Note => $this->decoded->getEventId(),
            $this->decoded instanceof Nevent => $this->decoded->getEventId(),
            default => null,
        };
    }

    public function getPublicKey(): ?PublicKey
    {
        return match (true) {
            $this->decoded instanceof Npub => $this->decoded->getPublicKey(),
            $this->decoded instanceof Nprofile => $this->decoded->getPublicKey(),
            $this->decoded instanceof Nevent => $this->decoded->getAuthor(),
            $this->decoded instanceof Naddr => $this->decoded->getCoordinate()->getPubkey(),
            default => null,
        };
    }

    public function getRelays(): RelayUrlCollection
    {
        return match (true) {
            $this->decoded instanceof Nprofile => $this->decoded->getRelays(),
            $this->decoded instanceof Nevent => $this->decoded->getRelays(),
            $this->decoded instanceof Naddr => $this->decoded->getRelays(),
            default => new RelayUrlCollection(),
        };
    }

    public function getAddressableIdentifier(): ?string
    {
        return $this->decoded instanceof Naddr ? $this->decoded->getCoordinate()->getIdentifier() : null;
    }

    public function getKind(): ?EventKind
    {
        return match (true) {
            $this->decoded instanceof Nevent => $this->decoded->getKind(),
            $this->decoded instanceof Naddr => $this->decoded->getCoordinate()->getKind(),
            default => null,
        };
    }

    public function isEventReference(): bool
    {
        return null !== $this->getEventId();
    }

    public function isPubkeyReference(): bool
    {
        return null !== $this->getPublicKey();
    }

    public function isAddressableReference(): bool
    {
        return $this->decoded instanceof Naddr;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'raw_text' => $this->rawText,
            'identifier' => $this->identifier,
            'position' => $this->position,
            'decoded_type' => $this->getDecodedType()?->value,
            'event_id' => $this->getEventId()?->toHex(),
            'public_key' => $this->getPublicKey()?->toHex(),
            'relays' => $this->getRelays()->toStrings(),
            'addressable_identifier' => $this->getAddressableIdentifier(),
            'kind' => $this->getKind()?->toInt(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function tryFromArray(array $data): ?self
    {
        $type = ContentReferenceType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '');
        $rawText = $data['raw_text'] ?? null;
        $identifier = $data['identifier'] ?? null;
        $position = $data['position'] ?? null;

        if (null === $type || !is_string($rawText) || !is_string($identifier) || !is_int($position) || $position < 0) {
            return null;
        }

        return new self($type, $rawText, $identifier, $position, self::tryDecodedFromArray($data));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    // Deliberate: rebuilds through the entity's own named constructor, so a row missing a field that entity requires yields no entity rather than a partly-populated one — see ADR-0060
    private static function tryDecodedFromArray(array $data): ?Nip19EntityInterface
    {
        $decodedType = Nip19EntityType::tryFrom(is_string($data['decoded_type'] ?? null) ? $data['decoded_type'] : '');

        if (null === $decodedType) {
            return null;
        }

        $publicKey = isset($data['public_key']) && is_string($data['public_key']) ? PublicKey::tryFromHex($data['public_key']) : null;
        $eventId = isset($data['event_id']) && is_string($data['event_id']) ? EventId::tryFromHex($data['event_id']) : null;
        $kind = isset($data['kind']) && is_int($data['kind']) ? EventKind::tryFromInt($data['kind']) : null;
        $relays = RelayUrlCollection::fromStrings($data['relays'] ?? null);

        return match ($decodedType) {
            Nip19EntityType::Pubkey => null === $publicKey ? null : Npub::fromPublicKey($publicKey),
            Nip19EntityType::Note => null === $eventId ? null : Note::fromEventId($eventId),
            Nip19EntityType::Profile => null === $publicKey ? null : Nprofile::tryFromPublicKey($publicKey, $relays),
            Nip19EntityType::Event => null === $eventId ? null : Nevent::tryFromEventId($eventId, $relays, $publicKey, $kind),
            Nip19EntityType::Address => self::tryAddress(self::tryCoordinate($kind, $publicKey, $data['addressable_identifier'] ?? null), $relays),
        };
    }

    private static function tryCoordinate(?EventKind $kind, ?PublicKey $publicKey, mixed $identifier): ?EventCoordinate
    {
        return null === $kind || null === $publicKey || !is_string($identifier)
            ? null
            : EventCoordinate::tryFrom($kind, $publicKey, $identifier);
    }

    private static function tryAddress(?EventCoordinate $coordinate, RelayUrlCollection $relays): ?Naddr
    {
        return null === $coordinate ? null : Naddr::tryFromCoordinate($coordinate, $relays);
    }
}
