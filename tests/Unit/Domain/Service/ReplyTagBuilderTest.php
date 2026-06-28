<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\ReplyTagBuilder;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class ReplyTagBuilderTest extends TestCase
{
    private KeyPair $keyPair1;
    private KeyPair $keyPair2;

    protected function setUp(): void
    {
        $this->keyPair1 = KeyMother::alice();
        $this->keyPair2 = KeyMother::bob();
    }

    public function testBuildReplyToRootPost(): void
    {
        $rootEvent = $this->createEvent($this->keyPair1);
        $signedRoot = $rootEvent->sign($this->keyPair1, FakeSignatureService::accepting());

        $tags = ReplyTagBuilder::buildTags($signedRoot);

        $tagArray = $tags->toJsonArray();
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
        $signedRoot = $rootEvent->sign($this->keyPair1, FakeSignatureService::accepting());

        $replyEvent = $this->createEvent($this->keyPair2);
        $signedReply = $replyEvent->sign($this->keyPair2, FakeSignatureService::accepting());

        $tags = ReplyTagBuilder::buildTags($signedReply, $signedRoot);

        $tagArray = $tags->toJsonArray();
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

    public function testBuildReplyToReplyBySameAuthorDeduplicatesThePTag(): void
    {
        $signedRoot = $this->createEvent($this->keyPair1, 'root content')
            ->sign($this->keyPair1, FakeSignatureService::accepting());
        $signedReply = $this->createEvent($this->keyPair1, 'reply content')
            ->sign($this->keyPair1, FakeSignatureService::accepting());

        $tags = ReplyTagBuilder::buildTags($signedReply, $signedRoot);

        $tagArray = $tags->toJsonArray();
        $this->assertCount(3, $tagArray);

        $this->assertSame($signedRoot->getId()->toHex(), $tagArray[0][1]);
        $this->assertSame('root', $tagArray[0][3]);
        $this->assertSame($signedReply->getId()->toHex(), $tagArray[1][1]);
        $this->assertSame('reply', $tagArray[1][3]);

        $pTags = array_filter($tagArray, static fn (array $tag): bool => 'p' === $tag[0]);
        $this->assertCount(1, $pTags);
    }

    private function createEvent(KeyPair $keyPair, string $content = 'Test content'): Event
    {
        return new Event(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString($content)
        );
    }
}
