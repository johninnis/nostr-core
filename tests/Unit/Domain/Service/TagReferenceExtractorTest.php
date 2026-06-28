<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\TagReferenceExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TagReferenceExtractorTest extends TestCase
{
    private const string EVENT_ID = '1111111111111111111111111111111111111111111111111111111111111111';
    private const string EVENT_AUTHOR = '2222222222222222222222222222222222222222222222222222222222222222';
    private const string PUBKEY = '3333333333333333333333333333333333333333333333333333333333333333';
    private const string QUOTE_ID = '4444444444444444444444444444444444444444444444444444444444444444';
    private const string QUOTE_AUTHOR = '5555555555555555555555555555555555555555555555555555555555555555';
    private const string COORD_PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string RELAY = 'wss://relay.example.com';

    public function testEventTagBecomesEventReferenceWithRelayMarkerAndAuthor(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('e', self::EVENT_ID, self::RELAY, 'reply', self::EVENT_AUTHOR),
        ]));

        $this->assertCount(1, $references->getEvents());

        $event = $references->getEvents()->toArray()[0];
        $this->assertSame(self::EVENT_ID, $event->getEventId()->toHex());
        $this->assertSame(self::RELAY, (string) $event->getRelayUrl());
        $this->assertSame('reply', $event->getMarker());
        $this->assertSame(self::EVENT_AUTHOR, $event->getAuthor()?->toHex());
    }

    public function testPubkeyTagBecomesPubkeyReferenceWithRelayAndPetname(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('p', self::PUBKEY, self::RELAY, 'alice'),
        ]));

        $this->assertCount(1, $references->getPubkeys());

        $pubkey = $references->getPubkeys()->toArray()[0];
        $this->assertSame(self::PUBKEY, $pubkey->getPubkey()->toHex());
        $this->assertSame(self::RELAY, (string) $pubkey->getRelayUrl());
        $this->assertSame('alice', $pubkey->getPetname());
    }

    public function testQuoteTagWithEventIdBecomesQuotedEventWithAuthorFromThirdElement(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('q', self::QUOTE_ID, self::RELAY, self::QUOTE_AUTHOR),
        ]));

        $this->assertCount(1, $references->getQuotes());
        $this->assertCount(0, $references->getAddressable());

        $quote = $references->getQuotes()->toArray()[0];
        $this->assertSame(self::QUOTE_ID, $quote->getEventId()->toHex());
        $this->assertSame(self::RELAY, (string) $quote->getRelayUrl());
        $this->assertNull($quote->getMarker());
        $this->assertSame(self::QUOTE_AUTHOR, $quote->getAuthor()?->toHex());
    }

    public function testQuoteTagCarryingCoordinateBecomesAddressableNotQuote(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('q', '30023:'.self::COORD_PUBKEY.':my-article', self::RELAY),
        ]));

        $this->assertCount(0, $references->getQuotes());
        $this->assertCount(1, $references->getAddressable());

        $coordinate = $references->getAddressable()->toArray()[0];
        $this->assertSame(30023, $coordinate->getKind()->toInt());
        $this->assertSame(self::COORD_PUBKEY, $coordinate->getPubkey()->toHex());
        $this->assertSame('my-article', $coordinate->getIdentifier());
        $this->assertSame(self::RELAY, (string) $coordinate->getRelayHint());
    }

    public function testAddressableTagBecomesCoordinate(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('a', '30023:'.self::COORD_PUBKEY.':my-article', self::RELAY),
        ]));

        $this->assertCount(1, $references->getAddressable());

        $coordinate = $references->getAddressable()->toArray()[0];
        $this->assertSame('my-article', $coordinate->getIdentifier());
        $this->assertSame(self::RELAY, (string) $coordinate->getRelayHint());
    }

    public function testReferenceTagBecomesRelayReferenceWithMode(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('r', self::RELAY, 'read'),
        ]));

        $this->assertCount(1, $references->getRelays());

        $relay = $references->getRelays()->toArray()[0];
        $this->assertSame(self::RELAY, (string) $relay->getRelayUrl());
        $this->assertSame('read', $relay->getMode());
    }

    public function testChallengeTagBecomesChallengeString(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('challenge', 'server-challenge-token'),
        ]));

        $this->assertSame(['server-challenge-token'], $references->getChallenges());
    }

    public function testCoordinatesPreserveDocumentOrderAcrossAddressableAndQuoteTags(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('a', '30023:'.self::COORD_PUBKEY.':first'),
            $this->tag('q', '30023:'.self::COORD_PUBKEY.':second'),
            $this->tag('a', '30023:'.self::COORD_PUBKEY.':third'),
        ]));

        $identifiers = array_map(
            static fn (EventCoordinate $coordinate): string => $coordinate->getIdentifier(),
            $references->getAddressable()->toArray(),
        );

        $this->assertSame(['first', 'second', 'third'], $identifiers);
    }

    public function testEveryCategoryIsExtractedFromOneMixedCollection(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('e', self::EVENT_ID),
            $this->tag('p', self::PUBKEY),
            $this->tag('q', self::QUOTE_ID),
            $this->tag('a', '30023:'.self::COORD_PUBKEY.':my-article'),
            $this->tag('r', self::RELAY),
            $this->tag('challenge', 'token'),
        ]));

        $this->assertCount(1, $references->getEvents());
        $this->assertCount(1, $references->getPubkeys());
        $this->assertCount(1, $references->getQuotes());
        $this->assertCount(1, $references->getAddressable());
        $this->assertCount(1, $references->getRelays());
        $this->assertSame(['token'], $references->getChallenges());
    }

    public function testMalformedValuesAreSkipped(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('e', 'not-a-valid-event-id'),
            $this->tag('p', 'not-a-valid-pubkey'),
            $this->tag('q', 'not-hex-and-no-colon'),
            $this->tag('r', 'not a relay url'),
        ]));

        $this->assertReferencesAreEmpty($references);
    }

    public function testUnknownTagTypesAreIgnored(): void
    {
        $references = TagReferenceExtractor::extract(new TagCollection([
            $this->tag('nonce', '12345'),
            $this->tag('subject', 'hello'),
        ]));

        $this->assertReferencesAreEmpty($references);
    }

    public function testEmptyTagCollectionYieldsEmptyReferences(): void
    {
        $this->assertReferencesAreEmpty(TagReferenceExtractor::extract(new TagCollection()));
    }

    private function tag(string ...$parts): Tag
    {
        return Tag::fromArray($parts) ?? throw new RuntimeException('Invalid tag fixture');
    }

    private function assertReferencesAreEmpty(TagReferences $references): void
    {
        $this->assertCount(0, $references->getEvents());
        $this->assertCount(0, $references->getPubkeys());
        $this->assertCount(0, $references->getQuotes());
        $this->assertCount(0, $references->getAddressable());
        $this->assertCount(0, $references->getRelays());
        $this->assertSame([], $references->getChallenges());
    }
}
