<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\ContentReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReferences;
use Innis\Nostr\Core\Domain\ValueObject\Reference\QuoteAnalysis;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ReplyChain;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventReferencesTest extends TestCase
{
    private const ID_A = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const ID_B = '0000000000000000000000000000000000000000000000000000000000000002';

    public function testEmptyReferencesHaveNoReferencesAndZeroCounts(): void
    {
        $references = EventReferences::fromArray([]);

        $this->assertFalse($references->hasReferences());
        $this->assertSame(0, $references->getReferencedEventCount());
        $this->assertSame(0, $references->getReferencedPubkeyCount());
    }

    public function testHasReferencesWhenATagReferencePresent(): void
    {
        $references = new EventReferences(
            $this->tagReferencesWithOneEvent(),
            new ContentReferenceCollection(),
            ReplyChain::fromArray([]),
            QuoteAnalysis::fromArray([]),
            EventIdCollection::fromHexValues([self::ID_A, self::ID_B]),
            PublicKeyCollection::fromHexValues([self::ID_A]),
        );

        $this->assertTrue($references->hasReferences());
        $this->assertSame(2, $references->getReferencedEventCount());
        $this->assertSame(1, $references->getReferencedPubkeyCount());
    }

    private function tagReferencesWithOneEvent(): TagReferences
    {
        $eventId = EventId::fromHex(self::ID_A) ?? throw new RuntimeException('Invalid test event id');

        return new TagReferences(
            new EventReferenceCollection([new EventReference($eventId)]),
            new PubkeyReferenceCollection(),
            new EventReferenceCollection(),
            new EventCoordinateCollection(),
            new RelayReferenceCollection(),
            [],
        );
    }
}
