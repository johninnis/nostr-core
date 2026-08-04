<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractor;
use Innis\Nostr\Core\Domain\Service\Nip19CodecInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nip19EntityInterface;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Note;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Npub;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceExtractorTest extends TestCase
{
    /**
     * @param list<string> $relayUrls
     */
    private static function decoded(Nip19EntityType $type, ?string $pubkeyHex = null, ?string $eventIdHex = null, array $relayUrls = []): ?Nip19EntityInterface
    {
        $relays = new RelayUrlCollection(array_values(array_filter(array_map(RelayUrl::tryFromString(...), $relayUrls))));
        $publicKey = null !== $pubkeyHex ? PublicKey::tryFromHex($pubkeyHex) : null;
        $eventId = null !== $eventIdHex ? EventId::tryFromHex($eventIdHex) : null;

        return match ($type) {
            Nip19EntityType::Pubkey => null === $publicKey ? null : Npub::fromPublicKey($publicKey),
            Nip19EntityType::Note => null === $eventId ? null : Note::fromEventId($eventId),
            Nip19EntityType::Profile => null === $publicKey ? null : Nprofile::tryFromPublicKey($publicKey, $relays),
            Nip19EntityType::Event => Nevent::tryFromEventId($eventId ?? self::placeholderEventId(), $relays, $publicKey),
            Nip19EntityType::Address => null,
        };
    }

    private static function placeholderEventId(): EventId
    {
        return EventId::tryFromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid placeholder event id');
    }

    public function testExtractNostrUriReferences(): void
    {
        $content = EventContent::fromString('Check out nostr:npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz and nostr:note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturnCallback(static function (string $bech32): ?Nip19EntityInterface {
                if ('npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz' === $bech32) {
                    return self::decoded(Nip19EntityType::Pubkey, pubkeyHex: 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210');
                }
                if ('note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc' === $bech32) {
                    return self::decoded(Nip19EntityType::Note, eventIdHex: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
                }

                return null;
            });

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(2, $references);

        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertEquals('nostr:npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz', $references[0]->getRawText());
        $this->assertEquals('npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz', $references[0]->getIdentifier());
        $this->assertEquals(10, $references[0]->getPosition());

        $this->assertSame(ContentReferenceType::NostrUri, $references[1]->getType());
        $this->assertEquals('nostr:note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc', $references[1]->getRawText());
        $this->assertEquals('note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc', $references[1]->getIdentifier());
    }

    public function testStripsTheNostrSchemeCaseInsensitively(): void
    {
        $content = EventContent::fromString('Hi NOSTR:npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz');

        $codec = $this->createStub(Nip19CodecInterface::class);
        $codec
            ->method('decodeComplexEntity')
            ->willReturnCallback(static fn (string $bech32): ?Nip19EntityInterface => 'npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz' === $bech32
                ? self::decoded(Nip19EntityType::Pubkey, pubkeyHex: 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210')
                : null);

        $references = new ContentReferenceExtractor($codec)->extractContentReferences($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertSame('NOSTR:npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz', $references[0]->getRawText());
        $this->assertSame('npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz', $references[0]->getIdentifier());
        $this->assertSame('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', $references[0]->getPublicKey()?->toHex());
    }

    public function testExtractBareReferences(): void
    {
        $content = EventContent::fromString('Here is npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz and note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc and nevent1qqstna2yrezu5wghjvswqqculvvwxsrcvu7uc0f78gan4xqhvz49d9spr3mhxue69uhkummnw3ez6un9d3shjtn4de6x2argwghx6egpr4mhxue69uhkummnw3ez6ur4vgh8wetvd3hhyer9wghxuet5nxnepm');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturnCallback(static function (string $bech32): ?Nip19EntityInterface {
                if ('npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz' === $bech32) {
                    return self::decoded(Nip19EntityType::Pubkey, pubkeyHex: 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210');
                }
                if ('note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc' === $bech32) {
                    return self::decoded(Nip19EntityType::Note, eventIdHex: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
                }
                if ('nevent1qqstna2yrezu5wghjvswqqculvvwxsrcvu7uc0f78gan4xqhvz49d9spr3mhxue69uhkummnw3ez6un9d3shjtn4de6x2argwghx6egpr4mhxue69uhkummnw3ez6ur4vgh8wetvd3hhyer9wghxuet5nxnepm' === $bech32) {
                    return self::decoded(Nip19EntityType::Event, eventIdHex: 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890', relayUrls: ['wss://relay.com']);
                }

                return null;
            });

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(3, $references);

        $this->assertSame(ContentReferenceType::BareNpub, $references[0]->getType());
        $this->assertEquals('npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz', $references[0]->getRawText());

        $this->assertSame(ContentReferenceType::BareNote, $references[1]->getType());
        $this->assertEquals('note10123456789abcdef0123456789abcdef0123456789abcdef0123456abc', $references[1]->getRawText());

        $this->assertSame(ContentReferenceType::BareNevent, $references[2]->getType());
        $this->assertEquals('nevent1qqstna2yrezu5wghjvswqqculvvwxsrcvu7uc0f78gan4xqhvz49d9spr3mhxue69uhkummnw3ez6un9d3shjtn4de6x2argwghx6egpr4mhxue69uhkummnw3ez6ur4vgh8wetvd3hhyer9wghxuet5nxnepm', $references[2]->getRawText());
    }

    public function testExtractLegacyReferences(): void
    {
        $content = EventContent::fromString('Check out #[0] and #[1] references');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded(Nip19EntityType::Event));

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(2, $references);

        $this->assertSame(ContentReferenceType::LegacyRef, $references[0]->getType());
        $this->assertEquals('#[0]', $references[0]->getRawText());
        $this->assertEquals('#[0]', $references[0]->getIdentifier());

        $this->assertSame(ContentReferenceType::LegacyRef, $references[1]->getType());
        $this->assertEquals('#[1]', $references[1]->getRawText());
        $this->assertEquals('#[1]', $references[1]->getIdentifier());
    }

    public function testReturnsUnknownReferenceForUndecodableBech32(): void
    {
        $content = EventContent::fromString('Invalid reference: npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(null);

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertNull($references[0]->getDecodedType());
    }

    public function testCreatesValueObjectsFromDecodedData(): void
    {
        $content = EventContent::fromString('Reference: nevent1test123');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded(
                Nip19EntityType::Event,
                pubkeyHex: 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210',
                eventIdHex: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
                relayUrls: ['wss://relay1.com', 'wss://relay2.com'],
            ));

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(1, $references);
        $reference = $references[0];

        $this->assertSame(Nip19EntityType::Event, $reference->getDecodedType());
        $this->assertNotNull($reference->getEventId());
        $this->assertEquals('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $reference->getEventId()->toHex());
        $this->assertNotNull($reference->getPublicKey());
        $this->assertEquals('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', $reference->getPublicKey()->toHex());
        $relays = $reference->getRelays()->toArray();
        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay1.com', (string) $relays[0]);
        $this->assertEquals('wss://relay2.com', (string) $relays[1]);
    }

    public function testExtractsAuthorKeyAsPublicKeyForNeventReferences(): void
    {
        $content = EventContent::fromString('Reference: nevent1test456');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded(
                Nip19EntityType::Event,
                pubkeyHex: 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210',
                eventIdHex: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
                relayUrls: ['wss://relay1.com'],
            ));

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(1, $references);
        $reference = $references[0];

        $this->assertNotNull($reference->getPublicKey());
        $this->assertEquals('fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210', $reference->getPublicKey()->toHex());
    }

    public function testSkipsInvalidRelayUrls(): void
    {
        $content = EventContent::fromString('Reference: nevent1test123');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded(
                Nip19EntityType::Event,
                relayUrls: ['wss://valid-relay.com', 'invalid-url', 'wss://another-valid.com'],
            ));

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(1, $references);
        $relayUrls = $references[0]->getRelays()->toArray();

        $this->assertCount(2, $relayUrls);
        $this->assertEquals('wss://valid-relay.com', (string) $relayUrls[0]);
        $this->assertEquals('wss://another-valid.com', (string) $relayUrls[1]);
    }

    public function testIgnoresBoundaryViolations(): void
    {
        $content = EventContent::fromString('Invalid: xnpub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyzx and valid npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz');

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturn(self::decoded(Nip19EntityType::Pubkey, pubkeyHex: 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210'));

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertEquals('npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz', $references[0]->getIdentifier());
    }

    public function testReturnsEmptyArrayForNoMatches(): void
    {
        $content = EventContent::fromString('No references here, just plain text');

        $extractor = new ContentReferenceExtractor(
            $this->createStub(Nip19CodecInterface::class)
        );

        $this->assertEmpty($extractor->extractContentReferences($content)->toArray());
    }

    public function testReturnsContentReferenceCollection(): void
    {
        $references = new ContentReferenceExtractor($this->createStub(Nip19CodecInterface::class))
            ->extractContentReferences(EventContent::fromString('plain text'));

        $this->assertCount(0, $references);
    }

    public function testExtractsConcatenatedReferencesWithoutSeparator(): void
    {
        $bareNevent = 'nevent1qqs97h9ednrvfx04gp8y0x2nfkw28xuan3r3lewul3v2geqt5s2y79szypuvuma2wgny8pegfej8hf5n3x2hxhkgcl2utfjhxlj4zv8sycc86qcyqqqqqqgehu35d';
        $prefixedNevent = 'nevent1qvzqqqqqqypzq7xwd748yfjrsu5yuerm56fcn9tntmyv04w95etn0e23xrczvvraqqs97h9ednrvfx04gp8y0x2nfkw28xuan3r3lewul3v2geqt5s2y79s54dn5f';

        $content = EventContent::fromString("Some text\n\n{$bareNevent}nostr:{$prefixedNevent} ");

        $bech32Encoder = $this->createStub(Nip19CodecInterface::class);
        $bech32Encoder
            ->method('decodeComplexEntity')
            ->willReturnCallback(static function (string $bech32) use ($bareNevent, $prefixedNevent): ?Nip19EntityInterface {
                if ($bech32 === $bareNevent || $bech32 === $prefixedNevent) {
                    return self::decoded(Nip19EntityType::Event, eventIdHex: '5f5cb96cc6c499f5404e4799534d9ca39b9d9c471fe5dcfc58a4640ba4144f16');
                }

                return null;
            });

        $references = new ContentReferenceExtractor($bech32Encoder)->extractContentReferences($content)->toArray();

        $this->assertCount(2, $references);

        $this->assertSame(ContentReferenceType::BareNevent, $references[0]->getType());
        $this->assertEquals($bareNevent, $references[0]->getRawText());
        $this->assertEquals($bareNevent, $references[0]->getIdentifier());

        $this->assertSame(ContentReferenceType::NostrUri, $references[1]->getType());
        $this->assertEquals('nostr:'.$prefixedNevent, $references[1]->getRawText());
        $this->assertEquals($prefixedNevent, $references[1]->getIdentifier());
    }

    /**
     * Overlap detection must stay linear in the match count. Measured on this suite's inputs at 14000
     * matches: linear 92ms, quadratic 2510ms, and the original per-match scan of every prior range
     * ~20s. Doubling the input takes the linear form from 43ms to 92ms (2.1x) and the quadratic form
     * from 646ms to 2510ms (3.9x), which is what separates them. The 600ms bound sits 6.5x above the
     * linear form and 4x below the quadratic one; recalibrate from those figures, not by nudging it.
     */
    public function testAdversarialContentIsExtractedInLinearTime(): void
    {
        $content = EventContent::fromString(str_repeat('nevent1a ', 14000));
        $extractor = new ContentReferenceExtractor($this->createStub(Nip19CodecInterface::class));

        $startedAt = hrtime(true);
        $references = $extractor->extractContentReferences($content);
        $elapsedMs = (hrtime(true) - $startedAt) / 1e6;

        $this->assertSame(14000, $references->count());
        $this->assertLessThan(600, $elapsedMs, sprintf('Extraction took %.0f ms; overlap detection is no longer linear', $elapsedMs));
    }

    public function testANostrUriSuppressesTheBareMatchNestedInsideIt(): void
    {
        $npub = 'npub1'.str_repeat('a', 58);
        $extractor = new ContentReferenceExtractor($this->createStub(Nip19CodecInterface::class));

        $references = $extractor->extractContentReferences(EventContent::fromString('nostr:'.$npub))->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
    }

    public function testAdjacentNonOverlappingReferencesAreAllKept(): void
    {
        $npub = 'npub1'.str_repeat('a', 58);
        $note = 'note1'.str_repeat('b', 58);
        $extractor = new ContentReferenceExtractor($this->createStub(Nip19CodecInterface::class));

        $references = $extractor->extractContentReferences(EventContent::fromString($npub.' '.$note))->toArray();

        $this->assertCount(2, $references);
        $this->assertSame(0, $references[0]->getPosition());
        $this->assertSame(strlen($npub) + 1, $references[1]->getPosition());
    }
}
