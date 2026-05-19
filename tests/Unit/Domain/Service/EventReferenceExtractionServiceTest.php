<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\EventReferences;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractorInterface;
use Innis\Nostr\Core\Domain\Service\EventReferenceExtractionService;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferenceExtractionServiceTest extends TestCase
{
    public function testExtractReferencesOrchestatesAllServices(): void
    {
        $contentExtractor = $this->createMock(ContentReferenceExtractorInterface::class);
        $contentExtractor
            ->expects($this->once())
            ->method('extractContentReferences')
            ->with($this->isInstanceOf(EventContent::class))
            ->willReturn([]);

        $service = new EventReferenceExtractionService($contentExtractor);

        $result = $service->extractReferences($this->createTestEvent());

        $this->assertInstanceOf(EventReferences::class, $result);
        $this->assertCount(1, $result->getTagReferences()->getEvents());
        $this->assertCount(1, $result->getTagReferences()->getPubkeys());
        $this->assertSame([], $result->getContentReferences());
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
        $contentExtractor->method('extractContentReferences')->willReturn([]);

        $service = new EventReferenceExtractionService($contentExtractor);

        $result = $service->extractReferences($event);

        $allEventIds = $result->getAllEventIds();
        $allPublicKeys = $result->getAllPublicKeys();

        $eventIdHexes = array_map(static fn ($id) => $id->toHex(), $allEventIds);
        $pubkeyHexes = array_map(static fn ($key) => $key->toHex(), $allPublicKeys);

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
        $contentExtractor->method('extractContentReferences')->willReturn([]);

        $service = new EventReferenceExtractionService($contentExtractor);

        $result = $service->extractReferences($event);

        $this->assertCount(1, $result->getAllEventIds());
        $this->assertCount(1, $result->getAllPublicKeys());

        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', $result->getAllEventIds()[0]->toHex());
        $this->assertEquals('2222222222222222222222222222222222222222222222222222222222222222', $result->getAllPublicKeys()[0]->toHex());
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
