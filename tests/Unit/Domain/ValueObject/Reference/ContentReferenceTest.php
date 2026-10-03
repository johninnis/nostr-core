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
        $reference = ContentReference::from(
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
        $reference = ContentReference::from(
            ContentReferenceType::BareNprofile,
            'nprofile1example',
            'profile',
            0,
            $this->decodedProfile(),
        );

        $this->assertTrue($reference->isPubkeyReference());
        $this->assertFalse($reference->isAddressableReference());
    }

    public function testToArrayFromArrayRoundTripWithDecodedEntity(): void
    {
        $reference = ContentReference::from(
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

    public function testTryFromArrayReturnsNullWhenTheDecodedEntityIsAbsent(): void
    {
        $this->assertNull(ContentReference::tryFromArray([
            'type' => ContentReferenceType::BareNpub->value,
            'raw_text' => 'npub1example',
            'identifier' => 'npub1example',
            'position' => 0,
        ]));
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
            ...ContentReference::from(ContentReferenceType::BareNprofile, 'nprofile1example', 'profile', 0, $this->decodedProfile())->toArray(),
            'position' => -1,
        ]));
    }

    public function testIsEventReferenceWhenDecodedEntityCarriesAnEventId(): void
    {
        $eventId = EventId::tryFromHex(self::EVENT_ID) ?? throw new RuntimeException('Invalid test event id');

        $reference = ContentReference::from(
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

        ContentReference::from(ContentReferenceType::BareNprofile, 'nprofile1example', 'profile', -1, $this->decodedProfile());
    }

    // Deliberate: an Address carrying no coordinate is unrepresentable now that the leaves are distinct types, so the pubkey-but-not-addressable case is an nprofile — see ADR-0082
    private function decodedProfile(): Nprofile
    {
        return Nprofile::tryFromPublicKey(
            PublicKey::tryFromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
        ) ?? throw new RuntimeException('Invalid test nprofile');
    }

    private function decodedAddress(?string $identifier): Naddr
    {
        $coordinate = EventCoordinate::tryFrom(
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            PublicKey::tryFromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            $identifier ?? '',
        );

        return (null === $coordinate ? null : Naddr::tryFromCoordinate($coordinate)) ?? throw new RuntimeException('Invalid test naddr');
    }

    public function testTryFromRefusesANegativePosition(): void
    {
        $this->assertNull(ContentReference::tryFrom(ContentReferenceType::NostrUri, 'nostr:x', 'x', -1, $this->decodedProfile()));
    }
}
