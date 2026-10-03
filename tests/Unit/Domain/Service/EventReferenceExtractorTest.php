<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\EventReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferenceExtractorTest extends TestCase
{
    private const string NOTE = 'note1xvenxvenxvenxvenxvenxvenxvenxvenxvenxvenxvenxvenxvesq7p6ma';
    private const string NADDR = 'naddr1qqrkzun5d93kcegzypzyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zygqcyqqq823cauq897';
    private const string NPUB = 'npub1g3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zyg3zqysvy7t';

    public function testExtractReferencesOrchestatesAllServices(): void
    {
        $result = EventReferenceExtractor::extract($this->createTestEvent());

        $this->assertCount(1, $result->getTagReferences()->getEvents());
        $this->assertCount(1, $result->getTagReferences()->getPubkeys());
        $this->assertSame([], $result->getContentReferences()->toArray());
        $this->assertTrue($result->getReplyChain()->isReply());
        $this->assertFalse($result->getQuoteAnalysis()->isQuote());
    }

    public function testMergesAllReferencesCorrectly(): void
    {
        $tags = [
            Tag::tryFromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com', 'root']),
            Tag::tryFromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
            Tag::tryFromArray(['q', '3333333333333333333333333333333333333333333333333333333333333333', '', '4444444444444444444444444444444444444444444444444444444444444444']),
        ];

        $event = EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(1),
            EventContent::fromString('Test content'),
            new TagCollection($tags),
            Timestamp::fromInt(1234567890),
        ));

        $result = EventReferenceExtractor::extract($event);

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
            Tag::tryFromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay1.com']),
            Tag::tryFromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay2.com']),
            Tag::tryFromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
            Tag::tryFromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
        ];

        $event = EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(1),
            EventContent::fromString('Test content'),
            new TagCollection($tags),
            Timestamp::fromInt(1234567890),
        ));

        $result = EventReferenceExtractor::extract($event);

        $this->assertCount(1, $result->getAllEventIds());
        $this->assertCount(1, $result->getAllPublicKeys());

        $this->assertEquals('1111111111111111111111111111111111111111111111111111111111111111', $result->getAllEventIds()->toArray()[0]->toHex());
        $this->assertEquals('2222222222222222222222222222222222222222222222222222222222222222', $result->getAllPublicKeys()->toArray()[0]->toHex());
    }

    public function testGenericRepostKind16IsReportedAsRepost(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::GENERIC_REPOST),
            EventContent::fromString('Test content'),
            new TagCollection(),
            Timestamp::fromInt(1234567890),
        ));

        $result = EventReferenceExtractor::extract($event);

        $this->assertTrue($result->getQuoteAnalysis()->isRepost());
    }

    #[DataProvider('quotingContent')]
    public function testAShortNoteIsAQuoteWhenItsContentNamesWhatTheTagBuilderWritesAsAQuote(string $content): void
    {
        $this->assertTrue(EventReferenceExtractor::extract($this->shortNote($content))->getQuoteAnalysis()->isQuote());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function quotingContent(): iterable
    {
        yield 'nostr: note' => ['see nostr:'.self::NOTE];
        yield 'bare note' => ['see '.self::NOTE];
        yield 'nostr: naddr' => ['see nostr:'.self::NADDR];
        yield 'bare naddr' => ['see '.self::NADDR];
    }

    public function testAShortNoteNamingOnlyAProfileIsNoQuote(): void
    {
        $this->assertFalse(EventReferenceExtractor::extract($this->shortNote('hi nostr:'.self::NPUB))->getQuoteAnalysis()->isQuote());
    }

    private function shortNote(string $content): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString($content),
            new TagCollection(),
            Timestamp::fromInt(1234567890),
        ));
    }

    private function createTestEvent(): Event
    {
        $tags = [
            Tag::tryFromArray(['e', '1111111111111111111111111111111111111111111111111111111111111111', 'wss://relay.com']),
            Tag::tryFromArray(['p', '2222222222222222222222222222222222222222222222222222222222222222']),
        ];

        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex('1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(1),
            EventContent::fromString('Test content'),
            new TagCollection($tags),
            Timestamp::fromInt(1234567890),
        ));
    }
}
