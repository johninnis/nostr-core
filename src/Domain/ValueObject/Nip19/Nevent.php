<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final readonly class Nevent implements Nip19EntityInterface
{
    public const string HRP = 'nevent';

    // Deliberate: the encoded form is built and validated in the named constructor, so toBech32 is total — see ADR-0104
    private function __construct(
        private EventId $eventId,
        private RelayUrlCollection $relays,
        private ?PublicKey $author,
        private ?EventKind $kind,
        private string $bech32,
    ) {
    }

    // Deliberate: author and kind are optional in NIP-19 for nevent, so their absence is the spec's shape rather than an unpopulated field — see ADR-0082
    public static function tryFromEventId(
        EventId $eventId,
        RelayUrlCollection $relays = new RelayUrlCollection(),
        ?PublicKey $author = null,
        ?EventKind $kind = null,
    ): ?self {
        $uniqueRelays = $relays->unique();

        $records = [['type' => Nip19Tlv::TYPE_SPECIAL, 'value' => $eventId->toBytes()]];

        foreach ($uniqueRelays as $relay) {
            $records[] = ['type' => Nip19Tlv::TYPE_RELAY, 'value' => (string) $relay];
        }

        if (null !== $author) {
            $records[] = ['type' => Nip19Tlv::TYPE_AUTHOR, 'value' => $author->toBytes()];
        }

        if (null !== $kind) {
            $records[] = ['type' => Nip19Tlv::TYPE_KIND, 'value' => Nip19Tlv::encodeKind($kind)];
        }

        $tlv = Nip19Tlv::tryFromRecords($records);

        $bech32 = null === $tlv ? null : Bech32Codec::encode(self::HRP, $tlv->toBytes());

        return null === $bech32 ? null : new self($eventId, $uniqueRelays, $author, $kind, $bech32);
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
        $eventId = null === $special ? null : EventId::tryFromBytes($special);
        $authors = array_map(PublicKey::tryFromBytes(...), $tlv->all(Nip19Tlv::TYPE_AUTHOR));
        $kinds = array_map(Nip19Tlv::decodeKind(...), $tlv->all(Nip19Tlv::TYPE_KIND));

        // Deliberate: author and kind are optional, but a record that is present and malformed is corruption, not absence — reporting it as absent would let a consumer act on a claim the payload never made; relay hints stay best-effort and drop individually — see nostr-adrs ADR-0094
        if (null === $eventId || in_array(null, $authors, true) || in_array(null, $kinds, true)) {
            return null;
        }

        return self::tryFromEventId(
            $eventId,
            RelayUrlCollection::fromStrings($tlv->all(Nip19Tlv::TYPE_RELAY)),
            null === $tlv->sole(Nip19Tlv::TYPE_AUTHOR) ? null : $authors[0],
            null === $tlv->sole(Nip19Tlv::TYPE_KIND) ? null : $kinds[0],
        );
    }

    public function getEventId(): EventId
    {
        return $this->eventId;
    }

    public function getRelays(): RelayUrlCollection
    {
        return $this->relays;
    }

    public function getAuthor(): ?PublicKey
    {
        return $this->author;
    }

    public function getKind(): ?EventKind
    {
        return $this->kind;
    }

    #[Override]
    public function type(): Nip19EntityType
    {
        return Nip19EntityType::Event;
    }

    #[Override]
    public function toBech32(): string
    {
        return $this->bech32;
    }
}
