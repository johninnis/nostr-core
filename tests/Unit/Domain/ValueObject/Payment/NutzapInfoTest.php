<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapInfo;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NutzapInfoTest extends TestCase
{
    public function testItsPubkeyIsItsAuthor(): void
    {
        $this->assertSame(NutzapMother::RECIPIENT, NutzapMother::recipientsInfo()->getPubkey()->toHex());
    }

    /**
     * @param list<list<string>> $pubkeyTags
     */
    #[DataProvider('keysNamingTheX')]
    public function testReadsItsP2pkKeyAsTheXCoordinateItNames(array $pubkeyTags): void
    {
        $this->assertSame(NutzapMother::P2PK_KEY, self::info($pubkeyTags)?->getP2pkPubkey()->toHex());
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function keysNamingTheX(): iterable
    {
        yield 'a bare x-coordinate, as Nostr writes a key' => [[['pubkey', NutzapMother::P2PK_KEY]]];
        yield 'a 02 prefix, as NIP-61 locks with it' => [[['pubkey', '02'.NutzapMother::P2PK_KEY]]];
        yield 'a 03 prefix, the same key to NUT-11' => [[['pubkey', '03'.NutzapMother::P2PK_KEY]]];
        yield 'upper-case hex' => [[['pubkey', strtoupper(NutzapMother::P2PK_KEY)]]];
        yield 'one key written in two forms is one claim' => [[['pubkey', NutzapMother::P2PK_KEY], ['pubkey', '02'.NutzapMother::P2PK_KEY]]];
        yield 'a value that is no key takes no part' => [[['pubkey', 'not a key'], ['pubkey', NutzapMother::P2PK_KEY]]];
    }

    /**
     * @param list<list<string>> $pubkeyTags
     */
    #[DataProvider('tagsNamingNoOneKey')]
    public function testAnEventNamingNoOneP2pkKeyIsNoNutzapInfo(array $pubkeyTags): void
    {
        $this->assertNull(self::info($pubkeyTags));
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function tagsNamingNoOneKey(): iterable
    {
        yield 'no pubkey tag, never falling back to the author' => [[]];
        yield 'pubkey tags that disagree' => [[['pubkey', NutzapMother::P2PK_KEY], ['pubkey', NutzapMother::OTHER_P2PK_KEY]]];
        yield 'a pubkey that is no key' => [[['pubkey', 'not a key']]];
        yield 'a 04-prefixed key' => [[['pubkey', '04'.NutzapMother::P2PK_KEY]]];
    }

    public function testAP2pkKeyThatIsTheAuthorsOwnKeyIsNoNutzapInfo(): void
    {
        $this->assertNull(NutzapInfo::tryFromEvent(NutzapMother::infoEvent([['pubkey', NutzapMother::RECIPIENT]])));
    }

    public function testAnotherKindIsNoNutzapInfo(): void
    {
        $this->assertNull(NutzapInfo::tryFromEvent(NutzapMother::event(EventKind::NUTZAP, NutzapMother::RECIPIENT, [['pubkey', NutzapMother::P2PK_KEY]])));
    }

    public function testListsEachMintOnceAsWrittenAndDropsWhatIsNoWebUrl(): void
    {
        $info = self::info([
            ['pubkey', NutzapMother::P2PK_KEY],
            ['mint', 'https://mint.example', 'usd', 'sat'],
            ['mint', 'HTTPS://Mint.example'],
            ['mint', 'not a url'],
            ['mint', 'https://mint.example'],
        ]);

        $this->assertSame(['https://mint.example', 'HTTPS://Mint.example'], $info?->getMints()->toStrings());
    }

    public function testAnEventListingNoMintIsNutzapInfoWithNoMints(): void
    {
        $this->assertSame(0, self::info([['pubkey', NutzapMother::P2PK_KEY]])?->getMints()->count());
    }

    public function testListsEachRelayOnceInCanonicalFormAndDropsWhatIsNoRelay(): void
    {
        $info = self::info([
            ['pubkey', NutzapMother::P2PK_KEY],
            ['relay', 'wss://Relay.example/'],
            ['relay', 'not a relay'],
            ['relay', 'wss://relay.example'],
        ]);

        $this->assertSame(['wss://relay.example'], $info?->getRelays()->toStrings());
    }

    /**
     * @param list<list<string>> $rawTags
     */
    private static function info(array $rawTags): ?NutzapInfo
    {
        return NutzapInfo::tryFromEvent(NutzapMother::infoEvent($rawTags));
    }
}
