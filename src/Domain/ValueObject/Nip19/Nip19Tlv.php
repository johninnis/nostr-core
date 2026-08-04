<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;

/**
 * The type-length-value record list an nprofile, nevent or naddr carries.
 *
 * @internal this models the framing the NIP-19 entities are built from; it is not part of the
 *           package's supported surface and may change whenever those entities need it to
 */
// Deliberate: a value object holding both the encoded bytes and the parsed records, not a stateless codec — it is minted by the entities in this namespace and belongs beside them — see ADR-0059
final readonly class Nip19Tlv
{
    public const int TYPE_SPECIAL = 0;
    public const int TYPE_RELAY = 1;
    public const int TYPE_AUTHOR = 2;
    public const int TYPE_KIND = 3;

    private const int UINT8_MAX = 255;
    private const int KIND_BYTE_LENGTH = 4;

    /**
     * @param array<int, list<string>> $records
     */
    private function __construct(private string $bytes, private array $records)
    {
    }

    /**
     * @param list<array{type: int, value: string}> $records
     */
    // Deliberate: NIP-19 encodes BOTH the type and the length as a uint8, so either out of range has no representation; pack('C') would wrap it modulo 256, and for a length the remainder would then decode as further records — see ADR-0059
    public static function tryFromRecords(array $records): ?self
    {
        $bytes = '';
        $indexed = [];

        foreach ($records as $record) {
            $valueLength = strlen($record['value']);

            if ($record['type'] < 0 || $record['type'] > self::UINT8_MAX || $valueLength > self::UINT8_MAX) {
                return null;
            }

            $bytes .= pack('CC', $record['type'], $valueLength).$record['value'];
            $indexed[$record['type']] ??= [];
            $indexed[$record['type']][] = $record['value'];
        }

        return new self($bytes, $indexed);
    }

    public static function tryFromBytes(string $bytes): ?self
    {
        $records = [];
        $position = 0;
        $length = strlen($bytes);

        while ($position < $length) {
            $type = ord($bytes[$position++]);

            if ($position >= $length) {
                return null;
            }

            $valueLength = ord($bytes[$position++]);
            $value = substr($bytes, $position, $valueLength);

            if (strlen($value) < $valueLength) {
                return null;
            }

            $position += $valueLength;
            $records[$type] ??= [];
            $records[$type][] = $value;
        }

        return new self($bytes, $records);
    }

    public static function encodeKind(EventKind $kind): string
    {
        return pack('N', $kind->toInt());
    }

    public function toBytes(): string
    {
        return $this->bytes;
    }

    public function first(int $type): ?string
    {
        return $this->records[$type][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function all(int $type): array
    {
        return $this->records[$type] ?? [];
    }

    public function kind(): ?EventKind
    {
        $value = $this->first(self::TYPE_KIND);

        if (null === $value || self::KIND_BYTE_LENGTH !== strlen($value)) {
            return null;
        }

        $unpacked = unpack('N', $value);

        return false === $unpacked ? null : EventKind::tryFromInt($unpacked[1]);
    }
}
