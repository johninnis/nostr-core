<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\ContentReferenceTagBuilder;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Note;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceTagBuilderTest extends TestCase
{
    private const string AUTHOR = '3bf0c63fcb93463407af97a5e5ee64fa883d107ef9e558472c4eb9aaaefa459d';

    private const string COORDINATE = '30023:'.self::AUTHOR.':an-article';

    private const string EVENT_ID = 'b02876ad954e1ad9f90548bf5a1688f140819ed482d58d3185a98a6e219aa8e2';

    private const string RELAY = 'wss://relay.example.com';

    private const string SHARED_NPUB_A = 'npub1zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zygse4sl3h';

    private const string SHARED_NPUB_B = 'npub1yg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3q2pw2gm';

    private const string SHARED_NEVENT = 'nevent1qqsrxvenxvenxvenxvenxvenxvenxvenxvenxvenxvenxvenxvenxvcpzamhxue69uhhyetvv9ujuetcv9khqmr99e3k7mgzyq3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyqcyqqqqqqgr7u6hc';

    private const string SHARED_NADDR = 'naddr1qqz8qmmnwsq3wamnwvaz7tmjv4kxz7fwv4uxzmtsd3jjucm0d5pzqyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3qvzqqqr4gux98gf5';

    public function testBuildTagsExtractsTagsFromNostrReferences(): void
    {
        $content = EventContent::fromString(
            'Some tests:'."\n\n"
            .'nostr:nprofile1qqsf03c2gsmx5ef4c9zmxvlew04gdh7u94afnknp33qvv3c94kvwxgspp4mhxue69uhkummn9ekx7mqpzfmhxue69uhhqatjwpkx2urpvuhx2ucpz3mhxue69uhhyetvv9ujuerpd46hxtnfduq3vamnwvaz7tmjv4kxz7fwdehhxarj9e3xzmnyqywhwumn8ghj7mn0wd68yttsw43zuam9d3kx7unyv4ezumn9wsq3gamnwvaz7tmjv4kxz7fwdehhxarj9eskjqg4waehxw309ahx7um5wghrq7p4xqh8getrdqq3xamnwvaz7tmjv4kxz7tpvfkx2tn0wfnszynhwden5te0danxvcmgv95kutnsw43q0g3ycy'."\n"
            .'nostr:note1kq58dtv4fcddn7g9fzl4595g79qgr8k5st2c6vv94x9xugv64r3qqmrmff'."\n"
            .'nostr:npub180cvv07tjdrrgpa0j7j7tmnyl2yr6yr7l8j4s3evf6u64th6gkwsyjh6w6'."\n"
            .'nostr:nevent1qqsts3r4v3ptcwhrfurz2h9y833mvn2z20ackj9lgwvc7d007e6khcqpzamhxue69uhkzarvv9ejumn0wd68ytnvv9hxgtcpzpmhxue69uhk2tnwdaejumr0dshsz9nhwden5te0v4jx2m3wdehhxarj9ekxzmny9uq3uamnwvaz7tmxv4jkguewdehhxarj9e3xzmny9acx7ur4d3shyqtxwaehxw309anxjmr5v4ezumn0wd68ytnhd9hx2tmwwp6kyvtpxdc8vam9xfcrxa3hd4hx573kdpkx2d3nwgmrywrhdsuhwdfkxashwdm4xgekv7n3wvcrvvnkx4m8zcm3w96nxum8dqen7cnjdaskgcmpwd6r6arjw4jszrnhwden5te0dehhxtnvdakz7qguwaehxw309ahx7um5wgkhqatz9eek2mtfwdhkctnyv4mz7qg7waehxw309ahx7um5wgkhqatz9emk2mrvdaexgetj9ehx2ap0qyghwumn8ghj7mn0wd68ytnhd9hx2tcprfmhxue69uhhqatjv9mxjerp9ehx7um5wghxcctwvshsz9thwden5te0wfjkccte9ejxzmt4wvhxjme0qythwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0qyshwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0wfjhxarjd93hgetyqy08wumn8ghj7un9d3shjtnwdaehgu3wvfskuep0w3e82um5v4jqz9rhwden5te0wfjkcctev93xcefwdaexwtc8ewaeu'."\n"
            .'nostr:nevent1qqsq5zzu9ezhgq6es36jgg94wxsa2xh55p4tfa56yklsvjemsw7vj3cpp4mhxue69uhkummn9ekx7mqpr4mhxue69uhkummnw3ez6ur4vgh8wetvd3hhyer9wghxuet5qy8hwumn8ghj7mn0wd68ytnddaksz9rhwden5te0dehhxarj9ehhsarj9ejx2aspzfmhxue69uhk7enxvd5xz6tw9ec82cspz3mhxue69uhhyetvv9ujuerpd46hxtnfduq3vamnwvaz7tmjv4kxz7fwdehhxarj9e3xzmnyqy28wumn8ghj7un9d3shjtnwdaehgu3wvfnsz9nhwden5te0wfjkccte9ec8y6tdv9kzumn9wspzpn92tr3hexwgt0z7w4qz3fcch4ryshja8jeng453aj4c83646jxvxkyvs4'
        );

        $tags = ContentReferenceTagBuilder::buildTags($content);
        $tagArrays = $tags->toJsonArray();

        $nprofilePubkey = '97c70a44366a6535c145b333f973ea86dfdc2d7a99da618c40c64705ad98e322';
        $noteId = 'b02876ad954e1ad9f90548bf5a1688f140819ed482d58d3185a98a6e219aa8e2';
        $npubPubkey = '3bf0c63fcb93463407af97a5e5ee64fa883d107ef9e558472c4eb9aaaefa459d';
        $neventOneId = 'b844756442bc3ae34f06255ca43c63b64d4253fb8b48bf43998f35eff6756be0';
        $neventTwoId = '0a085c2e4574035984752420b571a1d51af4a06ab4f69a25bf064b3b83bcc947';
        $neventTwoAuthor = 'ccaa58e37c99c85bc5e754028a718bd46485e5d3cb3345691ecab83c755d48cc';

        $pTags = array_filter($tagArrays, static fn (array $t) => 'p' === $t[0]);
        $eTags = array_filter($tagArrays, static fn (array $t) => 'e' === $t[0]);
        $qTags = array_filter($tagArrays, static fn (array $t) => 'q' === $t[0]);

        $pTagValues = array_map(static fn (array $t) => $t[1], $pTags);
        $qTagValues = array_map(static fn (array $t) => $t[1], $qTags);

        $this->assertContains($nprofilePubkey, $pTagValues, 'nprofile should produce a p tag');
        $this->assertContains($npubPubkey, $pTagValues, 'npub should produce a p tag');
        $this->assertContains($neventTwoAuthor, $pTagValues, 'nevent with author should produce a p tag');
        $this->assertCount(3, $pTags, 'should have exactly 3 p tags');

        $this->assertCount(0, $eTags, 'quoted events are q-only per NIP-18; no e mention tag is emitted');

        $this->assertContains($noteId, $qTagValues, 'note should produce a q tag');
        $this->assertContains($neventOneId, $qTagValues, 'nevent without author should produce a q tag');
        $this->assertContains($neventTwoId, $qTagValues, 'nevent with author should produce a q tag');
        $this->assertCount(3, $qTags, 'should have exactly 3 q tags');

        $neventTwoQTag = array_values(array_filter($tagArrays, static fn (array $t) => 'q' === $t[0] && $t[1] === $neventTwoId));
        $this->assertSame($neventTwoAuthor, $neventTwoQTag[0][3], 'nevent q tag should include author pubkey');
    }

    public function testAQuotedAddressIsAQTagCarryingTheCoordinate(): void
    {
        $tagArrays = ContentReferenceTagBuilder::buildTags(EventContent::fromString('Read this: nostr:'.self::naddr()->toBech32()))->toJsonArray();

        $this->assertContains(['q', self::COORDINATE], $tagArrays);
    }

    public function testAQuotedAddressCarriesItsFirstRelayHint(): void
    {
        $tagArrays = self::tagsFor(self::naddr(self::RELAY)->toBech32());

        $this->assertContains(['q', self::COORDINATE, self::RELAY], $tagArrays);
    }

    public function testAQuotedNoteIsAQTagCarryingOnlyTheEventId(): void
    {
        $tagArrays = self::tagsFor(Note::fromEventId(self::eventId())->toBech32());

        $this->assertSame([['q', self::EVENT_ID]], $tagArrays);
    }

    public function testAQuotedRegularEventCarriesItsRelayAndAuthor(): void
    {
        $nevent = Nevent::tryFromEventId(self::eventId(), RelayUrlCollection::fromStrings([self::RELAY]), self::author(), EventKind::fromInt(EventKind::TEXT_NOTE))
            ?? throw new RuntimeException('Expected a valid nevent');

        $this->assertContains(['q', self::EVENT_ID, self::RELAY, self::AUTHOR], self::tagsFor($nevent->toBech32()));
    }

    public function testAQuotedEventOfANonRegularKindCarriesNoAuthor(): void
    {
        $nevent = Nevent::tryFromEventId(self::eventId(), new RelayUrlCollection(), self::author(), EventKind::fromInt(30023))
            ?? throw new RuntimeException('Expected a valid nevent');

        $this->assertContains(['q', self::EVENT_ID], self::tagsFor($nevent->toBech32()));
    }

    public function testAQuotedAddressEmitsNoATag(): void
    {
        $tagArrays = ContentReferenceTagBuilder::buildTags(EventContent::fromString('Read this: nostr:'.self::naddr()->toBech32()))->toJsonArray();

        $this->assertSame([], array_values(array_filter($tagArrays, static fn (array $t): bool => 'a' === $t[0])));
    }

    public function testTagsEachReferencesAuthorBeforeItsQuoteMovesARepeatedTagToWhereItRecursThenHashtags(): void
    {
        $content = EventContent::fromString(
            'gm nostr:'.self::SHARED_NPUB_A.' see nostr:'.self::SHARED_NEVENT.' and nostr:'.self::SHARED_NADDR
            .' cc nostr:'.self::SHARED_NPUB_B.' #Nostr #nostr #Zürich',
        );
        $a = str_repeat('11', 32);
        $b = str_repeat('22', 32);

        $this->assertSame(
            [
                ['q', str_repeat('33', 32), self::RELAY, $b],
                ['p', $a],
                ['q', '30023:'.$a.':post', self::RELAY],
                ['p', $b],
                ['t', 'nostr'],
                ['t', 'zürich'],
            ],
            ContentReferenceTagBuilder::buildTags($content)->toJsonArray(),
        );
    }

    public function testATagAlreadyPresentThatTheContentRepeatsIsKeptAsItIsWhereItIs(): void
    {
        $a = str_repeat('11', 32);
        $existing = new TagCollection([Tag::fromArray(['p', $a, self::RELAY]), Tag::fromArray(['e', self::EVENT_ID])]);

        $this->assertSame(
            [['p', $a, self::RELAY], ['e', self::EVENT_ID]],
            ContentReferenceTagBuilder::buildTags(EventContent::fromString('nostr:'.self::SHARED_NPUB_A), $existing)->toJsonArray(),
        );
    }

    public function testAQuoteNamedWithHintsAndThenBareKeepsItsRelayAndAuthor(): void
    {
        $this->assertSame(
            [['p', self::AUTHOR], ['q', self::EVENT_ID, self::RELAY, self::AUTHOR]],
            self::tagsForMentions(self::neventWithHints(), Note::fromEventId(self::eventId())->toBech32()),
        );
    }

    public function testAQuoteNamedBareAndThenWithHintsTakesItsRelayAndAuthor(): void
    {
        $this->assertSame(
            [['p', self::AUTHOR], ['q', self::EVENT_ID, self::RELAY, self::AUTHOR]],
            self::tagsForMentions(Note::fromEventId(self::eventId())->toBech32(), self::neventWithHints()),
        );
    }

    public function testAQuoteNamedWithTwoRelaysKeepsTheFirst(): void
    {
        $this->assertSame(
            [['p', self::AUTHOR], ['q', self::EVENT_ID, self::RELAY, self::AUTHOR]],
            self::tagsForMentions(self::neventWithHints(), self::neventWithHints('wss://other.example.com')),
        );
    }

    public function testAQuotedAddressNamedBareAndThenWithARelayTakesTheRelay(): void
    {
        $this->assertSame(
            [['p', self::AUTHOR], ['q', self::COORDINATE, self::RELAY]],
            self::tagsForMentions(self::naddr()->toBech32(), self::naddr(self::RELAY)->toBech32()),
        );
    }

    public function testAQuotedAddressNamedWithARelayAndThenBareKeepsTheRelay(): void
    {
        $this->assertSame(
            [['p', self::AUTHOR], ['q', self::COORDINATE, self::RELAY]],
            self::tagsForMentions(self::naddr(self::RELAY)->toBech32(), self::naddr()->toBech32()),
        );
    }

    public function testEachHashtagIsATagWrittenOnceInCanonicalForm(): void
    {
        $this->assertSame(
            [['t', 'nostr'], ['t', 'bitcoin']],
            ContentReferenceTagBuilder::buildTags(EventContent::fromString('#Nostr and #bitcoin and #NOSTR'))->toJsonArray(),
        );
    }

    /**
     * @return list<list<string>>
     */
    private static function tagsFor(string $bech32): array
    {
        return ContentReferenceTagBuilder::buildTags(EventContent::fromString('Read this: nostr:'.$bech32))->toJsonArray();
    }

    /**
     * @return list<list<string>>
     */
    private static function tagsForMentions(string $first, string $second): array
    {
        return ContentReferenceTagBuilder::buildTags(EventContent::fromString('nostr:'.$first.' and nostr:'.$second))->toJsonArray();
    }

    private static function neventWithHints(string $relay = self::RELAY): string
    {
        $nevent = Nevent::tryFromEventId(self::eventId(), RelayUrlCollection::fromStrings([$relay]), self::author(), EventKind::fromInt(EventKind::TEXT_NOTE))
            ?? throw new RuntimeException('Expected a valid nevent');

        return $nevent->toBech32();
    }

    private static function author(): PublicKey
    {
        return PublicKey::tryFromHex(self::AUTHOR) ?? throw new RuntimeException('Expected a valid public key');
    }

    private static function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID) ?? throw new RuntimeException('Expected a valid event id');
    }

    private static function naddr(?string $relay = null): Naddr
    {
        $coordinate = EventCoordinate::tryFrom(EventKind::fromInt(30023), self::author(), 'an-article')
            ?? throw new RuntimeException('Expected a valid coordinate');

        return Naddr::tryFromCoordinate($coordinate, RelayUrlCollection::fromStrings(null === $relay ? [] : [$relay]))
            ?? throw new RuntimeException('Expected a valid naddr');
    }
}
