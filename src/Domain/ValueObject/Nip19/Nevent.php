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

    // Deliberate: the encoded form is built and validated in the named constructor, so toBech32 is total — see ADR-0060
    private function __construct(
        private EventId $eventId,
        private RelayUrlCollection $relays,
        private ?PublicKey $author,
        private ?EventKind $kind,
        private string $bech32,
    ) {
    }

    // Deliberate: author and kind are optional in NIP-19 for nevent, so their absence is the spec's shape rather than an unpopulated field — see ADR-0060
    public static function tryFromEventId(
        EventId $eventId,
        RelayUrlCollection $relays = new RelayUrlCollection(),
        ?PublicKey $author = null,
        ?EventKind $kind = null,
    ): ?self {
        $records = [['type' => Nip19Tlv::TYPE_SPECIAL, 'value' => $eventId->toBytes()]];

        foreach ($relays as $relay) {
            $records[] = ['type' => Nip19Tlv::TYPE_RELAY, 'value' => (string) $relay];
        }

        if (null !== $author) {
            $records[] = ['type' => Nip19Tlv::TYPE_AUTHOR, 'value' => $author->toBytes()];
        }

        if (null !== $kind) {
            $records[] = ['type' => Nip19Tlv::TYPE_KIND, 'value' => Nip19Tlv::encodeKind($kind)];
        }

        $tlv = Nip19Tlv::tryFromRecords($records);

        return null === $tlv ? null : new self($eventId, $relays, $author, $kind, Bech32Codec::encode(self::HRP, $tlv->toBytes()));
    }

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

        $special = $tlv->first(Nip19Tlv::TYPE_SPECIAL);
        $eventId = null === $special ? null : EventId::tryFromBytes($special);

        if (null === $eventId) {
            return null;
        }

        $authorBytes = $tlv->first(Nip19Tlv::TYPE_AUTHOR);
        $author = null === $authorBytes ? null : PublicKey::tryFromBytes($authorBytes);
        $kind = $tlv->kind();

        // Deliberate: author and kind are optional, but a record that is present and malformed is corruption, not absence — reporting it as absent would let a consumer act on a claim the payload never made; relay hints stay best-effort and drop individually — see ADR-0060
        if ((null !== $authorBytes && null === $author) || (null !== $tlv->first(Nip19Tlv::TYPE_KIND) && null === $kind)) {
            return null;
        }

        return self::tryFromEventId(
            $eventId,
            RelayUrlCollection::fromStrings($tlv->all(Nip19Tlv::TYPE_RELAY)),
            $author,
            $kind,
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
