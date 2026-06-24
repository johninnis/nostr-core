<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use InvalidArgumentException;

final readonly class ContentReference
{
    public function __construct(
        private ContentReferenceType $type,
        private string $rawText,
        private string $identifier,
        private int $position,
        private ?DecodedNip19Entity $decoded = null,
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

    public function getDecodedType(): string
    {
        return $this->decoded?->getType()->value ?? 'unknown';
    }

    public function getEventId(): ?EventId
    {
        return $this->decoded?->getEventId();
    }

    public function getPublicKey(): ?PublicKey
    {
        return $this->decoded?->getPublicKey();
    }

    public function getRelays(): RelayUrlCollection
    {
        return $this->decoded?->getRelays() ?? new RelayUrlCollection();
    }

    public function getAddressableIdentifier(): ?string
    {
        return $this->decoded?->getIdentifier();
    }

    public function getKind(): ?EventKind
    {
        return $this->decoded?->getKind();
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
        return null !== $this->getAddressableIdentifier() && null !== $this->getKind() && null !== $this->getPublicKey();
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'raw_text' => $this->rawText,
            'identifier' => $this->identifier,
            'position' => $this->position,
            'decoded_type' => $this->getDecodedType(),
            'event_id' => $this->getEventId()?->toHex(),
            'public_key' => $this->getPublicKey()?->toHex(),
            'relays' => $this->getRelays()->toStrings(),
            'addressable_identifier' => $this->getAddressableIdentifier(),
            'kind' => $this->getKind()?->toInt(),
        ];
    }

    public static function fromArray(array $data): ?self
    {
        $type = ContentReferenceType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '');
        $rawText = $data['raw_text'] ?? null;
        $identifier = $data['identifier'] ?? null;
        $position = $data['position'] ?? null;

        if (null === $type || !is_string($rawText) || !is_string($identifier) || !is_int($position) || $position < 0) {
            return null;
        }

        $relays = [];
        if (isset($data['relays']) && is_array($data['relays'])) {
            $relays = array_values(array_filter(array_map(
                static fn (mixed $url) => is_string($url) ? RelayUrl::fromString($url) : null,
                $data['relays'],
            )));
        }

        $addressableIdentifier = $data['addressable_identifier'] ?? null;

        $decodedType = Nip19EntityType::tryFrom(is_string($data['decoded_type'] ?? null) ? $data['decoded_type'] : '');

        $decoded = null === $decodedType ? null : new DecodedNip19Entity(
            type: $decodedType,
            publicKey: isset($data['public_key']) && is_string($data['public_key']) ? PublicKey::fromHex($data['public_key']) : null,
            eventId: isset($data['event_id']) && is_string($data['event_id']) ? EventId::fromHex($data['event_id']) : null,
            identifier: is_string($addressableIdentifier) ? $addressableIdentifier : null,
            kind: isset($data['kind']) && is_int($data['kind']) ? EventKind::tryFromInt($data['kind']) : null,
            relays: new RelayUrlCollection($relays),
        );

        return new self($type, $rawText, $identifier, $position, $decoded);
    }
}
