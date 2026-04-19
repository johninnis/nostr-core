<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Adapter;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\GiftWrapException;
use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Adapter\GiftWrapAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\Nip44EncryptionAdapter;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GiftWrapAdapterTest extends TestCase
{
    use WithCryptoServices;

    private GiftWrapAdapter $adapter;
    private KeyPair $senderKeyPair;
    private KeyPair $recipientKeyPair;

    protected function setUp(): void
    {
        $this->adapter = new GiftWrapAdapter(new Nip44EncryptionAdapter(), $this->signatureService(), $this->ecdhService());
        $this->senderKeyPair = KeyPair::generate($this->signatureService());
        $this->recipientKeyPair = KeyPair::generate($this->signatureService());
    }

    public function testCanWrapAndUnwrapRumour(): void
    {
        $rumour = $this->createRumour('Hello NIP-17!');

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );

        $unwrapped = $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());

        $this->assertSame('Hello NIP-17!', (string) $unwrapped->getContent());
        $this->assertTrue($unwrapped->getPubkey()->equals($this->senderKeyPair->getPublicKey()));
    }

    public function testWrapProducesKind1059Event(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $this->assertTrue($giftWrap->getKind()->equals(EventKind::giftWrap()));
    }

    public function testWrapProducesSignedGiftWrap(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $this->assertTrue($giftWrap->isSigned());
        $this->assertTrue($giftWrap->verify($this->signatureService()));
    }

    public function testGiftWrapHasRecipientPTag(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $pTags = $giftWrap->getTags()->findByType(TagType::pubkey());
        $this->assertCount(1, $pTags);
        $this->assertSame($this->recipientKeyPair->getPublicKey()->toHex(), $pTags[0]->getValue());
    }

    public function testGiftWrapPubkeyIsEphemeral(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $this->assertFalse($giftWrap->getPubkey()->equals($this->senderKeyPair->getPublicKey()));
    }

    public function testUnwrapReturnsSenderAsPubkey(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $rumour = $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());

        $this->assertTrue($rumour->getPubkey()->equals($this->senderKeyPair->getPublicKey()));
    }

    public function testUnwrapReturnsUnsignedRumour(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $rumour = $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());

        $this->assertFalse($rumour->isSigned());
    }

    public function testUnwrapReturnsKind14Rumour(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $rumour = $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());

        $this->assertTrue($rumour->getKind()->equals(EventKind::privateMessage()));
    }

    public function testUnwrapPreservesRumourTags(): void
    {
        $recipientPubkey = $this->recipientKeyPair->getPublicKey()->toHex();
        $tags = new TagCollection([
            Tag::pubkey($recipientPubkey),
            Tag::fromArray(['subject', 'Test conversation']),
        ]);

        $rumour = EventFactory::createRumour(
            $this->senderKeyPair->getPublicKey(),
            'Tagged message',
            $tags
        );

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );

        $unwrapped = $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());

        $pTags = $unwrapped->getTags()->findByType(TagType::pubkey());
        $this->assertCount(1, $pTags);
        $this->assertSame($recipientPubkey, $pTags[0]->getValue());

        $subjectTags = $unwrapped->getTags()->findByType(TagType::fromString('subject'));
        $this->assertCount(1, $subjectTags);
        $this->assertSame('Test conversation', $subjectTags[0]->getValue());
    }

    public function testWrapRejectsSignedRumour(): void
    {
        $rumour = $this->createRumour('Test')->sign($this->senderKeyPair, $this->signatureService());

        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('Rumour must not be signed');

        $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );
    }

    public function testWrapRejectsNonKind14Event(): void
    {
        $textNote = new Event(
            $this->senderKeyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Not a rumour')
        );

        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('Rumour must be kind 14');

        $this->adapter->wrapForRecipient(
            $textNote,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );
    }

    public function testWrapRejectsMismatchedSenderKey(): void
    {
        $rumour = $this->createRumour('Test');
        $wrongKeyPair = KeyPair::generate($this->signatureService());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sender private key does not match rumour public key');

        $this->adapter->wrapForRecipient(
            $rumour,
            $wrongKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );
    }

    public function testUnwrapRejectsNonKind1059Event(): void
    {
        $textNote = EventFactory::createTextNote(
            $this->senderKeyPair->getPublicKey(),
            'Not a gift wrap'
        )->sign($this->senderKeyPair, $this->signatureService());

        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('Event must be kind 1059');

        $this->adapter->unwrap($textNote, $this->recipientKeyPair->getPrivateKey());
    }

    public function testUnwrapRejectsUnsignedGiftWrap(): void
    {
        $giftWrap = new Event(
            $this->senderKeyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::giftWrap(),
            TagCollection::empty(),
            EventContent::fromString('fake')
        );

        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('Gift wrap signature is invalid');

        $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());
    }

    public function testUnwrapRejectsTamperedGiftWrap(): void
    {
        $legitimate = $this->wrapRumour('Original');

        $tampered = new Event(
            $legitimate->getPubkey(),
            $legitimate->getCreatedAt(),
            $legitimate->getKind(),
            $legitimate->getTags(),
            EventContent::fromString('tampered ciphertext'),
            $legitimate->getId(),
            $legitimate->getSignature(),
        );

        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('Gift wrap signature is invalid');

        $this->adapter->unwrap($tampered, $this->recipientKeyPair->getPrivateKey());
    }

    public function testDeterministicWrapWithExplicitParameters(): void
    {
        $ephemeralKeyPair = KeyPair::generate($this->signatureService());
        $sealTimestamp = Timestamp::fromInt(1700000000);
        $wrapTimestamp = Timestamp::fromInt(1700000100);

        $rumour = $this->createRumour('Deterministic test');

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey(),
            $ephemeralKeyPair,
            $sealTimestamp,
            $wrapTimestamp
        );

        $this->assertTrue($giftWrap->getPubkey()->equals($ephemeralKeyPair->getPublicKey()));
        $this->assertSame(1700000100, $giftWrap->getCreatedAt()->toInt());

        $unwrapped = $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey());
        $this->assertSame('Deterministic test', (string) $unwrapped->getContent());
    }

    public function testWrapForSenderAllowsSelfDecryption(): void
    {
        $rumour = $this->createRumour('Self-copy');

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->senderKeyPair->getPublicKey()
        );

        $unwrapped = $this->adapter->unwrap($giftWrap, $this->senderKeyPair->getPrivateKey());

        $this->assertSame('Self-copy', (string) $unwrapped->getContent());
    }

    private function createRumour(string $content): Event
    {
        return EventFactory::createRumour(
            $this->senderKeyPair->getPublicKey(),
            $content,
            new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey()->toHex())])
        );
    }

    private function wrapRumour(string $content): Event
    {
        return $this->adapter->wrapForRecipient(
            $this->createRumour($content),
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );
    }
}
