<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\ReplyTagBuilder;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplyTagBuilderTest extends TestCase
{
    use WithCryptoServices;

    private KeyPair $keyPair1;
    private KeyPair $keyPair2;

    protected function setUp(): void
    {
        $this->keyPair1 = KeyPair::generate($this->signatureService());
        $this->keyPair2 = KeyPair::generate($this->signatureService());
    }

    public function testBuildReplyToRootPost(): void
    {
        $rootEvent = $this->createEvent($this->keyPair1);
        $signedRoot = $rootEvent->sign($this->keyPair1->getPrivateKey(), $this->signatureService());

        $tags = ReplyTagBuilder::build($signedRoot);

        $tagArray = $tags->toArray();
        $this->assertCount(2, $tagArray);
        $this->assertSame('e', $tagArray[0][0]);
        $this->assertSame($signedRoot->getId()->toHex(), $tagArray[0][1]);
        $this->assertSame('', $tagArray[0][2]);
        $this->assertSame('root', $tagArray[0][3]);
        $this->assertSame('p', $tagArray[1][0]);
        $this->assertSame($signedRoot->getPubkey()->toHex(), $tagArray[1][1]);
    }

    public function testBuildReplyToReply(): void
    {
        $rootEvent = $this->createEvent($this->keyPair1);
        $signedRoot = $rootEvent->sign($this->keyPair1->getPrivateKey(), $this->signatureService());

        $replyEvent = $this->createEvent($this->keyPair2);
        $signedReply = $replyEvent->sign($this->keyPair2->getPrivateKey(), $this->signatureService());

        $tags = ReplyTagBuilder::build($signedReply, $signedRoot);

        $tagArray = $tags->toArray();
        $this->assertCount(4, $tagArray);

        $this->assertSame('e', $tagArray[0][0]);
        $this->assertSame($signedRoot->getId()->toHex(), $tagArray[0][1]);
        $this->assertSame('root', $tagArray[0][3]);

        $this->assertSame('e', $tagArray[1][0]);
        $this->assertSame($signedReply->getId()->toHex(), $tagArray[1][1]);
        $this->assertSame('reply', $tagArray[1][3]);

        $this->assertSame('p', $tagArray[2][0]);
        $this->assertSame($signedRoot->getPubkey()->toHex(), $tagArray[2][1]);

        $this->assertSame('p', $tagArray[3][0]);
        $this->assertSame($signedReply->getPubkey()->toHex(), $tagArray[3][1]);
    }

    public function testBuildFromValues(): void
    {
        $replyToId = EventId::fromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test event ID');
        $replyToAuthor = $this->keyPair1->getPublicKey();
        $rootId = EventId::fromHex(str_repeat('b', 64)) ?? throw new RuntimeException('Invalid test event ID');
        $rootAuthor = $this->keyPair2->getPublicKey();

        $tags = ReplyTagBuilder::buildFromValues($replyToId, $replyToAuthor, $rootId, $rootAuthor);

        $tagArray = $tags->toArray();
        $this->assertCount(4, $tagArray);
        $this->assertSame($rootId->toHex(), $tagArray[0][1]);
        $this->assertSame('root', $tagArray[0][3]);
        $this->assertSame($replyToId->toHex(), $tagArray[1][1]);
        $this->assertSame('reply', $tagArray[1][3]);
    }

    public function testBuildFromValuesSameAuthor(): void
    {
        $replyToId = EventId::fromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test event ID');
        $rootId = EventId::fromHex(str_repeat('b', 64)) ?? throw new RuntimeException('Invalid test event ID');
        $author = $this->keyPair1->getPublicKey();

        $tags = ReplyTagBuilder::buildFromValues($replyToId, $author, $rootId, $author);

        $tagArray = $tags->toArray();
        $this->assertCount(3, $tagArray);
        $pTags = array_filter($tagArray, static fn ($t) => 'p' === $t[0]);
        $this->assertCount(1, $pTags);
    }

    private function createEvent(KeyPair $keyPair): Event
    {
        return new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Test content')
        );
    }
}
