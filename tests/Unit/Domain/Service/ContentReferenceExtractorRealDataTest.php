<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractor;
use Innis\Nostr\Core\Domain\Service\Nip19CodecInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;
use PHPUnit\Framework\TestCase;

final class ContentReferenceExtractorRealDataTest extends TestCase
{
    private static function decoded(string $type, string $eventIdHex): DecodedNip19Entity
    {
        return new DecodedNip19Entity($type, eventId: EventId::fromHex($eventIdHex));
    }

    public function testExtractFromTestEventWithNevent(): void
    {
        $content = EventContent::fromString("Getting married and having kids will make you level up as a man faster and further than anything else.\n\nnostr:nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft ");

        $bech32Encoder = $this->createMock(Nip19CodecInterface::class);
        $bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with('nevent1qvzqqqqqqypzqxh7p36w84mcf6af8f0rlf255mhtqxfg6ynnnt5t5jpj0p5q3cmdqqsdxkwnafkgnfg68g6xkqau25548fewg440x5s8r4uud0sednkewugdc6hft')
            ->willReturn(self::decoded(DecodedNip19Entity::TYPE_EVENT, 'd359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771'));

        $references = (new ContentReferenceExtractor($bech32Encoder))->extractContentReferences($content);

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertEquals('event', $references[0]->getDecodedType());
        $this->assertNotNull($references[0]->getEventId());
        $this->assertEquals('d359d3ea6c89a51a3a346b03bc552953a72e456af352071d79c6be196ced9771', $references[0]->getEventId()->toHex());
    }

    public function testExtractFromTestEventWithNaddr(): void
    {
        $content = EventContent::fromString("Do not be shocked if the oft talked about theory of a gold-backed BRICS currency becomes a reality this Fall.\n\nnostr:naddr1qvzqqqr4gupzq3e0gs8jnmued6f2rp4c6vs07xqvs4vs8zpwt82smcdch4txjvq7qys8wumn8ghj7cnfw33k76twd4shs6tdv9kxjum5wvhx7mnvd9hx2tcpzemhxue69uhk2er9dchxummnw3ezumrpdejz7qqlvd5xjmnp945hxttswfjhqurfdenj6en0wgkhxmmdv46xs6twvuk7xtlq");

        $bech32Encoder = $this->createMock(Nip19CodecInterface::class);
        $bech32Encoder
            ->expects($this->once())
            ->method('decodeComplexEntity')
            ->with('naddr1qvzqqqr4gupzq3e0gs8jnmued6f2rp4c6vs07xqvs4vs8zpwt82smcdch4txjvq7qys8wumn8ghj7cnfw33k76twd4shs6tdv9kxjum5wvhx7mnvd9hx2tcpzemhxue69uhk2er9dchxummnw3ezumrpdejz7qqlvd5xjmnp945hxttswfjhqurfdenj6en0wgkhxmmdv46xs6twvuk7xtlq')
            ->willReturn(self::decoded(DecodedNip19Entity::TYPE_ADDRESS, '5570e03f9a762570a1668508895316500b38ae3f9b311871dbb637f2844d0c67'));

        $references = (new ContentReferenceExtractor($bech32Encoder))->extractContentReferences($content);

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertEquals('address', $references[0]->getDecodedType());
        $this->assertNotNull($references[0]->getEventId());
        $this->assertEquals('5570e03f9a762570a1668508895316500b38ae3f9b311871dbb637f2844d0c67', $references[0]->getEventId()->toHex());
    }

    public function testExtractFromPlainTextEventReturnsNoReferences(): void
    {
        $content = EventContent::fromString("open source software is powerful because anyone can verify, modify, distribute, and use without permission\n\na robust open source ecosystem empowers all of us to take agency over our lives\n\nfighting with people over what software they run is mostly unproductive, run what you want, thats the whole point");

        $extractor = new ContentReferenceExtractor(
            $this->createStub(Nip19CodecInterface::class)
        );

        $this->assertEmpty($extractor->extractContentReferences($content));
    }
}
