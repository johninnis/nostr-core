<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Note;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceTest extends TestCase
{
    private const string PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const string EVENT_ID = '1111111111111111111111111111111111111111111111111111111111111111';

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
            $this->decodedProfile(),
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

        $restored = ContentReference::tryFromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testToArrayFromArrayRoundTripWithoutDecodedEntity(): void
    {
        $reference = new ContentReference(ContentReferenceType::LegacyRef, '#[0]', 'identifier', 0);

        $restored = ContentReference::tryFromArray($reference->toArray());

        $this->assertNotNull($restored);
        $this->assertSame($reference->toArray(), $restored->toArray());
    }

    public function testTryFromArrayReturnsNullWhenTypeIsUnknown(): void
    {
        $this->assertNull(ContentReference::tryFromArray([
            'type' => 'not-a-real-type',
            'raw_text' => 'raw',
            'identifier' => 'id',
            'position' => 0,
        ]));
    }

    public function testTryFromArrayReturnsNullWhenPositionIsNegative(): void
    {
        $this->assertNull(ContentReference::tryFromArray([
            'type' => ContentReferenceType::LegacyRef->value,
            'raw_text' => 'raw',
            'identifier' => 'id',
            'position' => -1,
        ]));
    }

    public function testIsEventReferenceWhenDecodedEntityCarriesAnEventId(): void
    {
        $eventId = EventId::tryFromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');

        $reference = new ContentReference(
            ContentReferenceType::BareNevent,
            'nevent1example',
            'evt',
            0,
            Note::fromEventId($eventId),
        );

        $this->assertTrue($reference->isEventReference());
    }

    public function testConstructorRejectsNegativePosition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentReference(ContentReferenceType::LegacyRef, 'raw', 'id', -1);
    }

    // Deliberate: an Address carrying no identifier is unrepresentable now that the leaves are distinct types, so the pubkey-but-not-addressable case is an nprofile — see ADR-0060
    private function decodedProfile(): ?Nprofile
    {
        return Nprofile::tryFromPublicKey(
            PublicKey::tryFromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
        );
    }

    private function decodedAddress(?string $identifier): ?Naddr
    {
        $coordinate = EventCoordinate::tryFrom(
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            PublicKey::tryFromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            $identifier ?? '',
        );

        return null === $coordinate ? null : Naddr::tryFromCoordinate($coordinate);
    }
}
