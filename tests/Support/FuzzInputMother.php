<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use stdClass;

final class FuzzInputMother
{
    private const string BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    private const string VALID_HEX_32 = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    private const int MAX_ARRAY_DEPTH = 3;

    private function __construct()
    {
    }

    public static function hostileString(): string
    {
        return match (random_int(0, 4)) {
            0 => self::randomBytes(256),
            1 => bin2hex(self::randomBytes(96)),
            2 => self::bech32Shaped(),
            3 => self::asciiNoise(random_int(0, 256)),
            default => '',
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function hostileArray(): array
    {
        return self::randomArray(0);
    }

    /**
     * A near-valid event array with each field independently mutated, so the parser is driven deep
     * (past the required-field gate) rather than rejected at the first check.
     *
     * @return array<array-key, mixed>
     */
    public static function nearValidEventArray(): array
    {
        $event = [
            'id' => self::scalarOr(self::VALID_HEX_32),
            'pubkey' => self::scalarOr(self::VALID_HEX_32),
            'created_at' => self::scalarOr(random_int(0, 3_000_000_000)),
            'kind' => self::scalarOr(random_int(0, 40000)),
            'content' => self::scalarOr(self::asciiNoise(random_int(0, 64))),
            'sig' => self::scalarOr(self::VALID_HEX_32.self::VALID_HEX_32),
            'tags' => self::scalarOr(self::nearValidTags()),
        ];

        foreach (array_keys($event) as $field) {
            if (0 === random_int(0, 7)) {
                unset($event[$field]);
            }
        }

        return $event;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function nearValidFilterArray(): array
    {
        return array_filter([
            'ids' => self::scalarOr([self::scalarOr(self::VALID_HEX_32)]),
            'authors' => self::scalarOr([self::scalarOr(self::VALID_HEX_32)]),
            'kinds' => self::scalarOr([self::scalarOr(1)]),
            '#e' => self::scalarOr([self::scalarOr(self::VALID_HEX_32)]),
            'since' => self::hostileScalar(),
            'until' => self::hostileScalar(),
            'limit' => self::hostileScalar(),
            'search' => self::hostileScalar(),
        ], static fn (): bool => 0 !== random_int(0, 3));
    }

    /**
     * A protocol message as a list: a type tag followed by mutated payload elements.
     *
     * @param list<string> $types
     *
     * @return list<mixed>
     */
    public static function messageArray(array $types): array
    {
        $message = [self::scalarOr($types[random_int(0, count($types) - 1)])];

        for ($i = 0, $count = random_int(0, 4); $i < $count; ++$i) {
            $message[] = match (random_int(0, 3)) {
                0 => self::nearValidEventArray(),
                1 => self::nearValidFilterArray(),
                2 => self::scalarOr('sub-'.random_int(0, 999)),
                default => self::hostileScalar(),
            };
        }

        return $message;
    }

    /**
     * @param list<string> $types
     */
    public static function messageJson(array $types): string
    {
        return json_encode(self::messageArray($types), JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }

    /**
     * A JSON object whose numeric-string keys decode to a sparse-keyed PHP array (e.g. {"0":"EVENT","2":{}}),
     * so count() passes while positional keys are absent — the message-leaf attack vector.
     */
    public static function sparseObjectJson(): string
    {
        $object = new stdClass();
        $keys = ['0', '1', '2', '3', (string) random_int(0, 9), 'type'];

        for ($i = 0, $count = random_int(0, 4); $i < $count; ++$i) {
            $object->{$keys[random_int(0, count($keys) - 1)]} = match (random_int(0, 2)) {
                0 => 'EVENT',
                1 => self::nearValidEventArray(),
                default => self::hostileScalar(),
            };
        }

        return json_encode($object, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }

    private static function scalarOr(mixed $valid): mixed
    {
        return 0 === random_int(0, 1) ? $valid : self::hostileScalar();
    }

    private static function hostileScalar(): mixed
    {
        return match (random_int(0, 8)) {
            0 => random_int(-5, 70000),
            1 => [0, 1, -1, PHP_INT_MAX, PHP_INT_MIN][random_int(0, 4)],
            2 => true,
            3 => false,
            4 => null,
            5 => self::asciiNoise(random_int(0, 64)),
            6 => self::randomBytes(48),
            7 => bin2hex(self::randomBytes(32)),
            default => '',
        };
    }

    /**
     * @return list<mixed>
     */
    private static function nearValidTags(): array
    {
        $tags = [];

        for ($i = 0, $count = random_int(0, 8); $i < $count; ++$i) {
            if (0 === random_int(0, 4)) {
                $tags[] = self::hostileScalar();

                continue;
            }

            $tag = [self::scalarOr(['e', 'p', 'a', 'q', 't', 'd'][random_int(0, 5)])];
            for ($j = 0, $values = random_int(0, 4); $j < $values; ++$j) {
                $tag[] = self::scalarOr(self::VALID_HEX_32);
            }
            $tags[] = $tag;
        }

        return $tags;
    }

    private static function bech32Shaped(): string
    {
        $body = '';
        for ($i = 0, $length = random_int(0, 120); $i < $length; ++$i) {
            $body .= self::BECH32_CHARSET[random_int(0, 31)];
        }

        return self::asciiLower(random_int(1, 8)).'1'.$body;
    }

    private static function asciiNoise(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; ++$i) {
            $out .= chr(random_int(33, 126));
        }

        return $out;
    }

    private static function asciiLower(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; ++$i) {
            $out .= chr(random_int(97, 122));
        }

        return $out;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function randomArray(int $depth): array
    {
        $out = [];
        for ($i = 0, $count = random_int(0, 6); $i < $count; ++$i) {
            $key = 1 === random_int(0, 1) ? self::asciiNoise(random_int(1, 8)) : $i;
            $out[$key] = self::randomValue($depth);
        }

        return $out;
    }

    private static function randomValue(int $depth): mixed
    {
        $ceiling = $depth >= self::MAX_ARRAY_DEPTH ? 4 : 5;

        return match (random_int(0, $ceiling)) {
            0 => random_int(-1_000_000, 1_000_000),
            1 => self::asciiNoise(random_int(0, 64)),
            2 => 1 === random_int(0, 1),
            3 => null,
            4 => self::randomBytes(16),
            default => self::randomArray($depth + 1),
        };
    }

    private static function randomBytes(int $maxLength): string
    {
        $length = random_int(0, $maxLength);

        return 0 === $length ? '' : random_bytes($length);
    }
}
