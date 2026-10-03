<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\RelayHintExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayHintExtractorTest extends TestCase
{
    private const string EVENT_ID = 'a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0a0';
    private const string OTHER_EVENT_ID = 'c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1c1';
    private const string PUBKEY = 'b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2';

    private static function reference(string ...$relayUrls): string
    {
        $relays = [];
        foreach ($relayUrls as $url) {
            $relay = RelayUrl::tryFromString($url);
            if (null !== $relay) {
                $relays[] = $relay;
            }
        }

        $nevent = Nevent::tryFromEventId(
            EventId::tryFromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid test event id'),
            new RelayUrlCollection($relays),
        ) ?? throw new RuntimeException('Invalid test nevent');

        return 'nostr:'.$nevent->toBech32();
    }

    public function testExtractRelayHintsFromRTags(): void
    {
        $event = $this->makeEvent([
            ['r', 'wss://relay.example.com'],
            ['r', 'wss://nostr.example.org'],
            ['p', self::PUBKEY, 'wss://third.com'],
        ]);

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

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

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

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

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

        $this->assertContains('wss://quote-relay.com', $relays);
        $this->assertContains('wss://addressable-relay.com', $relays);
    }

    public function testExtractRelayHintFromNeventInContent(): void
    {
        $relays = $this->relayStrings(
            RelayHintExtractor::extract($this->makeEvent([], content: self::reference('wss://decoded-relay.com')))
        );

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://decoded-relay.com', $relays[0]);
    }

    public function testNeventWithoutRelaysYieldsNoHints(): void
    {
        $this->assertEmpty(
            RelayHintExtractor::extract($this->makeEvent([], content: self::reference()))->toArray()
        );
    }

    public function testContentWithoutReferencesYieldsNoHints(): void
    {
        $this->assertEmpty(RelayHintExtractor::extract($this->makeEvent([]))->toArray());
    }

    public function testExtractsEveryRelayHintFromAContentReference(): void
    {
        $relays = $this->relayStrings(
            RelayHintExtractor::extract($this->makeEvent([], content: self::reference('wss://relay1.com', 'wss://relay2.com')))
        );

        $this->assertCount(2, $relays);
        $this->assertContains('wss://relay1.com', $relays);
        $this->assertContains('wss://relay2.com', $relays);
    }

    public function testExtractRelayHintsFromContentWithMultipleReferences(): void
    {
        $relays = $this->relayStrings(
            RelayHintExtractor::extract($this->makeEvent([], content: implode(' ', [self::reference('wss://relay1.com'), self::reference('wss://relay2.com')])))
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

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

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
        ], content: self::reference('wss://relay.com'));

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

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

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

        $this->assertCount(1, $relays);
        $this->assertEquals('wss://valid-relay.com', $relays[0]);
    }

    public function testReturnsRelayUrlCollection(): void
    {
        $relays = RelayHintExtractor::extract($this->makeEvent([['r', 'wss://relay.example.com']]));

        $this->assertSame(['wss://relay.example.com'], $this->relayStrings($relays));
    }

    /**
     * @return list<string>
     */
    private function relayStrings(RelayUrlCollection $relays): array
    {
        return array_map(static fn (RelayUrl $relay): string => (string) $relay, $relays->toArray());
    }

    /**
     * @param list<list<string>> $tagArrays
     */
    private function makeEvent(array $tagArrays, int $kind = 1, string $content = ''): Event
    {
        $tags = [];
        foreach ($tagArrays as $tagArray) {
            $tags[] = Tag::tryFromArray($tagArray);
        }

        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210') ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt($kind),
            EventContent::fromString($content),
            new TagCollection($tags),
            Timestamp::fromInt(1234567890),
        ));
    }

    public function testExtractsRelayHintsFromTheCommentRootScope(): void
    {
        $event = $this->makeEvent([
            ['E', self::EVENT_ID, 'wss://root-event.com', self::PUBKEY],
            ['A', '30023:'.self::PUBKEY.':my-article', 'wss://root-address.com'],
            ['P', self::PUBKEY, 'wss://root-author.com'],
            ['K', '30023'],
        ], kind: 1111);

        $relays = $this->relayStrings(RelayHintExtractor::extract($event));

        $this->assertEqualsCanonicalizing(['wss://root-event.com', 'wss://root-address.com', 'wss://root-author.com'], $relays);
    }
}
