<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class Naddr implements Nip19EntityInterface
{
    public const string HRP = 'naddr';

    // Deliberate: the encoded form is built and validated in the named constructor, so toBech32 is total — see ADR-0060
    private function __construct(
        private EventCoordinate $coordinate,
        private RelayUrlCollection $relays,
        private string $bech32,
    ) {
    }

    public static function tryFromCoordinate(EventCoordinate $coordinate, RelayUrlCollection $relays = new RelayUrlCollection()): ?self
    {
        // Deliberate: the coordinate's own relay hint leads the relay records, so it survives the round trip that reads the first relay back as the hint — see ADR-0060
        $hint = $coordinate->getRelayHint();
        $allRelays = (null === $hint ? $relays : new RelayUrlCollection([$hint])->merge($relays))->unique();

        $records = [['type' => Nip19Tlv::TYPE_SPECIAL, 'value' => $coordinate->getIdentifier()]];

        foreach ($allRelays as $relay) {
            $records[] = ['type' => Nip19Tlv::TYPE_RELAY, 'value' => (string) $relay];
        }

        $records[] = ['type' => Nip19Tlv::TYPE_AUTHOR, 'value' => $coordinate->getPubkey()->toBytes()];
        $records[] = ['type' => Nip19Tlv::TYPE_KIND, 'value' => Nip19Tlv::encodeKind($coordinate->getKind())];

        $tlv = Nip19Tlv::tryFromRecords($records);

        return null === $tlv ? null : new self($coordinate, $allRelays, Bech32Codec::encode(self::HRP, $tlv->toBytes()));
    }

    // Deliberate: NIP-19 makes author and kind mandatory for naddr, so a payload missing either has no coordinate and decodes to null rather than to a partly-populated entity — see ADR-0060
    // Deliberate: parses a string already known to be this entity; the codec answers the different unknown-prefix question over the same payload step — see ADR-0060
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

        $identifier = $tlv->first(Nip19Tlv::TYPE_SPECIAL);
        $authorBytes = $tlv->first(Nip19Tlv::TYPE_AUTHOR);
        $kind = $tlv->kind();
        $author = null === $authorBytes ? null : PublicKey::tryFromBytes($authorBytes);

        if (null === $identifier || null === $author || null === $kind) {
            return null;
        }

        $coordinate = EventCoordinate::tryFrom($kind, $author, $identifier);

        if (null === $coordinate) {
            return null;
        }

        return self::tryFromCoordinate($coordinate, RelayUrlCollection::fromStrings($tlv->all(Nip19Tlv::TYPE_RELAY)));
    }

    public function getCoordinate(): EventCoordinate
    {
        return $this->coordinate;
    }

    public function getRelays(): RelayUrlCollection
    {
        return $this->relays;
    }

    #[Override]
    public function type(): Nip19EntityType
    {
        return Nip19EntityType::Address;
    }

    #[Override]
    public function toBech32(): string
    {
        return $this->bech32;
    }
}
