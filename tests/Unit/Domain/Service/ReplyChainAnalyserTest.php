<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReplyChainAnalyserTest extends TestCase
{
    private const string ROOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string PARENT_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string MENTION_ID = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
    private const string ROOT_AUTHOR = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
    private const string PARENT_AUTHOR = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    public function testNip10MarkedChainResolvesRootReplyAndMention(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['e', self::ROOT_ID, 'wss://relay.example', 'root']),
            Tag::tryFromArray(['e', self::PARENT_ID, 'wss://relay.example', 'reply']),
            Tag::tryFromArray(['e', self::MENTION_ID, 'wss://relay.example', 'mention']),
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertTrue($chain->isReply());
        $this->assertTrue($chain->isReply());
        $this->assertSame(self::ROOT_ID, self::rootEvent($chain)?->getEventId()->toHex());
        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
        $this->assertSame(1, $chain->getMentionedEventCount());
        $this->assertSame(self::PARENT_AUTHOR, $chain->getConversationParticipants()->toArray()[0]->toHex());
    }

    public function testNip10PositionalChainUsesFirstAsRootAndLastAsParent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['e', self::ROOT_ID]),
            Tag::tryFromArray(['e', self::MENTION_ID]),
            Tag::tryFromArray(['e', self::PARENT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertSame(self::ROOT_ID, self::rootEvent($chain)?->getEventId()->toHex());
        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
        $this->assertSame(1, $chain->getMentionedEventCount());
    }

    public function testNip10SingleEventTagIsTreatedAsParent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['e', self::PARENT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($chain->hasRoot());
        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
    }

    /**
     * @param list<list<string>> $tags
     */
    #[DataProvider('mentionBesideAnUnmarkedEventTag')]
    public function testAMentionMarkerBesideAnUnmarkedEventTagIsNotAReply(array $tags): void
    {
        $chain = ReplyChainAnalyser::analyse(new TagCollection(array_map(Tag::fromArray(...), $tags)), EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertSame([false, false, false, 2], [$chain->isReply(), $chain->hasRoot(), $chain->hasParent(), $chain->getMentionedEventCount()]);
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function mentionBesideAnUnmarkedEventTag(): iterable
    {
        yield 'mention first' => [[['e', self::MENTION_ID, '', 'mention'], ['e', self::PARENT_ID]]];
        yield 'mention last' => [[['e', self::PARENT_ID], ['e', self::MENTION_ID, '', 'mention']]];
    }

    public function testEventWithoutEventTagsIsARootPost(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($chain->isReply());
        $this->assertFalse($chain->isReply());
        $this->assertFalse($chain->hasRoot());
        $this->assertFalse($chain->hasParent());
    }

    public function testNip22CommentCollectsBothRootAndParentAuthors(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['E', self::ROOT_ID, 'wss://relay.example', self::ROOT_AUTHOR]),
            Tag::tryFromArray(['K', '1']),
            Tag::tryFromArray(['P', self::ROOT_AUTHOR, 'wss://relay.example']),
            Tag::tryFromArray(['e', self::PARENT_ID, 'wss://relay.example', self::PARENT_AUTHOR]),
            Tag::tryFromArray(['k', '1111']),
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $rootEvent = self::rootEvent($chain);
        $parentEvent = self::parentEvent($chain);
        $this->assertNotNull($rootEvent);
        $this->assertNotNull($parentEvent);

        $this->assertTrue($chain->isReply());
        $this->assertSame(self::ROOT_ID, $rootEvent->getEventId()->toHex());
        $this->assertSame(self::ROOT_AUTHOR, $rootEvent->getAuthor()?->toHex());
        $this->assertSame(self::PARENT_ID, $parentEvent->getEventId()->toHex());
        $this->assertSame(self::PARENT_AUTHOR, $parentEvent->getAuthor()?->toHex());

        $participants = array_map(
            static fn (PublicKey $pubkey): string => $pubkey->toHex(),
            $chain->getConversationParticipants()->toArray(),
        );
        $this->assertContains(self::ROOT_AUTHOR, $participants, 'NIP-22 root author (P tag) must be a participant');
        $this->assertContains(self::PARENT_AUTHOR, $participants, 'NIP-22 parent author (p tag) must be a participant');
    }

    public function testACommentsParentWithAFifthElementNamesNoAuthorInItsFourth(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['e', self::PARENT_ID, '', self::PARENT_AUTHOR, 'extra']),
            Tag::tryFromArray(['k', '1111']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull(self::parentEvent($chain)?->getAuthor());
    }

    public function testACommentsRootNamesItsAuthorInItsFourthElementBesideAFifth(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['E', self::ROOT_ID, '', self::ROOT_AUTHOR, 'extra']),
            Tag::tryFromArray(['K', '1']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame(self::ROOT_AUTHOR, self::rootEvent($chain)?->getAuthor()?->toHex());
    }

    public function testTheSpecBlogPostCommentResolvesItsAddressRootAndItsParent(): void
    {
        $author = '3c9849383bdea883b0bd16fece1ed36d37e37cdde3ce43b17ea4e9192ec11289';
        $address = '30023:'.$author.':f9347ca7';
        $tags = new TagCollection([
            Tag::tryFromArray(['A', $address, 'wss://example.relay']),
            Tag::tryFromArray(['K', '30023']),
            Tag::tryFromArray(['P', $author, 'wss://example.relay']),
            Tag::tryFromArray(['a', $address, 'wss://example.relay']),
            Tag::tryFromArray(['e', '5b4fc7fed15672fefe65d2426f67197b71ccc82aa0cc8a9e94f683eb78e07651', 'wss://example.relay']),
            Tag::tryFromArray(['k', '30023']),
            Tag::tryFromArray(['p', $author, 'wss://example.relay']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($chain->isReply());
        $this->assertSame($address, (string) self::rootAddress($chain));
        $this->assertSame($address, (string) self::parentAddress($chain));
        $this->assertSame('wss://example.relay', (string) self::rootAddress($chain)?->getRelayHint());
        $this->assertNull(self::rootEvent($chain));
        $this->assertNull(self::parentEvent($chain));
    }

    public function testACommentScopedOnlyToAnAddressHasARootAndIsAReply(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['A', '30023:'.self::ROOT_AUTHOR.':my-article', 'wss://relay.example']),
            Tag::tryFromArray(['K', '30023']),
            Tag::tryFromArray(['P', self::ROOT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($chain->hasRoot());
        $this->assertTrue($chain->isReply());
        $this->assertSame(self::ROOT_AUTHOR, $chain->getConversationParticipants()->toArray()[0]->toHex());
    }

    public function testACommentWhoseParentIsOnlyAnAddressHasAParent(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['a', '30023:'.self::PARENT_AUTHOR.':my-article'])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($chain->hasParent());
        $this->assertSame('30023:'.self::PARENT_AUTHOR.':my-article', (string) self::parentAddress($chain));
    }

    public function testACommentNamingAnEventRootAndAnAddressRootIsRootedAtTheAddress(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['E', self::ROOT_ID]),
            Tag::tryFromArray(['A', '30023:'.self::ROOT_AUTHOR.':my-article']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('30023:'.self::ROOT_AUTHOR.':my-article', (string) self::rootAddress($chain));
    }

    public function testACommentNamingAnEventRootAndExternalContentIsRootedAtTheEvent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030']),
            Tag::tryFromArray(['E', self::ROOT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame(self::ROOT_ID, self::rootEvent($chain)?->getEventId()->toHex());
    }

    public function testACommentNamingAnAddressParentAndAnEventParentHasTheAddressAsParent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['e', self::PARENT_ID]),
            Tag::tryFromArray(['a', '30023:'.self::PARENT_AUTHOR.':my-article']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('30023:'.self::PARENT_AUTHOR.':my-article', (string) self::parentAddress($chain));
    }

    #[DataProvider('unresolvableAddressProvider')]
    public function testACommentWhoseOnlyScopeIsAnUnresolvableAddressIsNotAReply(string $address): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['A', $address]),
            Tag::tryFromArray(['a', $address]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertFalse($chain->isReply());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unresolvableAddressProvider(): iterable
    {
        yield 'a regular kind has no address' => ['1:'.self::ROOT_AUTHOR.':'];
        yield 'the pubkey is not hex' => ['30023:not-a-pubkey:my-article'];
        yield 'no identifier separator' => ['30023:'.self::ROOT_AUTHOR];
    }

    public function testAnAddressTagDoesNotMakeAShortNoteAReply(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['A', '30023:'.self::ROOT_AUTHOR.':my-article'])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($chain->isReply());
        $this->assertNull(self::rootAddress($chain));
    }

    #[DataProvider('kindsThatDoNotReplyByNip10')]
    public function testAKindThatDoesNotThreadIsNotAReplyEvenWhenItNamesAnEvent(int $kind): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['e', self::PARENT_ID])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt($kind));

        $this->assertFalse($chain->isReply());
    }

    #[DataProvider('kindsThatDoNotReplyByNip10')]
    public function testAKindThatDoesNotThreadStillReportsWhatItNames(int $kind): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['e', self::PARENT_ID])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt($kind));

        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function kindsThatDoNotReplyByNip10(): iterable
    {
        yield 'a reaction names what it reacts to' => [EventKind::REACTION];
        yield 'a repost names what it reposts' => [EventKind::REPOST];
        yield 'a generic repost names what it reposts' => [EventKind::GENERIC_REPOST];
        yield 'a zap receipt names what was zapped' => [EventKind::ZAP_RECEIPT];
        yield 'a long-form article is not a thread post' => [EventKind::LONGFORM_CONTENT];
        yield 'a legacy direct message is not a thread post' => [EventKind::ENCRYPTED_DIRECT_MESSAGE];
    }

    public function testAShortNoteThatNamesAnEventIsAReply(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['e', self::PARENT_ID])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertTrue($chain->isReply());
    }

    public function testAnalysingTagsWithoutAKindStillReadsThemAsAThread(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['e', self::PARENT_ID])]);

        $chain = ReplyChainAnalyser::analyse($tags);

        $this->assertTrue($chain->isReply());
    }

    public function testTheSamePubkeyTaggedTwiceIsOneConversationParticipant(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['e', self::PARENT_ID]),
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
            Tag::tryFromArray(['p', self::ROOT_AUTHOR]),
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertCount(2, $chain->getConversationParticipants());
    }

    public function testACommentsParticipantsAreDeduplicatedToo(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['E', self::ROOT_ID]),
            Tag::tryFromArray(['e', self::PARENT_ID]),
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
            Tag::tryFromArray(['p', self::PARENT_AUTHOR]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertCount(1, $chain->getConversationParticipants());
    }

    public function testTheSpecWebsiteCommentResolvesItsExternalRootAndParent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'https://abc.com/articles/1']),
            Tag::tryFromArray(['K', 'web']),
            Tag::tryFromArray(['i', 'https://abc.com/articles/1']),
            Tag::tryFromArray(['k', 'web']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($chain->isReply());
        $this->assertSame('https://abc.com/articles/1', self::rootExternal($chain)?->getValue());
        $this->assertSame('https://abc.com/articles/1', self::parentExternal($chain)?->getValue());
    }

    public function testTheSpecPodcastCommentKeepsTheExternalRootsUrlHint(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f', 'https://fountain.fm/episode/z1y9TMQRuqXl2awyrQxg']),
            Tag::tryFromArray(['K', 'podcast:item:guid']),
            Tag::tryFromArray(['i', 'podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f', 'https://fountain.fm/episode/z1y9TMQRuqXl2awyrQxg']),
            Tag::tryFromArray(['k', 'podcast:item:guid']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('https://fountain.fm/episode/z1y9TMQRuqXl2awyrQxg', (string) self::rootExternal($chain)?->getHint());
    }

    public function testAnExternalRootHintIsReadWhicheverTagCarriesIt(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030']),
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'https://example.com/book']),
            Tag::tryFromArray(['K', 'isbn']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('https://example.com/book', (string) self::rootExternal($chain)?->getHint());
    }

    public function testAnExternalRootHintIsReadInItsCanonicalForm(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'HTTPS://Example.com:443/book']),
            Tag::tryFromArray(['K', 'isbn']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('https://example.com/book', (string) self::rootExternal($chain)?->getHint());
    }

    public function testExternalRootHintsInTwoSpellingsOfOneUrlAreOneClaim(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'https://example.com/book']),
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'https://EXAMPLE.com/book']),
            Tag::tryFromArray(['K', 'isbn']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('https://example.com/book', (string) self::rootExternal($chain)?->getHint());
    }

    public function testExternalRootHintsThatDisagreeStateNoHint(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'https://example.com/book']),
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'https://example.org/book']),
            Tag::tryFromArray(['K', 'isbn']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull(self::rootExternal($chain)?->getHint());
    }

    public function testAnExternalRootHintThatIsNotAWebUrlIsDroppedBeforeAWebUrlBesideIt(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'wss://relay.example.com']),
            Tag::tryFromArray(['I', 'isbn:9780765382030', 'https://example.com/book']),
            Tag::tryFromArray(['K', 'isbn']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('https://example.com/book', (string) self::rootExternal($chain)?->getHint());
    }

    public function testAnExternalRootCarriesTheNip73TypeItsKNames(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'https://abc.com/articles/1']),
            Tag::tryFromArray(['K', 'web']),
            Tag::tryFromArray(['i', 'https://abc.com/articles/1']),
            Tag::tryFromArray(['k', 'web']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('web', self::rootExternal($chain)?->getKind());
        $this->assertSame('web', self::parentExternal($chain)?->getKind());
    }

    public function testAnExternalRootWithoutItsKIsNoRoot(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['I', 'isbn:9780765382030'])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull($chain->getRoot());
        $this->assertFalse($chain->isReply());
    }

    public function testAnExternalRootWithAnEmptyKIsNoRoot(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['I', 'isbn:9780765382030']), Tag::tryFromArray(['K', ''])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull($chain->getRoot());
    }

    public function testAnExternalRootWhoseKTagsDisagreeIsNoRoot(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030']),
            Tag::tryFromArray(['K', 'isbn']),
            Tag::tryFromArray(['K', 'web']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull($chain->getRoot());
    }

    public function testAnExternalParentWithoutItsKIsNoParent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'isbn:9780765382030']),
            Tag::tryFromArray(['K', 'isbn']),
            Tag::tryFromArray(['i', 'isbn:9780765382030']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull($chain->getParent());
    }

    public function testExternalRootTagsWhereOneIsEmptyDisagreeAndNameNoRoot(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', '']),
            Tag::tryFromArray(['I', 'isbn:9780765382030']),
            Tag::tryFromArray(['K', 'isbn']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull($chain->getRoot());
    }

    public function testTheSpecReplyToAPodcastCommentHasAnExternalRootAndAnEventParent(): void
    {
        $tags = new TagCollection([
            Tag::tryFromArray(['I', 'podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f', 'https://fountain.fm/episode/z1y9TMQRuqXl2awyrQxg']),
            Tag::tryFromArray(['K', 'podcast:item:guid']),
            Tag::tryFromArray(['e', '80c48d992a38f9c445b943a9c9f1010b396676013443765750431a9004bdac05', 'wss://example.relay', '252f10c83610ebca1a059c0bae8255eba2f95be4d1d7bcfa89d7248a82d9f111']),
            Tag::tryFromArray(['k', '1111']),
            Tag::tryFromArray(['p', '252f10c83610ebca1a059c0bae8255eba2f95be4d1d7bcfa89d7248a82d9f111']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame('podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f', self::rootExternal($chain)?->getValue());
        $this->assertSame('80c48d992a38f9c445b943a9c9f1010b396676013443765750431a9004bdac05', self::parentEvent($chain)?->getEventId()->toHex());
        $this->assertNull(self::parentExternal($chain));
    }

    public function testACommentScopedOnlyToAnExternalIdentifierIsAReply(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['I', 'isbn:9780765382030']), Tag::tryFromArray(['K', 'isbn'])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertTrue($chain->hasRoot());
        $this->assertTrue($chain->isReply());
    }

    public function testAnExternalTagDoesNotMakeAShortNoteAReply(): void
    {
        $tags = new TagCollection([Tag::tryFromArray(['I', 'isbn:9780765382030'])]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertFalse($chain->isReply());
    }

    public function testNip10RootMarkersThatDisagreeNameNoRoot(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::ROOT_ID, '', 'root']),
            Tag::fromArray(['e', self::MENTION_ID, '', 'root']),
            Tag::fromArray(['e', self::PARENT_ID, '', 'reply']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertNull(self::rootEvent($chain));
        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
    }

    public function testNip10ReplyMarkersNamingOneEventAreOneClaim(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::ROOT_ID, '', 'root']),
            Tag::fromArray(['e', self::PARENT_ID, 'wss://one.example', 'reply']),
            Tag::fromArray(['e', self::PARENT_ID, 'wss://two.example', 'reply']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
    }

    public function testNip10ReplyMarkersThatDisagreeNameNoParent(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::ROOT_ID, '', 'root']),
            Tag::fromArray(['e', self::PARENT_ID, '', 'reply']),
            Tag::fromArray(['e', self::MENTION_ID, '', 'reply']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertNull(self::parentEvent($chain));
    }

    public function testACommentsRootEventTagsThatDisagreeNameNoRootEvent(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['E', self::ROOT_ID]),
            Tag::fromArray(['E', self::MENTION_ID]),
            Tag::fromArray(['e', self::PARENT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull(self::rootEvent($chain));
        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
    }

    public function testACommentsParentEventTagsNamingOneEventAreOneClaim(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['E', self::ROOT_ID]),
            Tag::fromArray(['e', self::PARENT_ID, 'wss://one.example']),
            Tag::fromArray(['e', self::PARENT_ID, 'wss://two.example']),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertSame(self::PARENT_ID, self::parentEvent($chain)?->getEventId()->toHex());
    }

    public function testACommentsRootAddressTagsThatDisagreeFallToItsEventRoot(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['A', '30023:'.self::ROOT_AUTHOR.':one']),
            Tag::fromArray(['A', '30023:'.self::ROOT_AUTHOR.':two']),
            Tag::fromArray(['E', self::ROOT_ID]),
            Tag::fromArray(['e', self::PARENT_ID]),
        ]);

        $chain = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT));

        $this->assertNull(self::rootAddress($chain));
        $this->assertSame(self::ROOT_ID, self::rootEvent($chain)?->getEventId()->toHex());
    }

    private static function rootEvent(ReplyChain $chain): ?EventReference
    {
        $root = $chain->getRoot();

        return $root instanceof EventReference ? $root : null;
    }

    private static function parentEvent(ReplyChain $chain): ?EventReference
    {
        $parent = $chain->getParent();

        return $parent instanceof EventReference ? $parent : null;
    }

    private static function rootAddress(ReplyChain $chain): ?EventCoordinate
    {
        $root = $chain->getRoot();

        return $root instanceof EventCoordinate ? $root : null;
    }

    private static function parentAddress(ReplyChain $chain): ?EventCoordinate
    {
        $parent = $chain->getParent();

        return $parent instanceof EventCoordinate ? $parent : null;
    }

    private static function rootExternal(ReplyChain $chain): ?ExternalContentId
    {
        $root = $chain->getRoot();

        return $root instanceof ExternalContentId ? $root : null;
    }

    private static function parentExternal(ReplyChain $chain): ?ExternalContentId
    {
        $parent = $chain->getParent();

        return $parent instanceof ExternalContentId ? $parent : null;
    }
}
