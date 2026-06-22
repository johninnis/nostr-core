<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;
use Override;

final class Nip19Codec implements Nip19CodecInterface
{
    #[Override]
    public function decodeComplexEntity(string $bech32): ?DecodedNip19Entity
    {
        $decoded = Bech32Codec::decode($bech32);
        if (null === $decoded) {
            return null;
        }

        $data = $decoded['data'];

        return match ($decoded['hrp']) {
            'npub' => new DecodedNip19Entity(Nip19EntityType::Pubkey, publicKey: PublicKey::fromBytes($data)),
            'note' => new DecodedNip19Entity(Nip19EntityType::Event, eventId: EventId::fromBytes($data)),
            'nprofile' => $this->decodeProfile($data),
            'nevent' => $this->decodeEvent($data),
            'naddr' => $this->decodeAddress($data),
            default => null,
        };
    }

    #[Override]
    public function encodeAddressableEvent(string $identifier, PublicKey $pubkey, int $kind, array $relays = []): string
    {
        $bytes = self::tlvEntry(0, $identifier);
        foreach ($relays as $relay) {
            $bytes .= self::tlvEntry(1, (string) $relay);
        }
        $bytes .= self::tlvEntry(2, $pubkey->toBytes());
        $bytes .= self::tlvEntry(3, self::integerToBytes($kind));

        return Bech32Codec::encode('naddr', $bytes);
    }

    #[Override]
    public function parseEventReference(string $input): EventId|EventCoordinate|null
    {
        if (str_starts_with($input, 'naddr1')) {
            return $this->parseAddressableReference($input);
        }

        if (str_starts_with($input, 'note1') || str_starts_with($input, 'nevent1')) {
            return $this->decodeComplexEntity($input)?->getEventId();
        }

        return EventId::fromHex($input);
    }

    private function parseAddressableReference(string $input): ?EventCoordinate
    {
        $decoded = $this->decodeComplexEntity($input);
        $kind = $decoded?->getKind();
        $publicKey = $decoded?->getPublicKey();
        $identifier = $decoded?->getIdentifier();

        if (null === $kind || null === $publicKey || null === $identifier) {
            return null;
        }

        $firstRelay = $decoded->getRelays()->toArray()[0] ?? null;

        return EventCoordinate::fromParts(
            $kind->toInt(),
            $publicKey->toHex(),
            $identifier,
            null !== $firstRelay ? (string) $firstRelay : null,
        );
    }

    private function decodeProfile(string $data): ?DecodedNip19Entity
    {
        $tlv = self::parseTlv($data);
        if (null === $tlv) {
            return null;
        }

        return new DecodedNip19Entity(
            Nip19EntityType::Profile,
            publicKey: isset($tlv[0][0]) ? PublicKey::fromBytes($tlv[0][0]) : null,
            relays: self::relayCollection($tlv),
        );
    }

    private function decodeEvent(string $data): ?DecodedNip19Entity
    {
        $tlv = self::parseTlv($data);
        if (null === $tlv) {
            return null;
        }

        return new DecodedNip19Entity(
            Nip19EntityType::Event,
            publicKey: isset($tlv[2][0]) ? PublicKey::fromBytes($tlv[2][0]) : null,
            eventId: isset($tlv[0][0]) ? EventId::fromBytes($tlv[0][0]) : null,
            kind: self::decodeKind($tlv),
            relays: self::relayCollection($tlv),
        );
    }

    private function decodeAddress(string $data): ?DecodedNip19Entity
    {
        $tlv = self::parseTlv($data);
        if (null === $tlv) {
            return null;
        }

        return new DecodedNip19Entity(
            Nip19EntityType::Address,
            publicKey: isset($tlv[2][0]) ? PublicKey::fromBytes($tlv[2][0]) : null,
            identifier: $tlv[0][0] ?? null,
            kind: self::decodeKind($tlv),
            relays: self::relayCollection($tlv),
        );
    }

    private static function decodeKind(array $tlv): ?EventKind
    {
        if (!isset($tlv[3][0])) {
            return null;
        }

        $kind = self::bytesToInteger($tlv[3][0]);

        return $kind >= 0 && $kind <= 65535 ? EventKind::fromInt($kind) : null;
    }

    private static function relayCollection(array $tlv): RelayUrlCollection
    {
        $relays = [];
        foreach ($tlv[1] ?? [] as $relayString) {
            $relay = RelayUrl::fromString($relayString);
            if (null !== $relay) {
                $relays[] = $relay;
            }
        }

        return new RelayUrlCollection($relays);
    }

    private static function parseTlv(string $bytes): ?array
    {
        $result = [];
        $position = 0;
        $length = strlen($bytes);

        while ($position < $length) {
            $type = ord($bytes[$position++]);
            $valueLength = $position < $length ? ord($bytes[$position++]) : 0;
            $value = substr($bytes, $position, $valueLength);
            if (strlen($value) < $valueLength) {
                return null;
            }
            $position += $valueLength;
            $result[$type] ??= [];
            $result[$type][] = $value;
        }

        return $result;
    }

    private static function tlvEntry(int $type, string $value): string
    {
        return pack('CC', $type, strlen($value)).$value;
    }

    private static function bytesToInteger(string $bytes): int
    {
        $unpacked = unpack('N', str_pad($bytes, 4, "\x00", STR_PAD_LEFT));

        return false === $unpacked ? 0 : $unpacked[1];
    }

    private static function integerToBytes(int $integer): string
    {
        return pack('N', $integer);
    }
}
