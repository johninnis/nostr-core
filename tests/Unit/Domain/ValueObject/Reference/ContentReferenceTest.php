<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\DecodedNip19Entity;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentReferenceTest extends TestCase
{
    private const PUBKEY = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

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
