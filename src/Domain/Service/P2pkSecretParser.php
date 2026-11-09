<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use SensitiveParameter;

final class P2pkSecretParser
{
    private const string KIND = 'P2PK';
    private const array COMPRESSED_PREFIXES = ['02', '03'];
    private const array TAGS_THAT_KEEP_ONE_SPENDER = [['sigflag', 'SIG_INPUTS'], ['sigflag', 'SIG_ALL'], ['n_sigs', '1']];

    private function __construct()
    {
    }

    // Deliberate: a lock whose secret could let any other key spend the proof, or no key at all, locks it to no key — see nostr-adrs ADR-0128
    public static function tryParseLockKey(#[SensitiveParameter] string $secret): ?PublicKey
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

    // Deliberate: read by its lower-case x-coordinate whatever its prefix or case, as NUT-11 compares keys, so a 03 lock is the recipient's key too — see nostr-adrs ADR-0128
    public static function tryParseCompressedKey(string $key): ?PublicKey
    {
        $lowered = strtolower($key);

        return in_array(substr($lowered, 0, 2), self::COMPRESSED_PREFIXES, true) ? PublicKey::tryFromHex(substr($lowered, 2)) : null;
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    // Deliberate: only the tags that leave the proof spendable by its one key are read; anything else, a tag no NUT defines yet or a repeat NUT-11 calls unspendable, locks it to no key — see nostr-adrs ADR-0128
    private static function namesOnlyTheLock(array $fields): bool
    {
        if (!array_key_exists('tags', $fields)) {
            return true;
        }

        $tags = $fields['tags'];

        return is_array($tags)
            && array_is_list($tags)
            && array_all($tags, static fn (mixed $tag): bool => in_array($tag, self::TAGS_THAT_KEEP_ONE_SPENDER, true))
            && count($tags) === count(array_unique(array_column($tags, 0)));
    }
}
