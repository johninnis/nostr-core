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
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceExtractorProseTest extends TestCase
{
    private const string EVENT_ID = '5c83da77af1dec6d7289834998ad7aafbd9e2191396d75ec3cc27f5a77226f36';
    private const string IDENTIFIER = 'a-long-form-article';

    public function testReadsANeventWithRelaysAuthorAndKindAtTheEndOfAParagraphWithATrailingSpace(): void
    {
        $nevent = Nevent::tryFromEventId(
            EventId::tryFromHex(self::EVENT_ID) ?? throw new RuntimeException('expected a valid event id'),
            RelayUrlCollection::fromStrings(['wss://relay.example.com']),
            KeyMother::alicePublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
        ) ?? throw new RuntimeException('expected a valid nevent');
        $content = EventContent::fromString("A first paragraph of prose.\n\nnostr:".$nevent->toBech32().' ');

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $this->assertSame(Nip19EntityType::Event, $references[0]->getDecodedType());
        $this->assertSame(self::EVENT_ID, $references[0]->getEventId()?->toHex());
    }

    public function testReadsAnNaddrWithRelaysAtTheEndOfTheContent(): void
    {
        $coordinate = EventCoordinate::tryFrom(EventKind::fromInt(EventKind::LONGFORM_CONTENT), KeyMother::alicePublicKey(), self::IDENTIFIER)
            ?? throw new RuntimeException('expected a valid coordinate');
        $naddr = Naddr::tryFromCoordinate($coordinate, RelayUrlCollection::fromStrings(['wss://relay.example.com', 'wss://relay.example.org']))
            ?? throw new RuntimeException('expected a valid naddr');
        $content = EventContent::fromString("A first paragraph of prose.\n\nnostr:".$naddr->toBech32());

        $references = ContentReferenceExtractor::extract($content)->toArray();

        $this->assertCount(1, $references);
        $this->assertSame(ContentReferenceType::NostrUri, $references[0]->getType());
        $decoded = $references[0]->getDecoded();
        $this->assertInstanceOf(Naddr::class, $decoded);
        $this->assertSame((string) $coordinate, (string) $decoded->getCoordinate());
    }

    public function testReadsNoReferenceFromProseThatNamesNone(): void
    {
        $content = EventContent::fromString("A first paragraph of prose.\n\nA second paragraph, with punctuation: commas, colons and a URL-free sentence.");

        $this->assertEmpty(ContentReferenceExtractor::extract($content)->toArray());
    }
}
