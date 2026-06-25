<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractorInterface;
use Innis\Nostr\Core\Domain\Service\RelayHintExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayHintExtractorTest extends TestCase
{
    private const EVENT_ID = 'a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0';
    private const OTHER_EVENT_ID = 'c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1';
    private const PUBKEY = 'b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2';

    private static function reference(string ...$relayUrls): ContentReference
    {
        $relays = [];
        foreach ($relayUrls as $url) {
            $relay = RelayUrl::fromString($url);
            if (null !== $relay) {
                $relays[] = $relay;
            }
        }

        $decoded = new DecodedNip19Entity(Nip19EntityType::Event, relays: new RelayUrlCollection($relays));

        return new ContentReference(ContentReferenceType::BareNevent, 'nevent1abc', 'nevent1abc', 0, $decoded);
    }

    public function testExtractRelayHintsFromRTags(): void
    {
        $event = $this->makeEvent([
            ['r', 'wss://relay.example.com'],
            ['r', 'wss://nostr.example.org'],
            ['p', self::PUBKEY, 'wss://third.com'],
        ]);

        $relays = $this->relayStrings($this->makeExtractor()->extractRelayHints($event));

        $this->assertCount(3, $relays);
        $this->assertContains('wss://relay.example.com', $relays);
        $this->assertContains('wss://nostr.example.org', $relays);
        $this->assertContains('wss://third.com', $relays);
    }

    public function testExtractRelayHintsFromEventAndPubkeyTags(): void
    {
        $event = $this->makeEvent([
            ['e', self::EVENT_ID, 'wss://relay1.com'],
            ['p', self::PUBKEY, 'wss://relay2.com', 'petname'],
            ['e', self::OTHER_EVENT_ID],
        ]);

        $relays = $this->relayStrings($this->makeExtractor()->extractRelayHints($event));

        $this->assertCount(2, $relays);
        $this->assertContains('wss://relay1.com', $relays);
        $this->assertContains('wss://relay2.com', $relays);
    }

    public function testExtractsRelayHintsFromQuoteAndAddressableTags(): void
    {
        $event = $this->makeEvent([
            ['q', self::EVENT_ID, 'wss://quote-relay.com'],
            ['a', '30023:'.self::PUBKEY.':my-article', 'wss://addressable-relay.com'],
        ]);

        $relays = $this->relayStrings($this->makeExtractor()->extractRelayHints($event));

        $this->assertContains('wss://quote-relay.com', $relays);
        $this->assertContains('wss://addressable-relay.com', $relays);
    }

    public function testExtractRelayHintFromNeventInContent(): void
    {
        $relays = $this->relayStrings(
            $this->makeExtractor(self::reference('wss://decoded-relay.com'))->extractRelayHints($this->makeEvent([]))
        );

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://decoded-relay.com', $relays[0]);
    }

    public function testNeventWithoutRelaysYieldsNoHints(): void
    {
        $this->assertEmpty(
            $this->makeExtractor(self::reference())->extractRelayHints($this->makeEvent([]))->toArray()
        );
    }

    public function testContentWithoutReferencesYieldsNoHints(): void
    {
        $this->assertEmpty($this->makeExtractor()->extractRelayHints($this->makeEvent([]))->toArray());
    }

    public function testExtractsEveryRelayHintFromAContentReference(): void
    {
        $relays = $this->relayStrings(
            $this->makeExtractor(self::reference('wss://relay1.com', 'wss://relay2.com'))->extractRelayHints($this->makeEvent([]))
        );

        $this->assertCount(2, $relays);
        $this->assertContains('wss://relay1.com', $relays);
        $this->assertContains('wss://relay2.com', $relays);
    }

    public function testExtractRelayHintsFromContentWithMultipleReferences(): void
    {
        $relays = $this->relayStrings(
            $this->makeExtractor(self::reference('wss://relay1.com'), self::reference('wss://relay2.com'))->extractRelayHints($this->makeEvent([]))
        );

        $this->assertCount(2, $relays);
        $this->assertContains('wss://relay1.com', $relays);
        $this->assertContains('wss://relay2.com', $relays);
    }

    public function testExtractRelayHintsFromKind6RepostEvent(): void
    {
        $event = $this->makeEvent([
            ['e', self::EVENT_ID, 'wss://repost-relay.com'],
            ['p', self::PUBKEY],
        ], 6);

        $relays = $this->relayStrings($this->makeExtractor()->extractRelayHints($event));

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://repost-relay.com', $relays[0]);
    }

    public function testExtractRelayHintsDeduplicates(): void
    {
        $event = $this->makeEvent([
            ['r', 'wss://relay.com'],
            ['r', 'wss://relay.com'],
            ['e', self::EVENT_ID, 'wss://relay.com'],
            ['p', self::PUBKEY, 'wss://different.com'],
        ]);

        $relays = $this->relayStrings($this->makeExtractor(self::reference('wss://relay.com'))->extractRelayHints($event));

        $this->assertCount(2, $relays);
        $this->assertContains('wss://relay.com', $relays);
        $this->assertContains('wss://different.com', $relays);
    }

    public function testExtractRelayHintsSkipsInvalidUrls(): void
    {
        $event = $this->makeEvent([
            ['r', 'invalid-url'],
            ['r', 'wss://valid-relay.com'],
            ['e', self::EVENT_ID, 'not-a-url'],
        ]);

        $relays = $this->relayStrings($this->makeExtractor()->extractRelayHints($event));

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://valid-relay.com', $relays[0]);
    }

    public function testReturnsRelayUrlCollection(): void
    {
        $relays = $this->makeExtractor()->extractRelayHints($this->makeEvent([['r', 'wss://relay.example.com']]));

        $this->assertInstanceOf(RelayUrlCollection::class, $relays);
    }

    /**
     * @return list<string>
     */
    private function relayStrings(RelayUrlCollection $relays): array
    {
        return array_map(static fn (RelayUrl $relay): string => (string) $relay, $relays->toArray());
    }

    private function makeExtractor(ContentReference ...$references): RelayHintExtractor
    {
        $extractor = $this->createStub(ContentReferenceExtractorInterface::class);
        $extractor
            ->method('extractContentReferences')
            ->willReturn(new ContentReferenceCollection($references));

        return new RelayHintExtractor($extractor);
    }

    /**
     * @param list<list<string>> $tagArrays
     */
    private function makeEvent(array $tagArrays, int $kind = 1): Event
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
            EventContent::fromString('')
        );
    }
}
