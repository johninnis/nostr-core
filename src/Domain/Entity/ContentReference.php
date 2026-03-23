<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Content\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

final readonly class ContentReference
{
    public function __construct(
        private ContentReferenceType $type,
        private string $rawText,
        private string $identifier,
        private int $position,
        private string $decodedType,
        private ?EventId $eventId = null,
        private ?PublicKey $publicKey = null,
        private array $relays = [],
        private ?string $error = null,
        private ?string $addressableIdentifier = null,
        private ?int $kind = null
    ) {
        if ($position < 0) {
            throw new \InvalidArgumentException('Position must be non-negative');
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
        return $this->decodedType;
    }

    public function getEventId(): ?EventId
    {
        return $this->eventId;
    }

    public function getPublicKey(): ?PublicKey
    {
        return $this->publicKey;
    }

    public function getRelays(): array
    {
        return $this->relays;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function isEventReference(): bool
    {
        return $this->eventId !== null;
    }

    public function isPubkeyReference(): bool
    {
        return $this->publicKey !== null;
    }

    public function getAddressableIdentifier(): ?string
    {
        return $this->addressableIdentifier;
    }

    public function getKind(): ?int
    {
        return $this->kind;
    }

    public function isAddressableReference(): bool
    {
        return $this->addressableIdentifier !== null && $this->kind !== null && $this->publicKey !== null;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'raw_text' => $this->rawText,
            'identifier' => $this->identifier,
            'position' => $this->position,
            'decoded_type' => $this->decodedType,
            'event_id' => $this->eventId?->toHex(),
            'public_key' => $this->publicKey?->toHex(),
            'relays' => array_map(fn (RelayUrl $relay) => (string) $relay, $this->relays),
            'error' => $this->error,
            'addressable_identifier' => $this->addressableIdentifier,
            'kind' => $this->kind
        ];
    }

    public static function fromArray(array $data): self
    {
        $relays = [];
        if (isset($data['relays']) && \is_array($data['relays'])) {
            $relays = array_values(array_filter(array_map(fn (string $url) => RelayUrl::fromString($url), $data['relays'])));
        }

        return new self(
            ContentReferenceType::from($data['type']),
            $data['raw_text'],
            $data['identifier'],
            $data['position'],
            $data['decoded_type'],
            isset($data['event_id']) ? EventId::fromHex($data['event_id']) : null,
            isset($data['public_key']) ? PublicKey::fromHex($data['public_key']) : null,
            $relays,
            $data['error'] ?? null,
            $data['addressable_identifier'] ?? null,
            $data['kind'] ?? null
        );
    }
}
