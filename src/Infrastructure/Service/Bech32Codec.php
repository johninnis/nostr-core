<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use InvalidArgumentException;

final class Bech32Codec
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    private const CHARKEY = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        15, -1, 10, 17, 21, 20, 26, 30, 7, 5, -1, -1, -1, -1, -1, -1,
        -1, 29, -1, 24, 13, 25, 9, 8, 23, -1, 18, 22, 31, 27, 19, -1,
        1, 0, 3, 16, 11, 28, 12, 14, 6, 4, 2, -1, -1, -1, -1, -1,
        -1, 29, -1, 24, 13, 25, 9, 8, 23, -1, 18, 22, 31, 27, 19, -1,
        1, 0, 3, 16, 11, 28, 12, 14, 6, 4, 2, -1, -1, -1, -1, -1,
    ];
    private const GENERATOR = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
    private const MAX_LENGTH = 5000;
    private const CHECKSUM_LENGTH = 6;

    public static function encodeNpub(string $hex): string
    {
        return self::encodeHex('npub', $hex);
    }

    public static function encodeNsec(string $hex): string
    {
        return self::encodeHex('nsec', $hex);
    }

    public static function encodeNote(string $hex): string
    {
        return self::encodeHex('note', $hex);
    }

    public static function encodeNaddr(string $identifier, string $pubkeyHex, int $kind, array $relays = []): string
    {
        $bytes = self::encodeTlv(
            [self::utf8ToBytes($identifier)],
            array_map([self::class, 'utf8ToBytes'], $relays),
            [self::hexToBytes($pubkeyHex)],
            [self::integerToBytes($kind)],
        );

        return self::encode('naddr', $bytes);
    }

    public static function decodeToHex(string $bech32): string
    {
        $decoded = self::decode($bech32);

        return self::bytesToHex($decoded['data']);
    }

    public static function decodeToTlv(string $bech32): array
    {
        $decoded = self::decode($bech32);
        $hrp = $decoded['hrp'];

        if ('npub' === $hrp || 'nsec' === $hrp || 'note' === $hrp) {
            return [
                'type' => $hrp,
                'data' => self::bytesToHex($decoded['data']),
            ];
        }

        $tlv = self::parseTlv($decoded['data']);

        return match ($hrp) {
            'nprofile' => [
                'type' => 'profile',
                'pubkey' => isset($tlv[0][0]) ? self::bytesToHex($tlv[0][0]) : '',
                'relays' => self::parseTlvRelays($tlv),
            ],
            'nevent' => [
                'type' => 'event',
                'event_id' => isset($tlv[0][0]) ? self::bytesToHex($tlv[0][0]) : '',
                'relays' => self::parseTlvRelays($tlv),
                'author' => isset($tlv[2][0]) ? self::bytesToHex($tlv[2][0]) : null,
                'kind' => isset($tlv[3][0]) ? self::bytesToInteger($tlv[3][0]) : null,
            ],
            'naddr' => [
                'type' => 'address',
                'identifier' => isset($tlv[0][0]) ? self::bytesToUtf8($tlv[0][0]) : '',
                'pubkey' => isset($tlv[2][0]) ? self::bytesToHex($tlv[2][0]) : '',
                'kind' => isset($tlv[3][0]) ? self::bytesToInteger($tlv[3][0]) : null,
                'relays' => self::parseTlvRelays($tlv),
            ],
            default => throw new InvalidArgumentException("Unknown bech32 prefix: {$hrp}"),
        };
    }

    private static function encodeHex(string $hrp, string $hex): string
    {
        return self::encode($hrp, self::hexToBytes($hex));
    }

    private static function encode(string $hrp, array $data): string
    {
        $words = self::convertBits($data, 8, 5, true);
        $polymod = self::createChecksum($hrp, $words);

        $encoded = $hrp.'1';
        foreach (array_merge($words, $polymod) as $value) {
            $encoded .= self::CHARSET[$value];
        }

        return $encoded;
    }

    private static function decode(string $bech32): array
    {
        $length = strlen($bech32);

        if ($length < 8 || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException("Invalid bech32 string length: {$length}");
        }

        $unpacked = unpack('C*', $bech32);
        if (false === $unpacked) {
            throw new InvalidArgumentException('Failed to unpack bech32 string');
        }
        $chars = array_values($unpacked);

        $hasUpper = false;
        $hasLower = false;
        $separatorPosition = -1;

        for ($i = 0; $i < $length; ++$i) {
            $char = $chars[$i];
            if ($char < 33 || $char > 126) {
                throw new InvalidArgumentException('Invalid character in bech32 string');
            }
            if ($char >= 0x61 && $char <= 0x7a) {
                $hasLower = true;
            }
            if ($char >= 0x41 && $char <= 0x5a) {
                $hasUpper = true;
                $chars[$i] = $char + 0x20;
            }
            if (0x31 === $char) {
                $separatorPosition = $i;
            }
        }

        if ($hasUpper && $hasLower) {
            throw new InvalidArgumentException('Mixed case in bech32 string');
        }
        if (-1 === $separatorPosition || $separatorPosition < 1) {
            throw new InvalidArgumentException('Missing separator in bech32 string');
        }
        if ($separatorPosition + 7 > $length) {
            throw new InvalidArgumentException('Checksum too short');
        }

        $hrp = pack('C*', ...array_slice($chars, 0, $separatorPosition));
        $data = array_map(
            fn (int $char): int => ($char & 0x80) ? -1 : self::CHARKEY[$char],
            array_slice($chars, $separatorPosition + 1)
        );

        if (!self::verifyChecksum($hrp, $data)) {
            throw new InvalidArgumentException('Invalid bech32 checksum');
        }

        $stripped = array_slice($data, 0, -self::CHECKSUM_LENGTH);

        return [
            'hrp' => $hrp,
            'data' => self::convertBits($stripped, 5, 8, false),
        ];
    }

    private static function convertBits(array $data, int $fromBits, int $toBits, bool $pad): array
    {
        $acc = 0;
        $bits = 0;
        $result = [];
        $maxValue = (1 << $toBits) - 1;
        $maxAcc = (1 << ($fromBits + $toBits - 1)) - 1;

        foreach ($data as $value) {
            if ($value < 0 || $value >> $fromBits) {
                throw new InvalidArgumentException('Invalid value for bit conversion');
            }
            $acc = (($acc << $fromBits) | $value) & $maxAcc;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $result[] = ($acc >> $bits) & $maxValue;
            }
        }

        if ($pad) {
            if ($bits > 0) {
                $result[] = ($acc << ($toBits - $bits)) & $maxValue;
            }
        } elseif ($bits >= $fromBits || (($acc << ($toBits - $bits)) & $maxValue)) {
            throw new InvalidArgumentException('Invalid padding in bit conversion');
        }

        return $result;
    }

    private static function polymod(array $values): int
    {
        $chk = 1;
        foreach ($values as $value) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1ffffff) << 5) ^ $value;
            for ($j = 0; $j < 5; ++$j) {
                $chk ^= (($top >> $j) & 1) ? self::GENERATOR[$j] : 0;
            }
        }

        return $chk;
    }

    private static function hrpExpand(string $hrp): array
    {
        $length = strlen($hrp);
        $expand1 = [];
        $expand2 = [];
        for ($i = 0; $i < $length; ++$i) {
            $ord = ord($hrp[$i]);
            $expand1[] = $ord >> 5;
            $expand2[] = $ord & 31;
        }

        return array_merge($expand1, [0], $expand2);
    }

    private static function verifyChecksum(string $hrp, array $data): bool
    {
        return 1 === self::polymod(array_merge(self::hrpExpand($hrp), $data));
    }

    private static function createChecksum(string $hrp, array $data): array
    {
        $values = array_merge(self::hrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $polymod = self::polymod($values) ^ 1;
        $checksum = [];
        for ($i = 0; $i < self::CHECKSUM_LENGTH; ++$i) {
            $checksum[] = ($polymod >> (5 * (5 - $i))) & 31;
        }

        return $checksum;
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

    private static function parseTlvRelays(array $tlv): array
    {
        if (!isset($tlv[1])) {
            return [];
        }

        return array_map([self::class, 'bytesToUtf8'], $tlv[1]);
    }

    private static function hexToBytes(string $hex): array
    {
        return array_map('hexdec', str_split($hex, 2));
    }

    private static function bytesToHex(array $bytes): string
    {
        $hex = '';
        foreach ($bytes as $byte) {
            $hex .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
        }

        return $hex;
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
        return (int) hexdec(self::bytesToHex($bytes));
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
