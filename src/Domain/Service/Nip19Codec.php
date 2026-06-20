<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

final class Nip19Codec implements Nip19CodecInterface
{
    #[Override]
    public function decodeComplexEntity(string $bech32): ?array
    {
        $decoded = Bech32Codec::decode($bech32);
        if (null === $decoded) {
            return null;
        }

        $data = $decoded['data'];

        return match ($decoded['hrp']) {
            'npub' => [
                'type' => 'pubkey',
                'pubkey' => HexCodec::fromBytes($data),
            ],
            'note' => [
                'type' => 'event',
                'event_id' => HexCodec::fromBytes($data),
            ],
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
            $decoded = $this->decodeComplexEntity($input);

            if (null === $decoded || !isset($decoded['kind'], $decoded['pubkey'], $decoded['identifier'])) {
                return null;
            }

            return EventCoordinate::fromParts(
                $decoded['kind'],
                $decoded['pubkey'],
                $decoded['identifier'],
                $decoded['relays'][0] ?? null
            );
        }

        if (str_starts_with($input, 'note1') || str_starts_with($input, 'nevent1')) {
            $decoded = $this->decodeComplexEntity($input);

            return isset($decoded['event_id']) ? EventId::fromHex($decoded['event_id']) : null;
        }

        return EventId::fromHex($input);
    }

    private function decodeProfile(string $data): ?array
    {
        $tlv = self::parseTlv($data);
        if (null === $tlv) {
            return null;
        }

        return [
            'type' => 'profile',
            'pubkey' => isset($tlv[0][0]) ? HexCodec::fromBytes($tlv[0][0]) : '',
            'relays' => self::extractRelays($tlv),
        ];
    }

    private function decodeEvent(string $data): ?array
    {
        $tlv = self::parseTlv($data);
        if (null === $tlv) {
            return null;
        }

        return [
            'type' => 'event',
            'event_id' => isset($tlv[0][0]) ? HexCodec::fromBytes($tlv[0][0]) : '',
            'relays' => self::extractRelays($tlv),
            'author' => isset($tlv[2][0]) ? HexCodec::fromBytes($tlv[2][0]) : null,
            'kind' => isset($tlv[3][0]) ? self::bytesToInteger($tlv[3][0]) : null,
        ];
    }

    private function decodeAddress(string $data): ?array
    {
        $tlv = self::parseTlv($data);
        if (null === $tlv) {
            return null;
        }

        return [
            'type' => 'address',
            'identifier' => $tlv[0][0] ?? '',
            'pubkey' => isset($tlv[2][0]) ? HexCodec::fromBytes($tlv[2][0]) : '',
            'kind' => isset($tlv[3][0]) ? self::bytesToInteger($tlv[3][0]) : null,
            'relays' => self::extractRelays($tlv),
        ];
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

    private static function extractRelays(array $tlv): array
    {
        return $tlv[1] ?? [];
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
