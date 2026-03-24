<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Service\RelayHintExtractorAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayHintExtractorRealDataTest extends TestCase
{
    private RelayHintExtractorAdapter $extractor;
    private Bech32EncoderInterface&MockObject $bech32Encoder;

    protected function setUp(): void
    {
        $this->bech32Encoder = $this->createMock(Bech32EncoderInterface::class);
        $this->extractor = new RelayHintExtractorAdapter($this->bech32Encoder, $this->createMock(\Psr\Log\LoggerInterface::class));
    }

    public function testExtractRelayHintsFromSecondTestEventTags(): void
    {
        $tags = TagCollection::fromArray([
            ['e', 'd359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771', '', 'mention'],
            ['r', 'wss://nos.lol/'],
            ['r', 'wss://relay.primal.net/'],
            ['r', 'wss://nostr.mom/'],
            ['r', 'wss://nostr.oxtr.dev/'],
            ['r', 'wss://relay.damus.io/'],
            ['r', 'wss://bitcoiner.social/'],
            ['r', 'wss://nostr.bitcoiner.social/'],
            ['r', 'wss://relay.noderunners.network/'],
            ['r', 'wss://nostr-pub.wellorder.net/'],
        ]);

        $relayHints = $this->extractor->extractRelayHintsFromTags($tags);

        $relayStrings = array_map(static fn ($relay) => (string) $relay, $relayHints);

        $this->assertCount(9, $relayHints);
        $this->assertContains('wss://nos.lol', $relayStrings);
        $this->assertContains('wss://relay.primal.net', $relayStrings);
        $this->assertContains('wss://nostr.mom', $relayStrings);
        $this->assertContains('wss://nostr.oxtr.dev', $relayStrings);
        $this->assertContains('wss://relay.damus.io', $relayStrings);
        $this->assertContains('wss://bitcoiner.social', $relayStrings);
        $this->assertContains('wss://nostr.bitcoiner.social', $relayStrings);
        $this->assertContains('wss://relay.noderunners.network', $relayStrings);
        $this->assertContains('wss://nostr-pub.wellorder.net', $relayStrings);
    }

    public function testExtractRelayHintsFromThirdTestEventTags(): void
    {
        $tags = TagCollection::fromArray([
            ['a', '30023:472f440f29ef996e92a186b8d320ff180c855903882e59d50de1b8bd5669301e:china-is-prepping-for-something', 'wss://bitcoinmaximalists.online/', 'mention'],
            ['r', 'wss://bitcoinmaximalists.online/'],
            ['r', 'wss://eden.nostr.land/'],
            ['r', 'wss://nos.lol/'],
            ['r', 'wss://nostr.oxtr.dev/'],
            ['r', 'wss://relay.bitcoinpark.com/'],
            ['r', 'wss://relay.damus.io/'],
            ['r', 'wss://relay.snort.social/'],
        ]);

        $relayHints = $this->extractor->extractRelayHintsFromTags($tags);

        $relayStrings = array_map(static fn ($relay) => (string) $relay, $relayHints);

        $this->assertCount(7, $relayHints);
        $this->assertContains('wss://bitcoinmaximalists.online', $relayStrings);
        $this->assertContains('wss://eden.nostr.land', $relayStrings);
        $this->assertContains('wss://nos.lol', $relayStrings);
        $this->assertContains('wss://nostr.oxtr.dev', $relayStrings);
        $this->assertContains('wss://relay.bitcoinpark.com', $relayStrings);
        $this->assertContains('wss://relay.damus.io', $relayStrings);
        $this->assertContains('wss://relay.snort.social', $relayStrings);
    }

    public function testExtractRelayHintsFromSecondTestEventContent(): void
    {
        $content = "Getting married and having kids will make you level up as a man faster and further than anything else.\n\nnostr:nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft ";

        $this->bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with('nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft')
            ->willReturn([
                'decoded_type' => 'nevent',
                'event_id' => 'd359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771',
                'relays' => ['wss://relay.primal.net/', 'wss://nos.lol/'],
            ]);

        $relayHints = $this->extractor->extractRelayHintsFromContent($content);

        $this->assertCount(1, $relayHints);
        $this->assertEquals('wss://relay.primal.net', (string) $relayHints[0]);
    }

    public function testExtractRelayHintsFromThirdTestEventContent(): void
    {
        $content = "Do not be shocked if the oft talked about theory of a gold-backed BRICS currency becomes a reality this Fall.\n\nnostr:naddr1qvzqqqr4gupzq3e0gs8jnmued6f2rp4c6vs07xqvs4vs8zpwt82smcdch4txjvq7qys8wumn8ghj7cnfw33k76twd4shs6tdv9kxjum5wvhx7mnvd9hx2tcpzemhxue69uhk2er9dchxummnw3ezumrpdejz7qqlvd5xjmnp945hxttswfjhqurfdenj6en0wgkhxmmdv46xs6twvuk7xtlq";

        $relayHints = $this->extractor->extractRelayHintsFromContent($content);

        $this->assertEmpty($relayHints);
    }

    public function testExtractRelayHintsFromFirstTestEvent(): void
    {
        $content = "open source software is powerful because anyone can verify, modify, distribute, and use without permission\n\na robust open source ecosystem empowers all of us to take agency over our lives\n\nfighting with people over what software they run is mostly unproductive, run what you want, thats the whole point";

        $relayHints = $this->extractor->extractRelayHintsFromContent($content);

        $this->assertEmpty($relayHints);
    }

    public function testExtractRelayHintsFromFullSecondTestEvent(): void
    {
        $event = new Event(
            PublicKey::fromHex('7b3f7803750746f455413a221f80965eecb69ef308f2ead1da89cc2c8912e968') ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1756903083),
            EventKind::fromInt(1),
            TagCollection::fromArray([
                ['e', 'd359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771', '', 'mention'],
                ['r', 'wss://nos.lol/'],
                ['r', 'wss://relay.primal.net/'],
                ['r', 'wss://nostr.mom/'],
            ]),
            EventContent::fromString("Getting married and having kids will make you level up as a man faster and further than anything else.\n\nnostr:nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft ")
        );

        $this->bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->willReturn([
                'decoded_type' => 'nevent',
                'relays' => ['wss://relay.damus.io/'],
            ]);

        $relayHints = $this->extractor->extractRelayHints($event);

        $relayStrings = array_map(static fn ($relay) => (string) $relay, $relayHints);

        $this->assertCount(4, $relayHints);
        $this->assertContains('wss://nos.lol', $relayStrings);
        $this->assertContains('wss://relay.primal.net', $relayStrings);
        $this->assertContains('wss://nostr.mom', $relayStrings);
        $this->assertContains('wss://relay.damus.io', $relayStrings);
    }
}
