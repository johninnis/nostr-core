<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Tests\Support\Bech32Mother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceExtractorTest extends TestCase
{
    private const string PUBKEY_HEX = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';
    private const string EVENT_ID_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private static function npub(): string
    {
        return (PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey'))->toBech32();
    }

    private static function note(): string
    {
        return self::eventId()->toBech32();
    }

    private static function eventId(): EventId
    {
        return EventId::tryFromHex(self::EVENT_ID_HEX) ?? throw new RuntimeException('Invalid test event id');
    }

    /**
     * @param list<string> $relays
     */
    private static function nevent(array $relays, ?string $authorHex = null): string
    {
        $author = null === $authorHex ? null : PublicKey::tryFromHex($authorHex);
        $relayUrls = RelayUrlCollection::fromStrings($relays);

        return (Nevent::tryFromEventId(self::eventId(), $relayUrls, $author) ?? throw new RuntimeException('Invalid test nevent'))->toBech32();
    }

    public function testExtractNostrUriReferences(): void
    {
        $npub = self::npub();
        $note = self::note();
        $content = EventContent::fromString("Check out nostr:{$npub} and nostr:{$note}");

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(2, $references);

        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertEquals('nostr:'.$npub, $references[0]->getRawText());
        $this->assertEquals($npub, $references[0]->getIdentifier());
        $this->assertEquals(10, $references[0]->getPosition());

        $this->assertSame(ContentReferenceType::NostrUri, $references[1]->getType());
        $this->assertEquals('nostr:'.$note, $references[1]->getRawText());
        $this->assertEquals($note, $references[1]->getIdentifier());
    }

    public function testStripsTheNostrSchemeCaseInsensitively(): void
    {
        $npub = self::npub();
        $content = EventContent::fromString('Hi NOSTR:'.$npub);

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertSame('NOSTR:'.$npub, $references[0]->getRawText());
        $this->assertSame($npub, $references[0]->getIdentifier());
        $this->assertSame(self::PUBKEY_HEX, $references[0]->getPublicKey()?->toHex());
    }

    public function testExtractBareReferences(): void
    {
        $npub = self::npub();
        $note = self::note();
        $nevent = self::nevent(['wss://relay.com']);
        $content = EventContent::fromString("Here is {$npub} and {$note} and {$nevent}");

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(3, $references);

        $this->assertSame(ContentReferenceType::BareNpub, $references[0]->getType());
        $this->assertEquals($npub, $references[0]->getRawText());

        $this->assertSame(ContentReferenceType::BareNote, $references[1]->getType());
        $this->assertEquals($note, $references[1]->getRawText());

        $this->assertSame(ContentReferenceType::BareNevent, $references[2]->getType());
        $this->assertEquals($nevent, $references[2]->getRawText());
    }

    public function testANip08IndexIsNoContentReference(): void
    {
        $this->assertTrue(ContentReferenceExtractor::extract(EventContent::fromString('Check out #[0] and #[1] references'))->isEmpty());
    }

    public function testABareRunThatDoesNotDecodeIsNoReference(): void
    {
        $content = EventContent::fromString('Invalid reference: npub10123456789abcdef0123456789abcdef0123456789abcdef0123456xyz');

        $this->assertTrue(ContentReferenceExtractor::extract($content)->isEmpty());
    }

    public function testANostrUriWhoseRunDoesNotDecodeIsNoReference(): void
    {
        $content = EventContent::fromString('Invalid reference: nostr:nevent1qqqqqqqqqqqqqqqq');

        $this->assertTrue(ContentReferenceExtractor::extract($content)->isEmpty());
    }

    public function testARunThatDoesNotDecodeLeavesTheEntitiesAroundItReferenced(): void
    {
        $content = EventContent::fromString('nevent1qqqqqqqqqqqqqqqq then '.self::npub());

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertSame([self::npub()], array_map(static fn ($reference): string => $reference->getIdentifier(), $references));
    }

    public function testCreatesValueObjectsFromDecodedData(): void
    {
        $content = EventContent::fromString('Reference: '.self::nevent(['wss://relay1.com', 'wss://relay2.com'], self::PUBKEY_HEX));

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $reference = $references[0];

        $this->assertSame(Nip19EntityType::Event, $reference->getDecodedType());
        $this->assertNotNull($reference->getEventId());
        $this->assertEquals(self::EVENT_ID_HEX, $reference->getEventId()->toHex());
        $this->assertNotNull($reference->getPublicKey());
        $this->assertEquals(self::PUBKEY_HEX, $reference->getPublicKey()->toHex());
        $relays = $reference->getRelays()->toArray();
        $this->assertCount(2, $relays);
        $this->assertEquals('wss://relay1.com', (string) $relays[0]);
        $this->assertEquals('wss://relay2.com', (string) $relays[1]);
    }

    public function testExtractsAuthorKeyAsPublicKeyForNeventReferences(): void
    {
        $content = EventContent::fromString('Reference: '.self::nevent(['wss://relay1.com'], self::PUBKEY_HEX));

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $reference = $references[0];

        $this->assertNotNull($reference->getPublicKey());
        $this->assertEquals(self::PUBKEY_HEX, $reference->getPublicKey()->toHex());
    }

    public function testSkipsInvalidRelayUrls(): void
    {
        $records = chr(0).chr(32).self::eventId()->toBytes();

        foreach (['wss://valid-relay.com', 'invalid-url', 'wss://another-valid.com'] as $relay) {
            $records .= chr(1).chr(strlen($relay)).$relay;
        }

        $content = EventContent::fromString('Reference: '.Bech32Mother::encode(Nevent::HRP, $records));

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $relayUrls = $references[0]->getRelays()->toArray();

        $this->assertCount(2, $relayUrls);
        $this->assertEquals('wss://valid-relay.com', (string) $relayUrls[0]);
        $this->assertEquals('wss://another-valid.com', (string) $relayUrls[1]);
    }

    public function testIgnoresBoundaryViolations(): void
    {
        $npub = self::npub();
        $content = EventContent::fromString("Invalid: x{$npub}x and valid {$npub}");

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertEquals($npub, $references[0]->getIdentifier());
    }

    #[DataProvider('nostrUrisRunningOnIntoMoreCharacters')]
    public function testANostrUriFollowedByMoreBech32CharactersIsNotAReference(string $content): void
    {
        $this->assertCount(0, ContentReferenceExtractor::extract(EventContent::fromString($content)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nostrUrisRunningOnIntoMoreCharacters(): iterable
    {
        yield 'an npub' => ['see nostr:'.self::npub().'xyz here'];
        yield 'a note' => ['see nostr:'.self::note().'xyz here'];
        yield 'an npub followed by a digit' => ['nostr:'.self::npub().'7'];
        yield 'an uppercase npub' => ['NOSTR:'.strtoupper(self::npub()).'XYZ'];
    }

    public function testANostrUriEndsAtTheFirstCharacterThatCannotContinueIt(): void
    {
        $references = ContentReferenceExtractor::extract(EventContent::fromString('nostr:'.self::npub().'.'))->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(self::npub(), $references[0]->getIdentifier());
    }

    public function testReturnsEmptyArrayForNoMatches(): void
    {
        $content = EventContent::fromString('No references here, just plain text');

        $this->assertEmpty(ContentReferenceExtractor::extract($content)->toArray());
    }

    public function testReturnsContentReferenceCollection(): void
    {
        $references = ContentReferenceExtractor::extract(EventContent::fromString('plain text'));

        $this->assertCount(0, $references);
    }

    public function testExtractsConcatenatedReferencesWithoutSeparator(): void
    {
        $bareNevent = 'nevent1qqs97h9ednrvfx04gp8y0x2nfkw28xuan3r3lewul3v2geqt5s2y79szypuvuma2wgny8pegfej8hf5n3x2hxhkgcl2utfjhxlj4zv8sycc86qcyqqqqqqgehu35d';
        $prefixedNevent = 'nevent1qvzqqqqqqypzq7xwd748yfjrsu5yuerm56fcn9tntmyv04w95etn0e23xrczvvraqqs97h9ednrvfx04gp8y0x2nfkw28xuan3r3lewul3v2geqt5s2y79s54dn5f';

        $content = EventContent::fromString("Some text\n\n{$bareNevent}nostr:{$prefixedNevent} ");

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(2, $references);

        $this->assertSame(ContentReferenceType::BareNevent, $references[0]->getType());
        $this->assertEquals($bareNevent, $references[0]->getRawText());
        $this->assertEquals($bareNevent, $references[0]->getIdentifier());
        $this->assertSame('5f5cb96cc6c499f5404e4799534d9ca39b9d9c471fe5dcfc58a4640ba4144f16', $references[0]->getEventId()?->toHex());

        $this->assertSame(ContentReferenceType::NostrUri, $references[1]->getType());
        $this->assertEquals('nostr:'.$prefixedNevent, $references[1]->getRawText());
        $this->assertEquals($prefixedNevent, $references[1]->getIdentifier());
    }

    public function testANostrNeventFollowedByANostrUriWithoutSeparatorYieldsBoth(): void
    {
        $nevent = self::nevent([]);
        $npub = self::npub();

        $references = ContentReferenceExtractor::extract(EventContent::fromString("nostr:{$nevent}nostr:{$npub}"))->toArray();

        $this->assertSame(['nostr:'.$nevent, 'nostr:'.$npub], array_map(static fn ($reference): string => $reference->getRawText(), $references));
    }

    public function testANostrNpubFollowedByANostrUriWithoutSeparatorYieldsBoth(): void
    {
        $npub = self::npub();
        $note = self::note();

        $references = ContentReferenceExtractor::extract(EventContent::fromString("nostr:{$npub}nostr:{$note}"))->toArray();

        $this->assertSame(['nostr:'.$npub, 'nostr:'.$note], array_map(static fn ($reference): string => $reference->getRawText(), $references));
    }

    public function testANostrNprofileFollowedByANostrUriWithoutSeparatorYieldsBoth(): void
    {
        $nprofile = (Nprofile::tryFromPublicKey(PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey')) ?? throw new RuntimeException('Invalid test nprofile'))->toBech32();
        $note = self::note();

        $references = ContentReferenceExtractor::extract(EventContent::fromString("nostr:{$nprofile}nostr:{$note}"))->toArray();

        $this->assertSame(['nostr:'.$nprofile, 'nostr:'.$note], array_map(static fn ($reference): string => $reference->getRawText(), $references));
    }

    public function testANostrNaddrFollowedByANostrUriWithoutSeparatorYieldsBoth(): void
    {
        $coordinate = EventCoordinate::tryFrom(
            EventKind::fromInt(30023),
            PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey'),
            'article',
        ) ?? throw new RuntimeException('Invalid test coordinate');
        $naddr = (Naddr::tryFromCoordinate($coordinate) ?? throw new RuntimeException('Invalid test naddr'))->toBech32();
        $npub = self::npub();

        $references = ContentReferenceExtractor::extract(EventContent::fromString("nostr:{$naddr}nostr:{$npub}"))->toArray();

        $this->assertSame(['nostr:'.$naddr, 'nostr:'.$npub], array_map(static fn ($reference): string => $reference->getRawText(), $references));
    }

    public function testAdversarialContentOfAdjacentReferencesKeepsEveryOne(): void
    {
        $content = EventContent::fromString(str_repeat(self::nevent([]).' ', 16000));

        $this->assertSame(16000, ContentReferenceExtractor::extract($content)->count());
    }

    public function testANostrUriSuppressesTheBareMatchNestedInsideIt(): void
    {
        $npub = self::npub();

        $references = ContentReferenceExtractor::extract(EventContent::fromString('nostr:'.$npub))->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
    }

    public function testAdjacentNonOverlappingReferencesAreAllKept(): void
    {
        $npub = self::npub();
        $note = self::note();

        $references = ContentReferenceExtractor::extract(EventContent::fromString($npub.' '.$note))->toArray();

        $this->assertCount(2, $references);
        $this->assertSame(0, $references[0]->getPosition());
        $this->assertSame(strlen($npub) + 1, $references[1]->getPosition());
    }
}
