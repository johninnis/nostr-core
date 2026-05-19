<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Adapter;

use Exception;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Adapter\RelayHintExtractorAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class RelayHintExtractorAdapterTest extends TestCase
{
    public function testExtractRelayHintsFromRTags(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'wss://relay.example.com'],
            ['r', 'wss://nostr.example.org'],
            ['p', 'pubkey123', 'wss://third.com'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHintsFromTags($tags);

        $this->assertCount(3, $relays);
        $this->assertEquals('wss://relay.example.com', (string) $relays[0]);
        $this->assertEquals('wss://nostr.example.org', (string) $relays[1]);
        $this->assertEquals('wss://third.com', (string) $relays[2]);
    }

    public function testExtractRelayHintsFromEventAndPubkeyTags(): void
    {
        $tags = TagCollection::fromArray([
            ['e', 'event123', 'wss://relay1.com'],
            ['p', 'pubkey456', 'wss://relay2.com', 'petname'],
            ['e', 'event789'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHintsFromTags($tags);

        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay1.com', (string) $relays[0]);
        $this->assertEquals('wss://relay2.com', (string) $relays[1]);
    }

    public function testExtractRelayHintFromNevent(): void
    {
        $nevent = 'nevent1abc123';
        $bech32Encoder = $this->createMock(Bech32EncoderInterface::class);
        $bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with($nevent)
            ->willReturn(['relays' => ['wss://decoded-relay.com']]);

        $relay = $this->makeExtractor($bech32Encoder)->extractRelayHintFromNevent($nevent);

        $this->assertNotNull($relay);
        $this->assertEquals('wss://decoded-relay.com', (string) $relay);
    }

    public function testExtractRelayHintFromNeventReturnsNullWhenNoRelays(): void
    {
        $nevent = 'nevent1abc123';
        $bech32Encoder = $this->createMock(Bech32EncoderInterface::class);
        $bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with($nevent)
            ->willReturn(['relays' => []]);

        $this->assertNull($this->makeExtractor($bech32Encoder)->extractRelayHintFromNevent($nevent));
    }

    public function testExtractRelayHintFromNeventHandlesException(): void
    {
        $nevent = 'nevent1invalid';
        $bech32Encoder = $this->createMock(Bech32EncoderInterface::class);
        $bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with($nevent)
            ->willThrowException(new Exception('Invalid nevent'));

        $this->assertNull($this->makeExtractor($bech32Encoder)->extractRelayHintFromNevent($nevent));
    }

    public function testExtractRelayHintsFromContentWithNevent(): void
    {
        $content = 'Check out this event: nevent1abc123 and this one nevent1def456';

        $bech32Encoder = $this->createMock(Bech32EncoderInterface::class);
        $bech32Encoder
            ->expects($this->exactly(2))
            ->method('decodeComplexEntity')
            ->willReturnMap([
                ['nevent1abc123', ['relays' => ['wss://relay1.com']]],
                ['nevent1def456', ['relays' => ['wss://relay2.com']]],
            ]);

        $relays = $this->makeExtractor($bech32Encoder)->extractRelayHintsFromContent($content);

        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay1.com', (string) $relays[0]);
        $this->assertEquals('wss://relay2.com', (string) $relays[1]);
    }

    public function testExtractRelayHintsFromKind6RepostEvent(): void
    {
        $event = $this->createRepostEvent([
            ['e', 'event123', 'wss://repost-relay.com'],
            ['p', 'author456'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHints($event);

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://repost-relay.com', (string) $relays[0]);
    }

    public function testExtractRelayHintsDeduplicates(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'wss://relay.com'],
            ['r', 'wss://relay.com'],
            ['e', 'event123', 'wss://relay.com'],
            ['p', 'pubkey456', 'wss://different.com'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHintsFromTags($tags);

        $this->assertCount(2, $relays);
        $relayUrls = array_map(static fn ($relay) => (string) $relay, $relays);
        $this->assertContains('wss://relay.com', $relayUrls);
        $this->assertContains('wss://different.com', $relayUrls);
    }

    public function testExtractRelayHintsSkipsInvalidUrls(): void
    {
        $tags = TagCollection::fromArray([
            ['r', 'invalid-url'],
            ['r', 'wss://valid-relay.com'],
            ['e', 'event123', 'not-a-url'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHintsFromTags($tags);

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://valid-relay.com', (string) $relays[0]);
    }

    private function makeExtractor(?Bech32EncoderInterface $bech32Encoder = null): RelayHintExtractorAdapter
    {
        return new RelayHintExtractorAdapter(
            $bech32Encoder ?? $this->createStub(Bech32EncoderInterface::class),
            new NullLogger()
        );
    }

    private function createRepostEvent(array $tagArrays): Event
    {
        $tags = [];
        foreach ($tagArrays as $tagArray) {
            $tags[] = Tag::fromArray($tagArray);
        }

        return new Event(
            PublicKey::fromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt(6),
            new TagCollection($tags),
            EventContent::fromString('')
        );
    }
}
