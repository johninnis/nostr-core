<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Factory;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Service\Nip42EventChecker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileEventMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Content\LongformMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Npub;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Sha256Hash;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RumourFactoryTest extends TestCase
{
    private const string THIRD_PUBKEY_HEX = 'f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9';

    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->keyPair = KeyMother::alice();
    }

    public function testHttpAuthForTheEmptyBodysHashCarriesNoPayloadTag(): void
    {
        $event = $this->authorFactory()->createHttpAuth(Nip98Request::fromBodyHash(HttpUrl::fromString('https://api.example.com/'), 'GET', Sha256Hash::ofContent('')));

        $this->assertFalse($event->getTags()->hasType(TagType::payload()));
    }

    public function testCanCreateAuth(): void
    {
        $relayUrl = RelayUrl::tryFromString('wss://relay.example.com');
        $this->assertNotNull($relayUrl);
        $challenge = Challenge::fromString('test-challenge-string');

        $event = $this->authorFactory()->createAuth(new RelayChallenge($relayUrl, $challenge));

        $this->assertSame(EventKind::CLIENT_AUTH, $event->getKind()->toInt());
        $this->assertSame('', (string) $event->getContent());

        $relayTags = $event->getTags()->findByType(TagType::fromString('relay'));
        $challengeTags = $event->getTags()->findByType(TagType::fromString('challenge'));

        $this->assertCount(1, $relayTags);
        $this->assertSame('wss://relay.example.com', $relayTags[0]->getValue());
        $this->assertCount(1, $challengeTags);
        $this->assertSame('test-challenge-string', $challengeTags[0]->getValue());
    }

    public function testTheAuthDraftAnswersTheRelayChallengeItWasBuiltFrom(): void
    {
        $relayChallenge = new RelayChallenge(
            RelayUrl::fromString('wss://relay.example.com'),
            Challenge::fromString('test-challenge-string'),
        );
        $draft = $this->authorFactory()->createAuth($relayChallenge);

        $this->assertNull(new Nip42EventChecker()->check(EventMother::fromRumour($draft), $relayChallenge, $draft->getCreatedAt()));
    }

    public function testCanCreateHttpAuth(): void
    {
        $url = 'https://api.example.com/upload';
        $method = 'POST';
        $payloadHash = Sha256Hash::ofContent('{"data":"test"}');

        $event = $this->authorFactory()->createHttpAuth(Nip98Request::fromBodyHash(HttpUrl::fromString($url), $method, $payloadHash));

        $this->assertTrue($event->getKind()->is(EventKind::HTTP_AUTH));
        $this->assertSame('', (string) $event->getContent());

        $urlTags = $event->getTags()->findByType(TagType::fromString('u'));
        $methodTags = $event->getTags()->findByType(TagType::method());
        $payloadTags = $event->getTags()->findByType(TagType::payload());

        $this->assertCount(1, $urlTags);
        $this->assertSame($url, $urlTags[0]->getValue());
        $this->assertCount(1, $methodTags);
        $this->assertSame($method, $methodTags[0]->getValue());
        $this->assertCount(1, $payloadTags);
        $this->assertSame((string) $payloadHash, $payloadTags[0]->getValue());
    }

    public function testCanCreateHttpAuthWithoutPayload(): void
    {
        $event = $this->authorFactory()->createHttpAuth(Nip98Request::fromBodyHash(HttpUrl::fromString('https://api.example.com/'), 'GET'));

        $this->assertTrue($event->getKind()->is(EventKind::HTTP_AUTH));
        $this->assertFalse($event->getTags()->hasType(TagType::payload()));
    }

    public function testRepostOfAShortNoteIsKind6(): void
    {
        $this->assertTrue($this->repostOf($this->signedEvent(EventKind::TEXT_NOTE))->getKind()->is(EventKind::REPOST));
    }

    public function testRepostTagsTheTargetIdWithTheRelayItCanBeFetchedFrom(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE);

        $this->assertSame(
            [['e', $target->getId()->toHex(), 'wss://relay.example.com']],
            self::tagsOfType($this->repostOf($target), TagType::event()),
        );
    }

    public function testRepostTagsTheTargetAuthor(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE);

        $this->assertSame([$target->getPubkey()->toHex()], $this->repostOf($target)->getTags()->getValuesByType(TagType::pubkey()));
    }

    public function testRepostContentIsTheStringifiedTarget(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE);

        $this->assertSame($target->toJson(), (string) $this->repostOf($target)->getContent());
    }

    public function testRepostOfAShortNoteHasNoKTag(): void
    {
        $this->assertFalse($this->repostOf($this->signedEvent(EventKind::TEXT_NOTE))->getTags()->hasType(TagType::parentKind()));
    }

    public function testRepostOfAnotherKindIsAGenericRepost(): void
    {
        $this->assertTrue($this->repostOf($this->signedEvent(EventKind::COMMENT))->getKind()->is(EventKind::GENERIC_REPOST));
    }

    public function testGenericRepostNamesTheTargetKindInAKTag(): void
    {
        $this->assertSame(
            [(string) EventKind::COMMENT],
            $this->repostOf($this->signedEvent(EventKind::COMMENT))->getTags()->getValuesByType(TagType::parentKind()),
        );
    }

    public function testRepostOfARegularEventHasNoATag(): void
    {
        $this->assertFalse($this->repostOf($this->signedEvent(EventKind::COMMENT))->getTags()->hasType(TagType::addressable()));
    }

    public function testRepostOfAReplaceableEventTagsItsCoordinate(): void
    {
        $target = $this->signedEvent(EventKind::RELAY_LIST);

        $this->assertSame(
            [['a', EventKind::RELAY_LIST.':'.$target->getPubkey()->toHex().':', 'wss://relay.example.com']],
            self::tagsOfType($this->repostOf($target), TagType::addressable()),
        );
    }

    public function testRepostOfAnAddressableEventTagsItsCoordinate(): void
    {
        $target = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));

        $this->assertSame(
            [EventKind::LONGFORM_CONTENT.':'.$target->getPubkey()->toHex().':post'],
            $this->repostOf($target)->getTags()->getValuesByType(TagType::addressable()),
        );
    }

    public function testRepostOfAProtectedEventHasEmptyContent(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([Tag::fromArray([TagType::PROTECTED])]));

        $this->assertSame('', (string) $this->repostOf($target)->getContent());
    }

    public function testRepostOfAnEventWhoseDashTagCarriesAValueEmbedsIt(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([Tag::fromArray([TagType::PROTECTED, 'x'])]));

        $this->assertSame($target->toJson(), (string) $this->repostOf($target)->getContent());
    }

    public function testRepostIsAuthoredByTheReposter(): void
    {
        $this->assertTrue(
            $this->repostOf($this->signedEvent(EventKind::TEXT_NOTE))->getPubkey()->equals(KeyMother::bob()->getPublicKey()),
        );
    }

    public function testCreateTextNoteIsAKind1NoteCarryingItsContent(): void
    {
        $note = $this->authorFactory()->createTextNote(EventContent::fromString('gm'));

        $this->assertSame([EventKind::TEXT_NOTE, 'gm'], [$note->getKind()->toInt(), (string) $note->getContent()]);
    }

    public function testCreateTextNoteTagsWhatItsContentMentions(): void
    {
        $bob = KeyMother::bobPublicKey();
        $content = EventContent::fromString('hi nostr:'.Npub::fromPublicKey($bob)->toBech32().' #Nostr');

        $this->assertSame(
            [['p', $bob->toHex()], ['t', 'nostr']],
            $this->authorFactory()->createTextNote($content)->getTags()->toJsonArray(),
        );
    }

    public function testReplyTagsItsContentAfterItsThreadTagsAndKeepsAThreadTagTheContentRepeats(): void
    {
        $bob = KeyMother::bobPublicKey();
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([Tag::pubkey($bob)]));
        $alice = $this->keyPair->getPublicKey();
        $content = EventContent::fromString('nostr:'.Npub::fromPublicKey($alice)->toBech32().' #nostr');

        $reply = self::bobFactory()->createReply($parent, $content);

        $this->assertSame(
            [
                ['e', $parent->getId()->toHex(), '', 'root', $alice->toHex()],
                ['p', $alice->toHex()],
                ['p', $bob->toHex()],
                ['t', 'nostr'],
            ],
            $reply->getTags()->toJsonArray(),
        );
    }

    public function testCommentMentioningItsParentsAuthorKeepsTheRelayOnTheParentPTag(): void
    {
        $alice = KeyMother::alicePublicKey();
        $article = Rumour::draft(
            $alice,
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            EventContent::fromString('Original post'),
            new TagCollection([Tag::identifier('post')]),
            Timestamp::fromInt(1700000000),
        )->sign($this->keyPair, FakeSignatureService::accepting());
        $coordinate = '30023:'.KeyMother::ALICE_PUBLIC_KEY_HEX.':post';
        $relay = 'wss://relay.example.com';
        $content = EventContent::fromString('nostr:'.Npub::fromPublicKey($alice)->toBech32().' nostr:'.Npub::fromPublicKey(KeyMother::bobPublicKey())->toBech32().' #nostr');

        $this->assertSame(
            [
                ['A', $coordinate, $relay],
                ['K', '30023'],
                ['P', KeyMother::ALICE_PUBLIC_KEY_HEX, $relay],
                ['a', $coordinate, $relay],
                ['e', 'ad7045d96edf815d23fb2922cbdb0c12bf266815c2a3045c4179565c669e6b9d', $relay, KeyMother::ALICE_PUBLIC_KEY_HEX],
                ['k', '30023'],
                ['p', KeyMother::ALICE_PUBLIC_KEY_HEX, $relay],
                ['p', KeyMother::BOB_PUBLIC_KEY_HEX],
                ['t', 'nostr'],
            ],
            self::bobFactory()->createReply($article, $content, $this->hintRelay())->getTags()->toJsonArray(),
        );
    }

    public function testACommentTagsItsContentAfterItsThreadTags(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));

        $reply = self::bobFactory()->createReply($article, EventContent::fromString('#Nostr'));

        $this->assertSame([['t', 'nostr']], array_slice($reply->getTags()->toJsonArray(), -1));
    }

    public function testReplyToAShortNoteIsAShortNote(): void
    {
        $this->assertTrue($this->replyTo($this->signedEvent(EventKind::TEXT_NOTE))->getKind()->is(EventKind::TEXT_NOTE));
    }

    public function testReplyCarriesItsContentAndAuthor(): void
    {
        $reply = $this->replyTo($this->signedEvent(EventKind::TEXT_NOTE));

        $this->assertSame('A reply', (string) $reply->getContent());
        $this->assertTrue($reply->getPubkey()->equals(KeyMother::bob()->getPublicKey()));
    }

    public function testReplyToATopLevelNoteMarksTheParentAsRootWithItsAuthor(): void
    {
        $parent = $this->signedEvent(EventKind::TEXT_NOTE);

        $this->assertSame(
            [['e', $parent->getId()->toHex(), '', 'root', $parent->getPubkey()->toHex()], ['p', $parent->getPubkey()->toHex()]],
            $this->replyTo($parent)->getTags()->toJsonArray(),
        );
    }

    public function testReplyReadsTheThreadRootFromTheParentsTags(): void
    {
        $rootId = str_repeat('1', 64);
        $rootAuthor = self::THIRD_PUBKEY_HEX;
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([
            Tag::fromArray(['e', $rootId, 'wss://root.example.com', 'root', $rootAuthor]),
            Tag::fromArray(['p', $rootAuthor]),
        ]));

        $this->assertSame(
            [
                ['e', $rootId, 'wss://root.example.com', 'root', $rootAuthor],
                ['e', $parent->getId()->toHex(), '', 'reply', $parent->getPubkey()->toHex()],
            ],
            self::tagsOfType($this->replyTo($parent), TagType::event()),
        );
    }

    public function testReplyReadsAPositionalRootWithoutAnAuthor(): void
    {
        $rootId = str_repeat('1', 64);
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([Tag::fromArray(['e', $rootId])]));

        $this->assertSame(['e', $rootId, '', 'root'], self::tagsOfType($this->replyTo($parent), TagType::event())[0]);
    }

    public function testReplyTagsTheParentAuthorAndEveryPubkeyTheParentTags(): void
    {
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([
            Tag::pubkey(KeyMother::bob()->getPublicKey()),
            Tag::fromArray(['p', self::THIRD_PUBKEY_HEX]),
        ]));

        $this->assertSame(
            [$parent->getPubkey()->toHex(), KeyMother::bob()->getPublicKey()->toHex(), self::THIRD_PUBKEY_HEX],
            $this->replyTo($parent)->getTags()->getValuesByType(TagType::pubkey()),
        );
    }

    public function testReplyTagsTheRootAuthorTheParentNames(): void
    {
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([
            Tag::fromArray(['e', str_repeat('1', 64), '', 'root', self::THIRD_PUBKEY_HEX]),
        ]));

        $this->assertContains(self::THIRD_PUBKEY_HEX, $this->replyTo($parent)->getTags()->getValuesByType(TagType::pubkey()));
    }

    public function testReplyTagsEachPubkeyOnce(): void
    {
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([Tag::pubkey($this->keyPair->getPublicKey())]));

        $this->assertSame([$parent->getPubkey()->toHex()], $this->replyTo($parent)->getTags()->getValuesByType(TagType::pubkey()));
    }

    public function testReplyToAnArticleIsAComment(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));

        $this->assertTrue($this->replyTo($article)->getKind()->is(EventKind::COMMENT));
    }

    public function testCommentOnAnArticleScopesToItsAddressAndPointsAtItAsParent(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));
        $author = $article->getPubkey()->toHex();
        $coordinate = EventKind::LONGFORM_CONTENT.':'.$author.':post';

        $this->assertSame(
            [
                ['A', $coordinate, ''],
                ['K', (string) EventKind::LONGFORM_CONTENT],
                ['P', $author],
                ['a', $coordinate, ''],
                ['e', $article->getId()->toHex(), '', $author],
                ['k', (string) EventKind::LONGFORM_CONTENT],
                ['p', $author],
            ],
            $this->replyTo($article)->getTags()->toJsonArray(),
        );
    }

    public function testCommentOnARegularEventScopesToItsId(): void
    {
        $picture = $this->signedEvent(EventKind::PICTURE);
        $author = $picture->getPubkey()->toHex();

        $this->assertSame(
            [
                ['E', $picture->getId()->toHex(), '', $author],
                ['K', (string) EventKind::PICTURE],
                ['P', $author],
                ['e', $picture->getId()->toHex(), '', $author],
                ['k', (string) EventKind::PICTURE],
                ['p', $author],
            ],
            $this->replyTo($picture)->getTags()->toJsonArray(),
        );
    }

    public function testCommentOnACommentKeepsTheParentCommentsRootScope(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));
        $comment = $this->replyTo($article)->sign(KeyMother::bob(), FakeSignatureService::accepting());
        $rootScope = array_values(array_filter(
            $comment->getTags()->toJsonArray(),
            static fn (array $tag): bool => in_array($tag[0], ['A', 'K', 'P'], true),
        ));

        $this->assertSame(
            [
                ...$rootScope,
                ['e', $comment->getId()->toHex(), '', $comment->getPubkey()->toHex()],
                ['k', (string) EventKind::COMMENT],
                ['p', $comment->getPubkey()->toHex()],
            ],
            $this->replyTo($comment)->getTags()->toJsonArray(),
        );
    }

    public function testReplyToANoteNamesWhereTheParentCanBeFound(): void
    {
        $parent = $this->signedEvent(EventKind::TEXT_NOTE);

        $this->assertSame(
            [['e', $parent->getId()->toHex(), 'wss://relay.example.com', 'root', $parent->getPubkey()->toHex()]],
            self::tagsOfType($this->replyTo($parent, $this->hintRelay()), TagType::event()),
        );
    }

    public function testReplyInAThreadNamesWhereOnlyTheParentCanBeFound(): void
    {
        $rootId = str_repeat('1', 64);
        $parent = $this->signedEvent(EventKind::TEXT_NOTE, new TagCollection([Tag::fromArray(['e', $rootId, '', 'root'])]));

        $this->assertSame(
            [
                ['e', $rootId, '', 'root'],
                ['e', $parent->getId()->toHex(), 'wss://relay.example.com', 'reply', $parent->getPubkey()->toHex()],
            ],
            self::tagsOfType($this->replyTo($parent, $this->hintRelay()), TagType::event()),
        );
    }

    public function testCommentOnAnArticleNamesWhereTheArticleCanBeFoundInItsScopeAndParentTags(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));
        $author = $article->getPubkey()->toHex();
        $coordinate = EventKind::LONGFORM_CONTENT.':'.$author.':post';
        $relay = 'wss://relay.example.com';

        $this->assertSame(
            [
                ['A', $coordinate, $relay],
                ['K', (string) EventKind::LONGFORM_CONTENT],
                ['P', $author, $relay],
                ['a', $coordinate, $relay],
                ['e', $article->getId()->toHex(), $relay, $author],
                ['k', (string) EventKind::LONGFORM_CONTENT],
                ['p', $author, $relay],
            ],
            $this->replyTo($article, $this->hintRelay())->getTags()->toJsonArray(),
        );
    }

    public function testCommentOnACommentNamesWhereOnlyTheParentCanBeFound(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));
        $articleAuthor = $article->getPubkey()->toHex();
        $comment = $this->replyTo($article)->sign(KeyMother::bob(), FakeSignatureService::accepting());
        $commenter = $comment->getPubkey()->toHex();
        $relay = 'wss://relay.example.com';

        $this->assertSame(
            [
                ['A', EventKind::LONGFORM_CONTENT.':'.$articleAuthor.':post', ''],
                ['K', (string) EventKind::LONGFORM_CONTENT],
                ['P', $articleAuthor],
                ['e', $comment->getId()->toHex(), $relay, $commenter],
                ['k', (string) EventKind::COMMENT],
                ['p', $commenter, $relay],
            ],
            $this->replyTo($comment, $this->hintRelay())->getTags()->toJsonArray(),
        );
    }

    public function testCanCreateReaction(): void
    {
        $targetEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Target post'),
        )->sign($this->keyPair, FakeSignatureService::accepting());

        $reaction = self::bobFactory()->createReaction($targetEvent);

        $this->assertTrue($reaction->getKind()->is(EventKind::REACTION));
        $this->assertSame('+', (string) $reaction->getContent());

        $eTags = $reaction->getTags()->findByType(TagType::event());
        $pTags = $reaction->getTags()->findByType(TagType::pubkey());
        $kTags = $reaction->getTags()->findByType(TagType::parentKind());

        $this->assertCount(1, $eTags);
        $this->assertSame($targetEvent->getId()->toHex(), $eTags[0]->getValue());
        $this->assertCount(1, $pTags);
        $this->assertSame($targetEvent->getPubkey()->toHex(), $pTags[0]->getValue());
        $this->assertCount(1, $kTags);
        $this->assertSame((string) $targetEvent->getKind()->toInt(), $kTags[0]->getValue());
        $this->assertCount(0, $reaction->getTags()->findByType(TagType::addressable()));
    }

    public function testCreateReactionAddsAddressableCoordinateForAddressableTarget(): void
    {
        $targetEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(30023),
            EventContent::fromString('An article'),
            new TagCollection([Tag::identifier('my-article')]),
        )->sign($this->keyPair, FakeSignatureService::accepting());

        $reaction = self::bobFactory()->createReaction($targetEvent);

        $aTags = $reaction->getTags()->findByType(TagType::addressable());

        $this->assertCount(1, $aTags);
        $this->assertSame('30023:'.$this->keyPair->getPublicKey()->toHex().':my-article', $aTags[0]->getValue());
    }

    public function testCreateReactionAddressesAnAddressableTargetWithoutADTagWithAnEmptyIdentifier(): void
    {
        $author = $this->keyPair->getPublicKey()->toHex();
        $targetRumour = Rumour::draft($this->keyPair->getPublicKey(), EventKind::fromInt(30023), EventContent::fromString('An article'));
        $targetEvent = new Event($targetRumour, $targetRumour->getId(), EventMother::signature());

        $reaction = self::bobFactory()->createReaction($targetEvent);

        $this->assertSame(['30023:'.$author.':'], $reaction->getTags()->getValuesByType(TagType::addressable()));
    }

    public function testReactionNamesTheTargetsAuthorInItsETagAsNip25Asks(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE);
        $author = $target->getPubkey()->toHex();

        $this->assertSame(
            [['e', $target->getId()->toHex(), '', $author], ['p', $author], ['k', (string) EventKind::TEXT_NOTE]],
            self::bobFactory()->createReaction($target)->getTags()->toJsonArray(),
        );
    }

    public function testReactionToAnAddressableTargetNamesItsAuthorInItsATagAsNip25Asks(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));
        $author = $article->getPubkey()->toHex();

        $this->assertSame(
            [['a', EventKind::LONGFORM_CONTENT.':'.$author.':post', '', $author]],
            self::tagsOfType(self::bobFactory()->createReaction($article), TagType::addressable()),
        );
    }

    public function testReactionNamesWhereTheTargetCanBeFoundInItsETagAndPTagAsNip25Asks(): void
    {
        $target = $this->signedEvent(EventKind::TEXT_NOTE);
        $author = $target->getPubkey()->toHex();
        $relay = (string) $this->hintRelay();

        $this->assertSame(
            [['e', $target->getId()->toHex(), $relay, $author], ['p', $author, $relay], ['k', (string) EventKind::TEXT_NOTE]],
            self::bobFactory()->createReaction($target, relay: $this->hintRelay())->getTags()->toJsonArray(),
        );
    }

    public function testReactionToAnAddressableTargetNamesWhereItCanBeFoundInItsATag(): void
    {
        $article = $this->signedEvent(EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('post')]));
        $author = $article->getPubkey()->toHex();

        $this->assertSame(
            [['a', EventKind::LONGFORM_CONTENT.':'.$author.':post', (string) $this->hintRelay(), $author]],
            self::tagsOfType(self::bobFactory()->createReaction($article, relay: $this->hintRelay()), TagType::addressable()),
        );
    }

    public function testReactionToAReplaceableTargetNamesItByItsETagAlone(): void
    {
        $relayList = $this->signedEvent(EventKind::RELAY_LIST);

        $this->assertSame([], self::tagsOfType(self::bobFactory()->createReaction($relayList), TagType::addressable()));
    }

    public function testCanCreateReactionWithCustomContent(): void
    {
        $targetEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Target post'),
        )->sign($this->keyPair, FakeSignatureService::accepting());

        $reaction = $this->authorFactory()->createReaction($targetEvent, EventContent::fromString('-'));

        $this->assertSame('-', (string) $reaction->getContent());
    }

    public function testCanCreateLongformContentWithMinimalFields(): void
    {
        $content = EventContent::fromString('# My Article\n\nSome content here.');
        $event = $this->authorFactory()->createLongformContent(
            $content,
            LongformMetadata::from('my-article', null, null, null, null, new HashtagCollection()),
        );

        $this->assertTrue($event->getKind()->is(EventKind::LONGFORM_CONTENT));
        $this->assertTrue($event->getContent()->equals($content));

        $dTags = $event->getTags()->findByType(TagType::identifier());
        $this->assertCount(1, $dTags);
        $this->assertSame('my-article', $dTags[0]->getValue());
    }

    public function testCreateLongformContentWritesAnEmptyDTagWhenThereIsNoIdentifier(): void
    {
        $event = $this->authorFactory()->createLongformContent(
            EventContent::fromString('Article body'),
            LongformMetadata::from('', null, null, null, null, new HashtagCollection()),
        );

        $this->assertSame([['d', '']], $event->getTags()->toJsonArray());
    }

    public function testCanCreateLongformContentWithAllFields(): void
    {
        $content = EventContent::fromString('Article body');
        $publishedAt = Timestamp::fromInt(1700000000);

        $event = $this->authorFactory()->createLongformContent(
            $content,
            LongformMetadata::from(
                'full-article',
                'My Full Article',
                'A summary of the article',
                HttpUrl::fromString('https://example.com/image.jpg'),
                $publishedAt,
                HashtagCollection::fromStrings(['nostr', 'bitcoin']),
            ),
        );

        $this->assertTrue($event->getKind()->is(EventKind::LONGFORM_CONTENT));

        $tags = $event->getTags();
        $dTags = $tags->findByType(TagType::identifier());
        $this->assertCount(1, $dTags);
        $this->assertSame('full-article', $dTags[0]->getValue());

        $titleTags = $tags->findByType(TagType::fromString('title'));
        $this->assertCount(1, $titleTags);
        $this->assertSame('My Full Article', $titleTags[0]->getValue());

        $summaryTags = $tags->findByType(TagType::fromString('summary'));
        $this->assertCount(1, $summaryTags);
        $this->assertSame('A summary of the article', $summaryTags[0]->getValue());

        $imageTags = $tags->findByType(TagType::fromString('image'));
        $this->assertCount(1, $imageTags);
        $this->assertSame('https://example.com/image.jpg', $imageTags[0]->getValue());

        $publishedAtTags = $tags->findByType(TagType::fromString('published_at'));
        $this->assertCount(1, $publishedAtTags);
        $this->assertSame('1700000000', $publishedAtTags[0]->getValue());

        $hashtagTags = $tags->findByType(TagType::hashtag());
        $this->assertCount(2, $hashtagTags);
    }

    public function testCanCreateDraftWrap(): void
    {
        $content = EventContent::fromString('{"kind":30023,"content":"wip"}');
        $event = $this->authorFactory()->createDraftWrap(
            'my-draft',
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            $content,
        );

        $this->assertTrue($event->getKind()->is(EventKind::DRAFT_EVENT));
        $this->assertTrue($event->getContent()->equals($content));

        $tags = $event->getTags();
        $dTags = $tags->findByType(TagType::identifier());
        $this->assertCount(1, $dTags);
        $this->assertSame('my-draft', $dTags[0]->getValue());

        $kTags = $tags->findByType(TagType::fromString(TagType::PARENT_KIND));
        $this->assertCount(1, $kTags);
        $this->assertSame((string) EventKind::LONGFORM_CONTENT, $kTags[0]->getValue());
    }

    public function testCreateDraftWrapWritesAnEmptyContentForADeletedDraft(): void
    {
        $event = $this->authorFactory()->createDraftWrap(
            'my-draft',
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            EventContent::empty(),
        );

        $this->assertSame('', (string) $event->getContent());
    }

    public function testCanCreateFileMetadata(): void
    {
        $metadata = FileEventMetadata::from(FileMetadata::from('https://example.com/image.png', 'image/png', str_repeat('a', 64), str_repeat('b', 64)));
        $event = $this->authorFactory()->createFileMetadata(
            $metadata,
            EventContent::fromString('a caption'),
        );

        $this->assertTrue($event->getKind()->is(EventKind::FILE_METADATA));
        $this->assertSame('a caption', (string) $event->getContent());
        $this->assertTrue($event->getTags()->equals($metadata->toTags()));
    }

    public function testCreateDeletionNamesARegularEventByIdAndKind(): void
    {
        $target = $this->signedBy($this->keyPair, EventKind::TEXT_NOTE, new TagCollection());

        $deletion = $this->authorFactory()->createDeletion($target);

        $this->assertTrue($deletion->getKind()->is(EventKind::EVENT_DELETION));
        $this->assertTrue($deletion->getPubkey()->equals($target->getPubkey()));
        $this->assertSame([['e', $target->getId()->toHex()], ['k', '1']], $deletion->getTags()->toJsonArray());
    }

    public function testCreateDeletionNamesAReplaceableEventByItsCoordinate(): void
    {
        $target = $this->signedBy($this->keyPair, EventKind::RELAY_LIST, new TagCollection());

        $deletion = $this->authorFactory()->createDeletion($target);

        $this->assertSame(
            [['a', '10002:'.$this->keyPair->getPublicKey()->toHex().':'], ['k', '10002']],
            $deletion->getTags()->toJsonArray(),
        );
    }

    public function testCreateDeletionNamesAnAddressableEventByItsCoordinate(): void
    {
        $target = $this->signedBy($this->keyPair, EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('my-article')]));

        $deletion = $this->authorFactory()->createDeletion($target);

        $this->assertSame(
            [['a', '30023:'.$this->keyPair->getPublicKey()->toHex().':my-article'], ['k', '30023']],
            $deletion->getTags()->toJsonArray(),
        );
    }

    public function testCreateDeletionNamesAnAddressableEventWithoutOneIdentifierById(): void
    {
        $target = $this->signedBy($this->keyPair, EventKind::LONGFORM_CONTENT, new TagCollection([Tag::identifier('one'), Tag::identifier('two')]));

        $deletion = $this->authorFactory()->createDeletion($target);

        $this->assertSame([['e', $target->getId()->toHex()], ['k', '30023']], $deletion->getTags()->toJsonArray());
    }

    public function testCreateDeletionRefusesAnEventByAnotherAuthor(): void
    {
        $target = $this->signedBy(KeyMother::bob(), EventKind::TEXT_NOTE, new TagCollection());

        $this->expectException(InvalidArgumentException::class);

        $this->authorFactory()->createDeletion($target);
    }

    public function testCreateDeletionRefusesADeletionRequest(): void
    {
        $target = $this->signedBy($this->keyPair, EventKind::EVENT_DELETION, new TagCollection([Tag::event(EventId::tryFromHex(str_repeat('1', 64)) ?? self::fail('invalid event id'))]));

        $this->expectException(InvalidArgumentException::class);

        $this->authorFactory()->createDeletion($target);
    }

    public function testCreatePrivateMessageAddressesEachReceiverOnce(): void
    {
        $bob = KeyMother::bobPublicKey();

        $message = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection([$bob, $bob]), EventContent::fromString('hello'));

        $this->assertTrue($message->getKind()->is(EventKind::PRIVATE_MESSAGE));
        $this->assertSame('hello', (string) $message->getContent());
        $this->assertSame([['p', $bob->toHex()]], $message->getTags()->toJsonArray());
    }

    public function testCreatePrivateMessageNamesTheMessageItAnswers(): void
    {
        $bob = KeyMother::bobPublicKey();
        $parent = Rumour::draft($bob, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'), new TagCollection([Tag::pubkey($this->keyPair->getPublicKey())]));

        $message = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection([$bob]), EventContent::fromString('hello'), $parent);

        $this->assertSame([['p', $bob->toHex()], ['e', $parent->getId()->toHex()]], $message->getTags()->toJsonArray());
    }

    public function testCreatePrivateMessageLeavesItsAuthorOutOfTheReceivers(): void
    {
        $bob = KeyMother::bobPublicKey();

        $message = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection([$this->keyPair->getPublicKey(), $bob]), EventContent::fromString('hello'));

        $this->assertSame([['p', $bob->toHex()]], $message->getTags()->toJsonArray());
    }

    public function testCreatePrivateReactionIsAddressedToTheRoomOfTheMessage(): void
    {
        $bob = KeyMother::bobPublicKey();
        $target = Rumour::draft($bob, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'), new TagCollection([Tag::pubkey($this->keyPair->getPublicKey())]));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([$bob]), $target);

        $this->assertTrue($reaction->getKind()->is(EventKind::REACTION));
        $this->assertSame('+', (string) $reaction->getContent());
        $this->assertSame(
            [['e', $target->getId()->toHex(), '', $bob->toHex()], ['p', $bob->toHex()], ['k', '14']],
            $reaction->getTags()->toJsonArray(),
        );
        $this->assertSame($target->getChatRoom()->toHexes(), $reaction->getChatRoom()->toHexes());
    }

    public function testCreatePrivateReactionTagsTheSenderLastWhenTheSenderWroteTheTarget(): void
    {
        $author = $this->keyPair->getPublicKey();
        $bob = KeyMother::bobPublicKey();
        $third = PublicKey::tryFromHex(self::THIRD_PUBKEY_HEX) ?? self::fail('invalid pubkey');
        $target = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection([$bob, $third]), EventContent::fromString('hi'));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([$bob, $third]), $target);

        $this->assertSame(
            [['e', $target->getId()->toHex(), '', $author->toHex()], ['p', $bob->toHex()], ['p', $third->toHex()], ['p', $author->toHex()], ['k', '14']],
            $reaction->getTags()->toJsonArray(),
        );
    }

    public function testCreatePrivateReactionToItsOwnMessageKeepsTheRoom(): void
    {
        $bob = KeyMother::bobPublicKey();
        $target = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection([$bob]), EventContent::fromString('hi'));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([$bob]), $target);

        $this->assertSame($target->getChatRoom()->toHexes(), $reaction->getChatRoom()->toHexes());
    }

    public function testCreatePrivateReactionTagsTheTargetsAuthorLastAmongItsReceivers(): void
    {
        $bob = KeyMother::bobPublicKey();
        $third = PublicKey::tryFromHex(self::THIRD_PUBKEY_HEX) ?? self::fail('invalid pubkey');
        $target = Rumour::draft($bob, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'), new TagCollection([Tag::pubkey($this->keyPair->getPublicKey()), Tag::pubkey($third)]));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([$bob, $third]), $target);

        $this->assertSame(
            [['p', $third->toHex()], ['p', $bob->toHex()]],
            array_map(static fn (Tag $tag): array => $tag->toArray(), $reaction->getTags()->findByType(TagType::pubkey())),
        );
    }

    public function testCreatePrivateReactionRefusesATargetWhoseAuthorIsNotInTheRoom(): void
    {
        $target = Rumour::draft(KeyMother::bobPublicKey(), EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'));

        $this->expectException(InvalidArgumentException::class);

        $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([PublicKey::tryFromHex(self::THIRD_PUBKEY_HEX) ?? self::fail('invalid pubkey')]), $target);
    }

    public function testCreatePrivateReactionCarriesTheReactionGiven(): void
    {
        $bob = KeyMother::bobPublicKey();
        $target = Rumour::draft($bob, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([$bob]), $target, EventContent::fromString('🤙'));

        $this->assertSame('🤙', (string) $reaction->getContent());
    }

    public function testCreatePrivateMessageWhoseOnlyReceiverIsItsAuthorIsARoomOfOne(): void
    {
        $author = $this->keyPair->getPublicKey();

        $message = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection([$author, $author]), EventContent::fromString('note to self'));

        $this->assertSame([['p', $author->toHex()]], $message->getTags()->toJsonArray());
        $this->assertSame([$author->toHex()], $message->getChatRoom()->toHexes());
    }

    public function testCreatePrivateMessageWithNoReceiverIsARoomOfOne(): void
    {
        $author = $this->keyPair->getPublicKey();

        $message = $this->authorFactory()->createPrivateMessage(new PublicKeyCollection(), EventContent::fromString('note to self'));

        $this->assertSame([['p', $author->toHex()]], $message->getTags()->toJsonArray());
    }

    public function testCreatePrivateReactionWhoseOnlyReceiverIsItsAuthorIsARoomOfOne(): void
    {
        $author = $this->keyPair->getPublicKey();
        $target = Rumour::draft($author, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'), new TagCollection([Tag::pubkey($author)]));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection([$author]), $target);

        $this->assertSame(
            [['e', $target->getId()->toHex(), '', $author->toHex()], ['p', $author->toHex()], ['k', '14']],
            $reaction->getTags()->toJsonArray(),
        );
        $this->assertSame($target->getChatRoom()->toHexes(), $reaction->getChatRoom()->toHexes());
    }

    public function testCreatePrivateReactionWithNoReceiverIsARoomOfOne(): void
    {
        $author = $this->keyPair->getPublicKey();
        $target = Rumour::draft($author, EventKind::fromInt(EventKind::PRIVATE_MESSAGE), EventContent::fromString('hi'));

        $reaction = $this->authorFactory()->createPrivateReaction(new PublicKeyCollection(), $target);

        $this->assertSame([$author->toHex()], $reaction->getChatRoom()->toHexes());
    }

    public function testCommentOnACommentThatNamesNoRootIsRootedAtThatComment(): void
    {
        $this->assertCommentRootedAtItsParent(new TagCollection([Tag::fromArray(['K', '30023'])]));
    }

    public function testCommentOnACommentWhoseRootKindIsEmptyIsRootedAtThatComment(): void
    {
        $this->assertCommentRootedAtItsParent(new TagCollection([Tag::fromArray(['E', str_repeat('1', 64)]), Tag::fromArray(['K', ''])]));
    }

    public function testCommentOnACommentWhoseRootKindIsNotOneValueIsRootedAtThatComment(): void
    {
        $this->assertCommentRootedAtItsParent(new TagCollection([Tag::fromArray(['E', str_repeat('1', 64)]), Tag::fromArray(['K', '1']), Tag::fromArray(['K', '30023'])]));
    }

    public function testCommentOnACommentWhoseRootTagsDisagreeIsRootedAtThatComment(): void
    {
        $this->assertCommentRootedAtItsParent(new TagCollection([Tag::fromArray(['E', str_repeat('1', 64)]), Tag::fromArray(['E', str_repeat('2', 64)]), Tag::fromArray(['K', '1'])]));
    }

    public function testCommentOnACommentRootedAtExternalContentKeepsThatScope(): void
    {
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([Tag::fromArray(['I', 'isbn:9780765382030']), Tag::fromArray(['K', 'isbn'])]));

        $this->assertSame(
            [['I', 'isbn:9780765382030'], ['K', 'isbn']],
            array_slice($this->replyTo($parent)->getTags()->toJsonArray(), 0, 2),
        );
    }

    public function testCommentOnACommentCopiesAnExternalRootHintInItsCanonicalForm(): void
    {
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([Tag::fromArray(['I', 'isbn:9780765382030', 'HTTPS://Example.com:443/book']), Tag::fromArray(['K', 'isbn'])]));

        $this->assertSame(['I', 'isbn:9780765382030', 'https://example.com/book'], $this->replyTo($parent)->getTags()->toJsonArray()[0]);
    }

    public function testCommentOnACommentDropsACopiedExternalRootHintThatIsNotAWebUrl(): void
    {
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([Tag::fromArray(['I', 'isbn:9780765382030', 'wss://relay.example.com']), Tag::fromArray(['K', 'isbn'])]));

        $this->assertSame(['I', 'isbn:9780765382030'], $this->replyTo($parent)->getTags()->toJsonArray()[0]);
    }

    public function testCommentOnACommentPointsAtTheAuthorOfAnAddressRootItsParentLeftOut(): void
    {
        $rootAuthor = KeyMother::BOB_PUBLIC_KEY_HEX;
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([Tag::fromArray(['A', '30023:'.$rootAuthor.':post']), Tag::fromArray(['K', '30023'])]));

        $this->assertSame(
            [['A', '30023:'.$rootAuthor.':post'], ['K', '30023'], ['P', $rootAuthor]],
            array_slice($this->replyTo($parent)->getTags()->toJsonArray(), 0, 3),
        );
    }

    public function testCommentOnACommentPointsAtTheAuthorItsEventRootNames(): void
    {
        $rootId = str_repeat('1', 64);
        $rootAuthor = KeyMother::BOB_PUBLIC_KEY_HEX;
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([Tag::fromArray(['E', $rootId, '', $rootAuthor]), Tag::fromArray(['K', '1'])]));

        $this->assertSame(
            [['E', $rootId, '', $rootAuthor], ['K', '1'], ['P', $rootAuthor]],
            array_slice($this->replyTo($parent)->getTags()->toJsonArray(), 0, 3),
        );
    }

    public function testCommentOnACommentAddsNoRootAuthorWhenTheParentNamesOne(): void
    {
        $rootId = str_repeat('1', 64);
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([
            Tag::fromArray(['E', $rootId, '', KeyMother::BOB_PUBLIC_KEY_HEX]),
            Tag::fromArray(['K', '1']),
            Tag::fromArray(['P', KeyMother::ALICE_PUBLIC_KEY_HEX]),
        ]));

        $this->assertSame(
            [['E', $rootId, '', KeyMother::BOB_PUBLIC_KEY_HEX], ['K', '1'], ['P', KeyMother::ALICE_PUBLIC_KEY_HEX], ['e', $parent->getId()->toHex(), '', $parent->getPubkey()->toHex()]],
            array_slice($this->replyTo($parent)->getTags()->toJsonArray(), 0, 4),
        );
    }

    public function testCommentOnACommentRootedAtExternalContentNamesNoRootAuthor(): void
    {
        $parent = $this->signedEvent(EventKind::COMMENT, new TagCollection([Tag::fromArray(['I', 'isbn:9780765382030']), Tag::fromArray(['K', 'isbn'])]));

        $this->assertSame([], self::tagsOfType($this->replyTo($parent), TagType::fromString(TagType::ROOT_PUBKEY)));
    }

    private function assertCommentRootedAtItsParent(TagCollection $parentTags): void
    {
        $parent = $this->signedEvent(EventKind::COMMENT, $parentTags);
        $author = $parent->getPubkey()->toHex();

        $this->assertSame(
            [
                ['E', $parent->getId()->toHex(), '', $author],
                ['K', (string) EventKind::COMMENT],
                ['P', $author],
                ['e', $parent->getId()->toHex(), '', $author],
                ['k', (string) EventKind::COMMENT],
                ['p', $author],
            ],
            $this->replyTo($parent)->getTags()->toJsonArray(),
        );
    }

    private function signedBy(KeyPair $author, int $kind, TagCollection $tags): Event
    {
        return Rumour::draft($author->getPublicKey(), EventKind::fromInt($kind), tags: $tags)->sign($author, FakeSignatureService::accepting());
    }

    private function signedEvent(int $kind, TagCollection $tags = new TagCollection()): Event
    {
        return Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString('Original post'),
            $tags,
        )->sign($this->keyPair, FakeSignatureService::accepting());
    }

    private function repostOf(Event $target): Rumour
    {
        $relay = RelayUrl::tryFromString('wss://relay.example.com');
        $this->assertNotNull($relay);

        return self::bobFactory()->createRepost($target, $relay);
    }

    /**
     * @return list<list<string>>
     */
    private static function tagsOfType(Rumour $rumour, TagType $type): array
    {
        return array_map(static fn (Tag $tag): array => $tag->toArray(), $rumour->getTags()->findByType($type));
    }

    private function replyTo(Event $parent, ?RelayUrl $hint = null): Rumour
    {
        return self::bobFactory()->createReply($parent, EventContent::fromString('A reply'), $hint);
    }

    private function authorFactory(): RumourFactory
    {
        return new RumourFactory($this->keyPair->getPublicKey());
    }

    private static function bobFactory(): RumourFactory
    {
        return new RumourFactory(KeyMother::bobPublicKey());
    }

    private function hintRelay(): RelayUrl
    {
        $relay = RelayUrl::tryFromString('wss://relay.example.com');
        $this->assertNotNull($relay);

        return $relay;
    }
}
