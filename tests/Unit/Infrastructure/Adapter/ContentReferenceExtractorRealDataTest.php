<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Adapter;

use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Infrastructure\Adapter\ContentReferenceExtractorAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ContentReferenceExtractorRealDataTest extends TestCase
{
    private ContentReferenceExtractorAdapter $extractor;
    private Bech32EncoderInterface&MockObject $bech32Encoder;

    protected function setUp(): void
    {
        $this->bech32Encoder = $this->createMock(Bech32EncoderInterface::class);
        $this->extractor = new ContentReferenceExtractorAdapter($this->bech32Encoder);
    }

    public function testExtractFromTestEventWithNevent(): void
    {
        $content = EventContent::fromString("Getting married and having kids will make you level up as a man faster and further than anything else.\n\nnostr:nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft ");

        $this->bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with('nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft')
            ->willReturn([
                'type' => 'event',
                'event_id' => 'd359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771',
            ]);

        $references = $this->extractor->extractContentReferences($content);

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertEquals('event', $references[0]->getDecodedType());
        $this->assertNotNull($references[0]->getEventId());
        $this->assertEquals('d359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771', $references[0]->getEventId()->toHex());
    }

    public function testExtractFromTestEventWithNaddr(): void
    {
        $content = EventContent::fromString("Do not be shocked if the oft talked about theory of a gold-backed BRICS currency becomes a reality this Fall.\n\nnostr:naddr1qvzqqqr4gupzq3e0gs8jnmued6f2rp4c6vs07xqvs4vs8zpwt82smcdch4txjvq7qys8wumn8ghj7cnfw33k76twd4shs6tdv9kxjum5wvhx7mnvd9hx2tcpzemhxue69uhk2er9dchxummnw3ezumrpdejz7qqlvd5xjmnp945hxttswfjhqurfdenj6en0wgkhxmmdv46xs6twvuk7xtlq");

        $this->bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with('naddr1qvzqqqr4gupzq3e0gs8jnmued6f2rp4c6vs07xqvs4vs8zpwt82smcdch4txjvq7qys8wumn8ghj7cnfw33k76twd4shs6tdv9kxjum5wvhx7mnvd9hx2tcpzemhxue69uhk2er9dchxummnw3ezumrpdejz7qqlvd5xjmnp945hxttswfjhqurfdenj6en0wgkhxmmdv46xs6twvuk7xtlq')
            ->willReturn([
                'type' => 'address',
                'event_id' => '5570e03f9a762570a1668508895316500b38ae3f9b311871dbb637f2844d0c67',
            ]);

        $references = $this->extractor->extractContentReferences($content);

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertEquals('address', $references[0]->getDecodedType());
        $this->assertNotNull($references[0]->getEventId());
        $this->assertEquals('5570e03f9a762570a1668508895316500b38ae3f9b311871dbb637f2844d0c67', $references[0]->getEventId()->toHex());
    }

    public function testExtractFromPlainTextEventReturnsNoReferences(): void
    {
        $content = EventContent::fromString("open source software is powerful because anyone can verify, modify, distribute, and use without permission\n\na robust open source ecosystem empowers all of us to take agency over our lives\n\nfighting with people over what software they run is mostly unproductive, run what you want, thats the whole point");

        $references = $this->extractor->extractContentReferences($content);

        $this->assertEmpty($references);
    }
}
