<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\Nip19CodecInterface;
use Innis\Nostr\Core\Domain\Service\RelayHintExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayHintExtractorTest extends TestCase
{
    private static function decoded(string ...$relayUrls): DecodedNip19Entity
    {
        $relays = [];
        foreach ($relayUrls as $url) {
            $relay = RelayUrl::fromString($url);
            if (null !== $relay) {
                $relays[] = $relay;
            }
        }

        return new DecodedNip19Entity(Nip19EntityType::Event, relays: new RelayUrlCollection($relays));
    }

    public function testExtractRelayHintsFromRTags(): void
    {
        $event = $this->makeEvent([
            ['r', 'wss://relay.example.com'],
            ['r', 'wss://nostr.example.org'],
            ['p', 'pubkey123', 'wss://third.com'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHints($event)->toArray();

        $this->assertCount(3, $relays);
        $this->assertEquals('wss://relay.example.com', (string) $relays[0]);
        $this->assertEquals('wss://nostr.example.org', (string) $relays[1]);
        $this->assertEquals('wss://third.com', (string) $relays[2]);
    }

    public function testExtractRelayHintsFromEventAndPubkeyTags(): void
    {
        $event = $this->makeEvent([
            ['e', 'event123', 'wss://relay1.com'],
            ['p', 'pubkey456', 'wss://relay2.com', 'petname'],
            ['e', 'event789'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHints($event)->toArray();

        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay1.com', (string) $relays[0]);
        $this->assertEquals('wss://relay2.com', (string) $relays[1]);
    }

    public function testExtractRelayHintFromNeventInContent(): void
    {
        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded('wss://decoded-relay.com'));

        $relays = $this->makeExtractor($bech32Encoder)->extractRelayHints($this->makeEvent([], 'nevent1abc123'))->toArray();

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://decoded-relay.com', (string) $relays[0]);
    }

    public function testNeventWithoutRelaysYieldsNoHints(): void
    {
        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded());

        $this->assertEmpty($this->makeExtractor($bech32Encoder)->extractRelayHints($this->makeEvent([], 'nevent1abc123'))->toArray());
    }

    public function testUndecodableNeventYieldsNoHints(): void
    {
        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(null);

        $this->assertEmpty($this->makeExtractor($bech32Encoder)->extractRelayHints($this->makeEvent([], 'nevent1invalid'))->toArray());
    }

    public function testExtractRelayHintsFromContentWithNevent(): void
    {
        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturnMap([
                ['nevent1abc123', self::decoded('wss://relay1.com')],
                ['nevent1def456', self::decoded('wss://relay2.com')],
            ]);

        $event = $this->makeEvent([], 'Check out this event: nevent1abc123 and this one nevent1def456');

        $relays = $this->makeExtractor($bech32Encoder)->extractRelayHints($event)->toArray();

        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay1.com', (string) $relays[0]);
        $this->assertEquals('wss://relay2.com', (string) $relays[1]);
    }

    public function testExtractRelayHintsFromKind6RepostEvent(): void
    {
        $event = $this->makeEvent([
            ['e', 'event123', 'wss://repost-relay.com'],
            ['p', 'author456'],
        ], '', 6);

        $relays = $this->makeExtractor()->extractRelayHints($event)->toArray();

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://repost-relay.com', (string) $relays[0]);
    }

    public function testExtractRelayHintsDeduplicates(): void
    {
        $event = $this->makeEvent([
            ['r', 'wss://relay.com'],
            ['r', 'wss://relay.com'],
            ['e', 'event123', 'wss://relay.com'],
            ['p', 'pubkey456', 'wss://different.com'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHints($event)->toArray();

        $this->assertCount(2, $relays);
        $relayUrls = array_map(static fn ($relay) => (string) $relay, $relays);
        $this->assertContains('wss://relay.com', $relayUrls);
        $this->assertContains('wss://different.com', $relayUrls);
    }

    public function testExtractRelayHintsSkipsInvalidUrls(): void
    {
        $event = $this->makeEvent([
            ['r', 'invalid-url'],
            ['r', 'wss://valid-relay.com'],
            ['e', 'event123', 'not-a-url'],
        ]);

        $relays = $this->makeExtractor()->extractRelayHints($event)->toArray();

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://valid-relay.com', (string) $relays[0]);
    }

    public function testReturnsRelayUrlCollection(): void
    {
        $relays = $this->makeExtractor()->extractRelayHints($this->makeEvent([['r', 'wss://relay.example.com']]));

        $this->assertInstanceOf(RelayUrlCollection::class, $relays);
    }

    private function makeExtractor(?Nip19CodecInterface $bech32Encoder = null): RelayHintExtractor
    {
        return new RelayHintExtractor(
            $bech32Encoder ?? $this->createStub(Nip19CodecInterface::class),
        );
    }

    private function makeEvent(array $tagArrays, string $content = '', int $kind = 1): Event
    {
        $tags = [];
        foreach ($tagArrays as $tagArray) {
            $tags[] = Tag::fromArray($tagArray);
        }

        return new Event(
            PublicKey::fromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1234567890),
            EventKind::fromInt($kind),
            new TagCollection($tags),
            EventContent::fromString($content)
        );
    }
}
