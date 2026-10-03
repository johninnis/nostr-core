<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class PubkeyReference
{
    public function __construct(
        private PublicKey $pubkey,
        private ?RelayUrl $relayUrl = null,
        private ?string $petname = null,
    ) {
    }

    // Deliberate: only a p tag carries a NIP-02 petname in its third element; a NIP-22 P tag names the root author and a relay — see ADR-0085
    public static function tryFromTag(Tag $tag): ?self
    {
        $pubkey = PublicKey::tryFromHex($tag->getValue(0) ?? '');

        return null === $pubkey ? null : new self(
            $pubkey,
            RelayUrl::tryFromString($tag->getValue(1)),
            $tag->getType()->is(TagType::PUBKEY) ? $tag->getValue(2) : null,
        );
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

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $pubkeyHex = $data['pubkey'] ?? null;
        if (!is_string($pubkeyHex)) {
            return null;
        }

        $pubkey = PublicKey::tryFromHex($pubkeyHex);
        if (null === $pubkey) {
            return null;
        }

        return new self(
            $pubkey,
            isset($data['relay_url']) && is_string($data['relay_url']) ? RelayUrl::tryFromString($data['relay_url']) : null,
            isset($data['petname']) && is_string($data['petname']) ? $data['petname'] : null,
        );
    }
}
