<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    private const string EVENT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string AUTHOR = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    public function testCanCreateWithTypeAndValues(): void
    {
        $tag = Tag::fromArray([TagType::EVENT, 'event-id', 'relay-url']);

        $this->assertTrue($tag->getType()->equals(TagType::event()));
        $this->assertSame('event-id', $tag->getValue(0));
        $this->assertSame('relay-url', $tag->getValue(1));
        $this->assertSame(['event-id', 'relay-url'], $tag->getValues());
    }

    public function testAllowsEmptyValuesForFlagStyleTags(): void
    {
        $contentWarningType = TagType::fromString('content-warning');
        $tag = Tag::fromArray(['content-warning']);

        $this->assertTrue($tag->getType()->equals($contentWarningType));
        $this->assertSame([], $tag->getValues());
        $this->assertNull($tag->getValue(0));
    }

    public function testFromArrayRefusesAValueThatIsNotUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tag::fromArray([TagType::HASHTAG, "\xff"]);
    }

    public function testTryFromArrayAcceptsAnEmptyName(): void
    {
        $this->assertSame(['', 'value'], Tag::tryFromArray(['', 'value'])?->toArray());
    }

    public function testTryFromArrayAcceptsALoneEmptyName(): void
    {
        $this->assertSame([''], Tag::tryFromArray([''])?->toArray());
    }

    public function testABareEventTagCarriesOnlyTheEventId(): void
    {
        $this->assertSame([TagType::EVENT, self::EVENT_ID], Tag::event(self::eventId())->toArray());
    }

    public function testAnEventTagNamesItsAuthorAfterAnEmptyRelay(): void
    {
        $this->assertSame([TagType::EVENT, self::EVENT_ID, '', self::AUTHOR], Tag::event(self::eventId(), null, self::author())->toArray());
    }

    public function testARootEventTagWritesTheEventTagShapeUnderTheUppercaseName(): void
    {
        $relay = RelayUrl::fromString('wss://relay.example.com');

        $this->assertSame([TagType::ROOT_EVENT, self::EVENT_ID, 'wss://relay.example.com', self::AUTHOR], Tag::rootEvent(self::eventId(), $relay, self::author())->toArray());
    }

    public function testGetValueReturnsNullForInvalidIndex(): void
    {
        $tag = Tag::fromArray([TagType::EVENT, 'event-id']);

        $this->assertNull($tag->getValue(1));
    }

    public function testHasValueWorksCorrectly(): void
    {
        $tag = Tag::fromArray([TagType::EVENT, 'event-id', 'relay-url']);

        $this->assertTrue($tag->hasValue('event-id'));
        $this->assertTrue($tag->hasValue('relay-url'));
        $this->assertFalse($tag->hasValue('not-present'));
    }

    public function testToArrayWorksCorrectly(): void
    {
        $tag = Tag::fromArray([TagType::EVENT, 'event-id', 'relay-url']);

        $expected = ['e', 'event-id', 'relay-url'];
        $this->assertSame($expected, $tag->toArray());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $tag1 = Tag::fromArray([TagType::EVENT, 'event-id']);
        $tag2 = Tag::fromArray([TagType::EVENT, 'event-id']);
        $tag3 = Tag::fromArray([TagType::PUBKEY, 'event-id']);
        $tag4 = Tag::fromArray([TagType::EVENT, 'different-id']);

        $this->assertTrue($tag1->equals($tag2));
        $this->assertFalse($tag1->equals($tag3));
        $this->assertFalse($tag1->equals($tag4));
    }

    public function testStaticEventFactory(): void
    {
        $tag = Tag::fromArray(['e', 'event-id', 'wss://relay.example.com', 'root']);

        $this->assertTrue($tag->getType()->equals(TagType::event()));
        $this->assertSame('event-id', $tag->getValue(0));
        $this->assertSame('wss://relay.example.com', $tag->getValue(1));
        $this->assertSame('root', $tag->getValue(2));
    }

    public function testABarePubkeyTagCarriesOnlyThePublicKey(): void
    {
        $this->assertSame([TagType::PUBKEY, self::AUTHOR], Tag::pubkey(self::author())->toArray());
    }

    public function testAPubkeyTagCarriesItsRelayAndPetname(): void
    {
        $relay = RelayUrl::fromString('wss://relay.example.com');

        $this->assertSame([TagType::PUBKEY, self::AUTHOR, 'wss://relay.example.com', 'alice'], Tag::pubkey(self::author(), $relay, 'alice')->toArray());
    }

    public function testAPubkeyTagNamesItsPetnameAfterAnEmptyRelay(): void
    {
        $this->assertSame([TagType::PUBKEY, self::AUTHOR, '', 'alice'], Tag::pubkey(self::author(), null, 'alice')->toArray());
    }

    public function testStaticHashtagFactory(): void
    {
        $tag = Tag::hashtag(Hashtag::fromString('nostr'));

        $this->assertTrue($tag->getType()->equals(TagType::hashtag()));
        $this->assertSame('nostr', $tag->getValue(0));
    }

    public function testAHashtagTagCarriesTheLowercaseValue(): void
    {
        $this->assertSame('nostr', Tag::hashtag(Hashtag::fromString('NOSTR'))->getValue(0));
    }

    public function testStaticIdentifierFactory(): void
    {
        $tag = Tag::identifier('my-id');

        $this->assertTrue($tag->getType()->equals(TagType::identifier()));
        $this->assertSame('my-id', $tag->getValue(0));
    }

    public function testTryFromArrayWorksCorrectly(): void
    {
        $data = ['e', 'event-id', 'relay-url'];
        $tag = Tag::tryFromArray($data);

        $this->assertNotNull($tag);
        $this->assertTrue($tag->getType()->equals(TagType::event()));
        $this->assertSame('event-id', $tag->getValue(0));
        $this->assertSame('relay-url', $tag->getValue(1));
    }

    public function testTryFromArrayReturnsNullForEmptyArray(): void
    {
        $this->assertNull(Tag::tryFromArray([]));
    }

    public function testTryFromArrayReturnsNullForNonStringValues(): void
    {
        $this->assertNull(Tag::tryFromArray(['e', 123]));
    }

    public function testTryFromArrayReturnsNullForInvalidUtf8Value(): void
    {
        $this->assertNull(Tag::tryFromArray(['e', "bad\xff\xfeutf8"]));
    }

    private static function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID) ?? throw new InvalidArgumentException('fixture');
    }

    private static function author(): PublicKey
    {
        return PublicKey::tryFromHex(self::AUTHOR) ?? throw new InvalidArgumentException('fixture');
    }
}
