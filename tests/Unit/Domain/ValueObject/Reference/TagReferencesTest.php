<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\TagReferences;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TagReferencesTest extends TestCase
{
    private const EVENT_ID = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const PUBKEY = '0000000000000000000000000000000000000000000000000000000000000002';

    public function testToArrayFromArrayRoundTripPreservesEveryReferenceKind(): void
    {
        $references = new TagReferences(
            new EventReferenceCollection([new EventReference($this->eventId())]),
            new PubkeyReferenceCollection([new PubkeyReference($this->pubkey(), $this->relay(), 'alice')]),
            new EventReferenceCollection([new EventReference($this->eventId())]),
            new EventCoordinateCollection([$this->coordinate()]),
            new RelayReferenceCollection([new RelayReference($this->relay(), 'read')]),
            ['challenge-token'],
        );

        $restored = TagReferences::fromArray($references->toArray());

        $this->assertSame($references->toArray(), $restored->toArray());
    }

    public function testFromArrayDropsNonStringChallenges(): void
    {
        $references = TagReferences::fromArray(['challenges' => ['valid', 123, null]]);

        $this->assertSame(['valid'], $references->getChallenges());
    }

    public function testEmptyHasNoReferencesOfAnyKind(): void
    {
        $references = TagReferences::empty();

        $this->assertSame([
            'events' => [],
            'pubkeys' => [],
            'quotes' => [],
            'addressable' => [],
            'relays' => [],
            'challenges' => [],
        ], $references->toArray());
    }

    private function eventId(): EventId
    {
        return EventId::fromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::fromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey');
    }

    private function relay(): RelayUrl
    {
        return RelayUrl::fromString('wss://relay.example') ?? throw new RuntimeException('Invalid test relay');
    }

    private function coordinate(): EventCoordinate
    {
        return EventCoordinate::create(
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            $this->pubkey(),
            'my-article',
        ) ?? throw new RuntimeException('Invalid test coordinate');
    }
}
