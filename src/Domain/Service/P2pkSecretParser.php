<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

final class P2pkSecretParser
{
    private const string KIND = 'P2PK';
    private const array COMPRESSED_PREFIXES = ['02', '03'];
    private const array SPENDERS_BESIDE_THE_LOCK = ['pubkeys', 'refund', 'locktime'];

    private function __construct()
    {
    }

    // Deliberate: a lock whose secret lets any other key spend the proof, now or once a locktime passes, locks it to no key — see nostr-adrs ADR-0121
    public static function tryParseLockKey(string $secret): ?PublicKey
    {
        $decoded = JsonWireFormat::decode($secret);

        if (!is_array($decoded) || !array_is_list($decoded) || 2 !== count($decoded) || self::KIND !== $decoded[0]) {
            return null;
        }

        $fields = JsonWireFormat::objectFields($decoded[1]);
        $data = null === $fields ? null : JsonWireFormat::stringField($fields, 'data');

        if (null === $fields || null === $data || null === JsonWireFormat::stringField($fields, 'nonce') || !self::namesOnlyTheLock($fields)) {
            return null;
        }

        return self::tryParseCompressedKey($data);
    }

    // Deliberate: read by its lower-case x-coordinate whatever its prefix or case, as NUT-11 compares keys, so a 03 lock is the recipient's key too — see nostr-adrs ADR-0121
    public static function tryParseCompressedKey(string $key): ?PublicKey
    {
        $lowered = strtolower($key);

        return in_array(substr($lowered, 0, 2), self::COMPRESSED_PREFIXES, true) ? PublicKey::tryFromHex(substr($lowered, 2)) : null;
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    private static function namesOnlyTheLock(array $fields): bool
    {
        if (!array_key_exists('tags', $fields)) {
            return true;
        }

        $tags = JsonWireFormat::listField($fields, 'tags', self::secretTag(...));

        return null !== $tags && !array_any($tags, static fn (array $tag): bool => in_array($tag[0], self::SPENDERS_BESIDE_THE_LOCK, true));
    }

    /**
     * @return non-empty-list<non-empty-string>|null
     */
    private static function secretTag(mixed $tag): ?array
    {
        if (!is_array($tag) || !array_is_list($tag) || [] === $tag) {
            return null;
        }

        return array_all($tag, static fn (mixed $value): bool => is_string($value) && '' !== $value) ? $tag : null;
    }
}
