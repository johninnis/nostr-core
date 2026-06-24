<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pubkey' => $this->pubkey->toHex(),
            'relay_url' => null !== $this->relayUrl ? (string) $this->relayUrl : null,
            'petname' => $this->petname,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $pubkeyHex = $data['pubkey'] ?? null;
        if (!is_string($pubkeyHex)) {
            return null;
        }

        $pubkey = PublicKey::fromHex($pubkeyHex);
        if (null === $pubkey) {
            return null;
        }

        return new self(
            $pubkey,
            isset($data['relay_url']) && is_string($data['relay_url']) ? RelayUrl::fromString($data['relay_url']) : null,
            isset($data['petname']) && is_string($data['petname']) ? $data['petname'] : null,
        );
    }
}
