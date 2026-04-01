<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final class Bech32EncoderAdapter implements Bech32EncoderInterface
{
    public function decodeComplexEntity(string $bech32): array
    {
        $decoded = Bech32Codec::decode($bech32);
        $hrp = $decoded['hrp'];
        $data = $decoded['data'];

        return match ($hrp) {
            'npub' => [
                'type' => 'pubkey',
                'pubkey' => Bech32Codec::bytesToHex($data),
            ],
            'note' => [
                'type' => 'event',
                'event_id' => Bech32Codec::bytesToHex($data),
            ],
            'nprofile' => $this->decodeProfile($data),
            'nevent' => $this->decodeEvent($data),
            'naddr' => $this->decodeAddress($data),
            default => throw new InvalidArgumentException("Unknown bech32 prefix: {$hrp}"),
        };
    }

    public function encodeAddressableEvent(string $identifier, PublicKey $pubkey, int $kind, array $relays = []): string
    {
        $bytes = self::encodeTlv(
            [self::utf8ToBytes($identifier)],
            array_map(self::utf8ToBytes(...), $relays),
            [Bech32Codec::hexToBytes($pubkey->toHex())],
            [self::integerToBytes($kind)],
        );

        return Bech32Codec::encode('naddr', $bytes);
    }

    public function parseEventReference(string $input): EventId|EventCoordinate|null
    {
        if (str_starts_with($input, 'naddr1')) {
            $decoded = $this->decodeComplexEntity($input);

            if (!isset($decoded['kind'], $decoded['pubkey'], $decoded['identifier'])) {
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

    private function decodeProfile(array $data): array
    {
        $tlv = self::parseTlv($data);

        return [
            'type' => 'profile',
            'pubkey' => isset($tlv[0][0]) ? Bech32Codec::bytesToHex($tlv[0][0]) : '',
            'relays' => self::extractRelays($tlv),
        ];
    }

    private function decodeEvent(array $data): array
    {
        $tlv = self::parseTlv($data);

        return [
            'type' => 'event',
            'event_id' => isset($tlv[0][0]) ? Bech32Codec::bytesToHex($tlv[0][0]) : '',
            'relays' => self::extractRelays($tlv),
            'author' => isset($tlv[2][0]) ? Bech32Codec::bytesToHex($tlv[2][0]) : null,
            'kind' => isset($tlv[3][0]) ? self::bytesToInteger($tlv[3][0]) : null,
        ];
    }

    private function decodeAddress(array $data): array
    {
        $tlv = self::parseTlv($data);

        return [
            'type' => 'address',
            'identifier' => isset($tlv[0][0]) ? self::bytesToUtf8($tlv[0][0]) : '',
            'pubkey' => isset($tlv[2][0]) ? Bech32Codec::bytesToHex($tlv[2][0]) : '',
            'kind' => isset($tlv[3][0]) ? self::bytesToInteger($tlv[3][0]) : null,
            'relays' => self::extractRelays($tlv),
        ];
    }

    private static function parseTlv(array $bytes): array
    {
        $result = [];
        $position = 0;
        $count = count($bytes);

        while ($position < $count) {
            $type = $bytes[$position++];
            $length = $bytes[$position++];
            $value = array_slice($bytes, $position, $length);
            if (count($value) < $length) {
                throw new InvalidArgumentException("Not enough data for TLV type {$type}");
            }
            $position += $length;
            $result[$type] ??= [];
            $result[$type][] = $value;
        }

        return $result;
    }

    private static function encodeTlv(array ...$tlvEntries): array
    {
        $result = [];
        foreach ($tlvEntries as $type => $values) {
            foreach ($values as $value) {
                $result[] = $type;
                $result[] = count($value);
                array_push($result, ...$value);
            }
        }

        return $result;
    }

    private static function extractRelays(array $tlv): array
    {
        if (!isset($tlv[1])) {
            return [];
        }

        return array_map(self::bytesToUtf8(...), $tlv[1]);
    }

    private static function bytesToUtf8(array $bytes): string
    {
        $utf8 = '';
        foreach ($bytes as $byte) {
            $utf8 .= chr($byte);
        }

        return $utf8;
    }

    private static function utf8ToBytes(string $utf8): array
    {
        return array_map('ord', mb_str_split($utf8));
    }

    private static function bytesToInteger(array $bytes): int
    {
        return (int) hexdec(Bech32Codec::bytesToHex($bytes));
    }

    private static function integerToBytes(int $integer): array
    {
        return [
            ($integer >> 24) & 0xff,
            ($integer >> 16) & 0xff,
            ($integer >> 8) & 0xff,
            $integer & 0xff,
        ];
    }
}
