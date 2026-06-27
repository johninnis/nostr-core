<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractorInterface;
use Innis\Nostr\Core\Domain\Service\EventReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferenceExtractorTest extends TestCase
{
    public function testExtractReferencesOrchestatesAllServices(): void
    {
        $contentExtractor = $this->createStub(ContentReferenceExtractorInterface::class);
        $contentExtractor
            ->method('extractContentReferences')
            ->willReturn(new ContentReferenceCollection([]));

        $service = new EventReferenceExtractor($contentExtractor);

        $result = $service->extractReferences($this->createTestEvent());

        $this->assertCount(1, $result->getTagReferences()->getEvents());
        $this->assertCount(1, $result->getTagReferences()->getPubkeys());
        $this->assertSame([], $result->getContentReferences()->toArray());
        $this->assertTrue($result->getReplyChain()->isReply());
        $this->assertFalse($result->getQuoteAnalysis()->isQuote());
    }

    public function testMergesAllReferencesCorrectly(): void
    {
        $tags = [
            Tag::fromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'root']),
            Tag::fromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
            Tag::fromArray(['q', '3333333333333333333333333333333333333333333333333333333333333333', '', '4444444444444444444444444444444444444444444444444444444444444444']),
        ];

        $event = new Event(
            PublicKey::fromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt(1),
            new TagCollection($tags),
            EventContent::fromString('Test content')
        );

        $contentExtractor = $this->createStub(ContentReferenceExtractorInterface::class);
        $contentExtractor->method('extractContentReferences')->willReturn(new ContentReferenceCollection([]));

        $service = new EventReferenceExtractor($contentExtractor);

        $result = $service->extractReferences($event);

        $allEventIds = $result->getAllEventIds();
        $allPublicKeys = $result->getAllPublicKeys();

        $eventIdHexes = $allEventIds->toHexes();
        $pubkeyHexes = $allPublicKeys->toHexes();

        $this->assertContains('1111111111111111111111111111111111111111111111111111111111111111', $eventIdHexes);
        $this->assertContains('3333333333333333333333333333333333333333333333333333333333333333', $eventIdHexes);
        $this->assertContains('2222222222222222222222222222222222222222222222222222222222222222', $pubkeyHexes);
        $this->assertContains('4444444444444444444444444444444444444444444444444444444444444444', $pubkeyHexes);
    }

    public function testDeduplicatesReferences(): void
    {
        $tags = [
            Tag::fromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay1.com']),
            Tag::fromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay2.com']),
            Tag::fromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
            Tag::fromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
        ];

        $event = new Event(
            PublicKey::fromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt(1),
            new TagCollection($tags),
            EventContent::fromString('Test content')
        );

        $contentExtractor = $this->createStub(ContentReferenceExtractorInterface::class);
        $contentExtractor->method('extractContentReferences')->willReturn(new ContentReferenceCollection([]));

        $service = new EventReferenceExtractor($contentExtractor);

        $result = $service->extractReferences($event);

        $this->assertCount(1, $result->getAllEventIds());
        $this->assertCount(1, $result->getAllPublicKeys());

        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', $result->getAllEventIds()->toArray()[0]->toHex());
        $this->assertEquals('2222222222222222222222222222222222222222222222222222222222222222', $result->getAllPublicKeys()->toArray()[0]->toHex());
    }

    public function testGenericRepostKind16IsReportedAsRepost(): void
    {
        $event = new Event(
            PublicKey::fromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt(EventKind::GENERIC_REPOST),
            new TagCollection(),
            EventContent::fromString('Test content')
        );

        $contentExtractor = $this->createStub(ContentReferenceExtractorInterface::class);
        $contentExtractor->method('extractContentReferences')->willReturn(new ContentReferenceCollection([]));

        $result = new EventReferenceExtractor($contentExtractor)->extractReferences($event);

        $this->assertTrue($result->getQuoteAnalysis()->isRepost());
    }

    private function createTestEvent(): Event
    {
        $tags = [
            Tag::fromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com']),
            Tag::fromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
        ];

        return new Event(
            PublicKey::fromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt(1),
            new TagCollection($tags),
            EventContent::fromString('Test content')
        );
    }
}
