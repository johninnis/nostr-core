<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceTest extends TestCase
{
    private const PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const EVENT_ID = '1111111111111111111111111111111111111111111111111111111111111111';

    public function testAddressableReferenceCarriesPubkeyKindAndIdentifier(): void
    {
        $reference = new ContentReference(
            ContentReferenceType::BareNaddr,
            'naddr1example',
            'my-article',
            0,
            $this->decodedAddress('my-article'),
        );

        $this->assertTrue($reference->isPubkeyReference());
        $this->assertTrue($reference->isAddressableReference());
    }

    public function testIsNotAddressableWhenIdentifierMissing(): void
    {
        $reference = new ContentReference(
            ContentReferenceType::BareNprofile,
            'nprofile1example',
            'profile',
            0,
            $this->decodedAddress(null),
        );

        $this->assertTrue($reference->isPubkeyReference());
        $this->assertFalse($reference->isAddressableReference());
    }

    public function testBareReferenceWithoutDecodedEntityIsNeitherPubkeyNorAddressable(): void
    {
        $reference = new ContentReference(ContentReferenceType::LegacyRef, 'raw', 'identifier', 0);

        $this->assertFalse($reference->isPubkeyReference());
        $this->assertFalse($reference->isAddressableReference());
    }

    public function testToArrayFromArrayRoundTripWithDecodedEntity(): void
    {
        $reference = new ContentReference(
            ContentReferenceType::BareNaddr,
            'naddr1example',
            'my-article',
            5,
            $this->decodedAddress('my-article'),
        );

        $restored = ContentReference::fromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testToArrayFromArrayRoundTripWithoutDecodedEntity(): void
    {
        $reference = new ContentReference(ContentReferenceType::LegacyRef, '#[0]', 'identifier', 0);

        $restored = ContentReference::fromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testFromArrayReturnsNullWhenTypeIsUnknown(): void
    {
        $this->assertNull(ContentReference::fromArray([
            'type' => 'not-a-real-type',
            'raw_text' => 'raw',
            'identifier' => 'id',
            'position' => 0,
        ]));
    }

    public function testFromArrayReturnsNullWhenPositionIsNegative(): void
    {
        $this->assertNull(ContentReference::fromArray([
            'type' => ContentReferenceType::LegacyRef->value,
            'raw_text' => 'raw',
            'identifier' => 'id',
            'position' => -1,
        ]));
    }

    public function testIsEventReferenceWhenDecodedEntityCarriesAnEventId(): void
    {
        $eventId = EventId::fromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');

        $reference = new ContentReference(
            ContentReferenceType::BareNevent,
            'nevent1example',
            'evt',
            0,
            new DecodedNip19Entity(type: Nip19EntityType::Event, eventId: $eventId),
        );

        $this->assertTrue($reference->isEventReference());
    }

    public function testConstructorRejectsNegativePosition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentReference(ContentReferenceType::LegacyRef, 'raw', 'id', -1);
    }

    private function decodedAddress(?string $identifier): DecodedNip19Entity
    {
        return new DecodedNip19Entity(
            type: Nip19EntityType::Address,
            publicKey: PublicKey::fromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            eventId: null,
            identifier: $identifier,
            kind: EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            relays: new RelayUrlCollection(),
        );
    }
}
