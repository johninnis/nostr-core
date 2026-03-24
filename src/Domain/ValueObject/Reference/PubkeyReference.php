<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use RuntimeException;

final readonly class PubkeyReference
{
    public function __construct(
        private PublicKey $pubkey,
        private ?RelayUrl $relayUrl = null,
        private ?string $petname = null,
    ) {
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function getRelayUrl(): ?RelayUrl
    {
        return $this->relayUrl;
    }

    public function getPetname(): ?string
    {
        return $this->petname;
    }

    public function toArray(): array
    {
        return [
            'pubkey' => $this->pubkey->toHex(),
            'relay_url' => null !== $this->relayUrl ? (string) $this->relayUrl : null,
            'petname' => $this->petname,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            PublicKey::fromHex($data['pubkey']) ?? throw new RuntimeException('Corrupt pubkey in serialised PubkeyReference'),
            isset($data['relay_url']) ? RelayUrl::fromString($data['relay_url']) : null,
            $data['petname'] ?? null
        );
    }
}
