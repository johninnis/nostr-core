<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapInfo;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use RuntimeException;

final class NutzapMother
{
    public const string SENDER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    public const string RECIPIENT = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    public const string P2PK_KEY = 'eaee8939e3565e48cc62967e2fde9d8e2a4b3ec0081f29eceff5c64ef10ac1ed';
    public const string OTHER_P2PK_KEY = '49098aa8b9d2fbec49ff8598feb17b592b986e62319a4fa488a3dc36387157a7';
    public const string MINT = 'https://mint.example';
    public const string KEYSET_ID = '000a93d6f8a1d2c4';
    public const string UNBLINDED_SIGNATURE = '02277c66191736eb72fce9d975d08e3191f8f96afb73ab1eec37e4465683066d3f';
    public const string DLEQ_E = '1111111111111111111111111111111111111111111111111111111111111111';
    public const string DLEQ_S = '2222222222222222222222222222222222222222222222222222222222222222';
    public const string DLEQ_R = '3333333333333333333333333333333333333333333333333333333333333333';

    /**
     * @param list<list<string>> $rawTags
     */
    public static function event(int $kind, string $author, array $rawTags): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex($author) ?? throw new RuntimeException('Invalid fixture author'),
            EventKind::fromInt($kind),
            EventContent::empty(),
            TagCollectionMother::fromRaw($rawTags),
            Timestamp::fromInt(1700000000),
        ));
    }

    public static function secret(string $lockKey): string
    {
        return json_encode(['P2PK', ['nonce' => 'b00bdd0467b0090a25bdf2d2f0d45ac4e355c482c1418350f273a04fedaaee83', 'data' => $lockKey]], JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    public static function proofTag(int $amount, string $lockKey = '02'.self::P2PK_KEY, ?string $keysetId = self::KEYSET_ID): array
    {
        return ['proof', json_encode(array_filter([
            'amount' => $amount,
            'id' => $keysetId,
            'secret' => self::secret($lockKey),
            'C' => self::UNBLINDED_SIGNATURE,
            'dleq' => ['e' => self::DLEQ_E, 's' => self::DLEQ_S, 'r' => self::DLEQ_R],
        ], static fn (mixed $field): bool => null !== $field), JSON_THROW_ON_ERROR)];
    }

    /**
     * @param list<list<string>> $rawTags
     */
    public static function nutzap(array $rawTags): Nutzap
    {
        return Nutzap::tryFromEvent(self::event(EventKind::NUTZAP, self::SENDER, $rawTags)) ?? throw new RuntimeException('Invalid fixture nutzap');
    }

    /**
     * @param list<list<string>> $extraTags
     */
    public static function payingNutzap(array $extraTags = []): Nutzap
    {
        return self::nutzap([['p', self::RECIPIENT], ['u', self::MINT], self::proofTag(21), ...$extraTags]);
    }

    /**
     * @param list<list<string>> $rawTags
     */
    public static function infoEvent(array $rawTags, string $author = self::RECIPIENT): Event
    {
        return self::event(EventKind::NUTZAP_MINT_RECOMMENDATION, $author, $rawTags);
    }

    public static function recipientsInfo(string $author = self::RECIPIENT): NutzapInfo
    {
        return NutzapInfo::tryFromEvent(self::infoEvent([['pubkey', self::P2PK_KEY], ['mint', self::MINT, 'sat'], ['relay', 'wss://relay.example']], $author))
            ?? throw new RuntimeException('Invalid fixture nutzap info');
    }
}
