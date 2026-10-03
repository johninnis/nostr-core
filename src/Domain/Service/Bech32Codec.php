<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\Bech32Variant;

final class Bech32Codec
{
    private const string CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    /** @var list<int> */
    private const array CHARKEY = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        15, -1, 10, 17, 21, 20, 26, 30, 7, 5, -1, -1, -1, -1, -1, -1,
        -1, 29, -1, 24, 13, 25, 9, 8, 23, -1, 18, 22, 31, 27, 19, -1,
        1, 0, 3, 16, 11, 28, 12, 14, 6, 4, 2, -1, -1, -1, -1, -1,
        -1, 29, -1, 24, 13, 25, 9, 8, 23, -1, 18, 22, 31, 27, 19, -1,
        1, 0, 3, 16, 11, 28, 12, 14, 6, 4, 2, -1, -1, -1, -1, -1,
    ];
    /** @var list<int> */
    private const array GENERATOR = [0x3B6A57B2, 0x26508E6D, 0x1EA119FA, 0x3D4233DD, 0x2A1462B3];
    private const int MAX_LENGTH = 5000;
    private const int MAX_HRP_LENGTH = 83;
    private const string ENCODABLE_HRP = '/^[\x21-\x40\x5B-\x7E]{1,'.self::MAX_HRP_LENGTH.'}$/D';
    private const int CHECKSUM_LENGTH = 6;

    private function __construct()
    {
    }

    /**
     * @return non-empty-string|null
     */
    public static function encode(string $hrp, string $bytes, Bech32Variant $variant = Bech32Variant::Bech32): ?string
    {
        if (1 !== preg_match(self::ENCODABLE_HRP, $hrp)) {
            return null;
        }

        $byteValues = '' === $bytes ? [] : array_values((array) unpack('C*', $bytes));
        $words = self::toWords($byteValues);

        if (strlen($hrp) + 1 + count($words) + self::CHECKSUM_LENGTH > self::MAX_LENGTH) {
            return null;
        }

        $checksum = self::createChecksum($hrp, $words, $variant);

        $encoded = $hrp.'1';
        foreach ([...$words, ...$checksum] as $value) {
            $encoded .= self::CHARSET[$value];
        }

        return $encoded;
    }

    /**
     * @return array{hrp: string, data: string}|null
     */
    public static function decode(string $bech32, Bech32Variant $variant = Bech32Variant::Bech32): ?array
    {
        $length = strlen($bech32);

        if ($length < 8 || $length > self::MAX_LENGTH) {
            return null;
        }

        $unpacked = unpack('C*', $bech32);
        if (false === $unpacked) {
            return null;
        }
        $chars = array_values($unpacked);

        $hasUpper = false;
        $hasLower = false;
        $separatorPosition = -1;

        for ($i = 0; $i < $length; ++$i) {
            $char = $chars[$i];
            if ($char < 33 || $char > 126) {
                return null;
            }
            if ($char >= 0x61 && $char <= 0x7A) {
                $hasLower = true;
            }
            if ($char >= 0x41 && $char <= 0x5A) {
                $hasUpper = true;
                $chars[$i] = $char + 0x20;
            }
            if (0x31 === $char) {
                $separatorPosition = $i;
            }
        }

        if ($hasUpper && $hasLower) {
            return null;
        }
        if ($separatorPosition < 1 || $separatorPosition > self::MAX_HRP_LENGTH) {
            return null;
        }
        if ($separatorPosition + 7 > $length) {
            return null;
        }

        $hrp = pack('C*', ...array_slice($chars, 0, $separatorPosition));
        $data = array_map(
            static fn (int $char): int => self::CHARKEY[$char],
            array_slice($chars, $separatorPosition + 1)
        );

        if (in_array(-1, $data, true)) {
            return null;
        }

        if ($variant->value !== self::polymod(array_merge(self::hrpExpand($hrp), $data))) {
            return null;
        }

        $stripped = array_slice($data, 0, -self::CHECKSUM_LENGTH);

        $payload = self::fromWords($stripped);
        if (null === $payload) {
            return null;
        }

        return [
            'hrp' => $hrp,
            'data' => pack('C*', ...$payload),
        ];
    }

    public static function decodeWithHrp(string $bech32, string $expectedHrp, Bech32Variant $variant = Bech32Variant::Bech32): ?string
    {
        $decoded = self::decode($bech32, $variant);

        return null !== $decoded && $expectedHrp === $decoded['hrp'] ? $decoded['data'] : null;
    }

    /**
     * @param list<int> $bytes
     *
     * @return list<int>
     */
    private static function toWords(array $bytes): array
    {
        [$words, $accumulator, $bits] = self::regroup($bytes, 8, 5);

        return $bits > 0 ? [...$words, ($accumulator << (5 - $bits)) & 0x1F] : $words;
    }

    /**
     * @param list<int> $words
     *
     * @return list<int>|null
     */
    private static function fromWords(array $words): ?array
    {
        [$bytes, $accumulator, $bits] = self::regroup($words, 5, 8);

        return $bits >= 5 || (($accumulator << (8 - $bits)) & 0xFF) ? null : $bytes;
    }

    /**
     * @param list<int> $data
     *
     * @return array{list<int>, int, int}
     */
    private static function regroup(array $data, int $fromBits, int $toBits): array
    {
        $acc = 0;
        $bits = 0;
        $result = [];
        $maxValue = (1 << $toBits) - 1;
        $maxAcc = (1 << ($fromBits + $toBits - 1)) - 1;

        foreach ($data as $value) {
            $acc = (($acc << $fromBits) | $value) & $maxAcc;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $result[] = ($acc >> $bits) & $maxValue;
            }
        }

        return [$result, $acc, $bits];
    }

    /**
     * @param list<int> $values
     */
    private static function polymod(array $values): int
    {
        $chk = 1;
        foreach ($values as $value) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1FFFFFF) << 5) ^ $value;
            for ($j = 0; $j < 5; ++$j) {
                $chk ^= (($top >> $j) & 1) ? self::GENERATOR[$j] : 0;
            }
        }

        return $chk;
    }

    /**
     * @return list<int>
     */
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

    /**
     * @param list<int> $data
     *
     * @return list<int>
     */
    private static function createChecksum(string $hrp, array $data, Bech32Variant $variant): array
    {
        $values = array_merge(self::hrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $polymod = self::polymod($values) ^ $variant->value;
        $checksum = [];
        for ($i = 0; $i < self::CHECKSUM_LENGTH; ++$i) {
            $checksum[] = ($polymod >> (5 * (5 - $i))) & 31;
        }

        return $checksum;
    }
}
