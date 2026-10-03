<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class Nprofile implements Nip19EntityInterface
{
    public const string HRP = 'nprofile';

    // Deliberate: the encoded form is built and validated in the named constructor, so toBech32 is total — see ADR-0104
    private function __construct(
        private PublicKey $publicKey,
        private RelayUrlCollection $relays,
        private string $bech32,
    ) {
    }

    public static function tryFromPublicKey(PublicKey $publicKey, RelayUrlCollection $relays = new RelayUrlCollection()): ?self
    {
        $uniqueRelays = $relays->unique();

        $records = [['type' => Nip19Tlv::TYPE_SPECIAL, 'value' => $publicKey->toBytes()]];

        foreach ($uniqueRelays as $relay) {
            $records[] = ['type' => Nip19Tlv::TYPE_RELAY, 'value' => (string) $relay];
        }

        $tlv = Nip19Tlv::tryFromRecords($records);

        $bech32 = null === $tlv ? null : Bech32Codec::encode(self::HRP, $tlv->toBytes());

        return null === $bech32 ? null : new self($publicKey, $uniqueRelays, $bech32);
    }

    // Deliberate: parses a string already known to be this entity; the codec answers the different unknown-prefix question over the same payload step — see ADR-0082
    public static function tryFromBech32(string $bech32): ?self
    {
        $payload = Bech32Codec::decodeWithHrp($bech32, self::HRP);

        return null === $payload ? null : self::tryFromPayload($payload);
    }

    public static function tryFromPayload(string $payload): ?self
    {
        $tlv = Nip19Tlv::tryFromBytes($payload);

        if (null === $tlv) {
            return null;
        }

        $special = $tlv->sole(Nip19Tlv::TYPE_SPECIAL);
        $publicKey = null === $special ? null : PublicKey::tryFromBytes($special);

        if (null === $publicKey) {
            return null;
        }

        return self::tryFromPublicKey($publicKey, RelayUrlCollection::fromStrings($tlv->all(Nip19Tlv::TYPE_RELAY)));
    }

    public function getPublicKey(): PublicKey
    {
        return $this->publicKey;
    }

    public function getRelays(): RelayUrlCollection
    {
        return $this->relays;
    }

    #[Override]
    public function type(): Nip19EntityType
    {
        return Nip19EntityType::Profile;
    }

    #[Override]
    public function toBech32(): string
    {
        return $this->bech32;
    }
}
