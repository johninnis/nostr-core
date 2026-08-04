<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class Npub implements Nip19EntityInterface
{
    private function __construct(private PublicKey $publicKey)
    {
    }

    public static function fromPublicKey(PublicKey $publicKey): self
    {
        return new self($publicKey);
    }

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }

    #[Override]
    public function type(): Nip19EntityType
    {
        return Nip19EntityType::Pubkey;
    }

    // Deliberate: the bech32 form and its hrp belong to the value object this wraps; this leaf exists only to make the sum type total over NIP-19 prefixes and must not restate either — see ADR-0060
    #[Override]
    public function toBech32(): string
    {
        return $this->publicKey->toBech32();
    }
}
