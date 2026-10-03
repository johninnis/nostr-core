<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Exception\GiftWrapException;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Failure\GiftWrapUnwrapFailure;
use Innis\Nostr\Core\Domain\Service\ConversationCipher;
use Innis\Nostr\Core\Domain\Service\ConversationCipherInterface;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Crypto\GiftWrapEnvelope;
use Innis\Nostr\Core\Infrastructure\Crypto\GiftWrapEnvelopeFactoryInterface;
use Innis\Nostr\Core\Infrastructure\Crypto\GiftWrapper;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\RandomGiftWrapEnvelopeFactory;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use Innis\Nostr\Core\Tests\Support\EventMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GiftWrapperTest extends TestCase
{
    private GiftWrapper $adapter;
    private KeyPair $senderKeyPair;
    private KeyPair $recipientKeyPair;

    protected function setUp(): void
    {
        $this->adapter = GiftWrapper::create(new Nip44Cipher(), CryptoFixtures::signer(), CryptoFixtures::ecdh());
        $this->senderKeyPair = KeyPair::generate(CryptoFixtures::signer());
        $this->recipientKeyPair = KeyPair::generate(CryptoFixtures::signer());
    }

    public function testCanWrapAndUnwrapRumour(): void
    {
        $rumour = $this->createRumour('Hello NIP-17!');

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );

        $unwrapped = $this->unwrapped($this->adapter, $giftWrap, $this->recipientKeyPair);

        $this->assertSame('Hello NIP-17!', (string) $unwrapped->getContent());
        $this->assertTrue($unwrapped->getPubkey()->equals($this->senderKeyPair->getPublicKey()));
    }

    public function testWrapProducesKind1059Event(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $this->assertTrue($giftWrap->getKind()->is(EventKind::GIFT_WRAP));
    }

    public function testWrapProducesSignedGiftWrap(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $this->assertTrue($giftWrap->verify(CryptoFixtures::signer()));
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

        $rumour = $this->unwrapped($this->adapter, $giftWrap, $this->recipientKeyPair);

        $this->assertTrue($rumour->getPubkey()->equals($this->senderKeyPair->getPublicKey()));
    }

    public function testUnwrapReturnsKind14Rumour(): void
    {
        $giftWrap = $this->wrapRumour('Test');

        $rumour = $this->unwrapped($this->adapter, $giftWrap, $this->recipientKeyPair);

        $this->assertTrue($rumour->getKind()->is(EventKind::PRIVATE_MESSAGE));
    }

    public function testUnwrapPreservesRumourTags(): void
    {
        $recipientPubkey = $this->recipientKeyPair->getPublicKey()->toHex();
        $tags = new TagCollection([
            Tag::pubkey($this->recipientKeyPair->getPublicKey()),
            Tag::fromArray(['subject', 'Test conversation']),
        ]);

        $rumour = Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::PRIVATE_MESSAGE),
            EventContent::fromString('Tagged message'),
            $tags,
        );

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );

        $unwrapped = $this->unwrapped($this->adapter, $giftWrap, $this->recipientKeyPair);

        $pTags = $unwrapped->getTags()->findByType(TagType::pubkey());
        $this->assertCount(1, $pTags);
        $this->assertSame($recipientPubkey, $pTags[0]->getValue());

        $subjectTags = $unwrapped->getTags()->findByType(TagType::fromString('subject'));
        $this->assertCount(1, $subjectTags);
        $this->assertSame('Test conversation', $subjectTags[0]->getValue());
    }

    #[DataProvider('rumourKinds')]
    public function testWrapsAndUnwrapsARumourOfAnyKind(int $kind): void
    {
        $rumour = Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString('Any kind'),
            new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey())]),
        );

        $giftWrap = $this->adapter->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey()
        );

        $this->assertSame($kind, $this->unwrapped($this->adapter, $giftWrap, $this->recipientKeyPair)->getKind()->toInt());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function rumourKinds(): iterable
    {
        yield 'text note' => [EventKind::TEXT_NOTE];
        yield 'reaction' => [7];
        yield 'file message' => [15];
    }

    public function testWrapRejectsMismatchedSenderKey(): void
    {
        $rumour = $this->createRumour('Test');
        $wrongKeyPair = KeyPair::generate(CryptoFixtures::signer());

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
        $textNote = Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Not a gift wrap'),
        )->sign($this->senderKeyPair, CryptoFixtures::signer());

        $this->assertSame(
            GiftWrapUnwrapFailure::NotGiftWrap,
            $this->adapter->unwrap($textNote, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapRejectsGiftWrapWithInvalidSignature(): void
    {
        $giftWrap = EventMother::fromRumour(Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::GIFT_WRAP),
            EventContent::fromString('fake'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertSame(
            GiftWrapUnwrapFailure::WrapSignatureInvalid,
            $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapRejectsSignedRumour(): void
    {
        $signedInner = $this->createRumour('Sneaky')->sign($this->senderKeyPair, CryptoFixtures::signer());
        $giftWrap = $this->sealAndWrap($signedInner, $this->senderKeyPair);

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourSigned,
            $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapRejectsARumourWhoseStatedIdDoesNotMatchItsFields(): void
    {
        $forged = JsonWireFormat::encode(
            [...$this->createRumour('Forged')->toArray(), 'id' => str_repeat('f', 64)],
            JsonWireFormat::EVENT,
        );
        $giftWrap = $this->wrapPayload($this->sealPayload($forged, $this->senderKeyPair)->toJson());

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourIdMismatch,
            $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapRejectsARumourThatStatesNoIdAsMalformed(): void
    {
        $withoutId = JsonWireFormat::encode(
            array_diff_key($this->createRumour('No id')->toArray(), ['id' => true]),
            JsonWireFormat::EVENT,
        );
        $giftWrap = $this->wrapPayload($this->sealPayload($withoutId, $this->senderKeyPair)->toJson());

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourMalformed,
            $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapRejectsTamperedGiftWrap(): void
    {
        $legitimate = $this->wrapRumour('Original');

        $tampered = new Event(
            Rumour::draft(
                $legitimate->getPubkey(),
                $legitimate->getKind(),
                EventContent::fromString('tampered ciphertext'),
                $legitimate->getTags(),
                $legitimate->getCreatedAt(),
            ),
            $legitimate->getId(),
            $legitimate->getSignature(),
        );

        $this->assertSame(
            GiftWrapUnwrapFailure::WrapSignatureInvalid,
            $this->adapter->unwrap($tampered, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsSealDecryptFailedForCiphertextItCannotOpen(): void
    {
        $giftWrap = $this->signedGiftWrap(KeyPair::generate(CryptoFixtures::signer()), 'not a nip-44 payload');

        $this->assertSame(GiftWrapUnwrapFailure::SealDecryptFailed, $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()));
    }

    public function testUnwrapReturnsSealDecryptFailedWhenItsCipherCannotOpenTheWrap(): void
    {
        $cipher = $this->createStub(ConversationCipherInterface::class);
        $cipher->method('decrypt')->willThrowException(new EncryptionException('cannot open'));
        $giftWrapper = new GiftWrapper($cipher, CryptoFixtures::signer(), new RandomGiftWrapEnvelopeFactory(CryptoFixtures::signer()));

        $this->assertSame(GiftWrapUnwrapFailure::SealDecryptFailed, $giftWrapper->unwrap($this->wrapRumour('Unreadable'), $this->recipientKeyPair->getPrivateKey()));
    }

    public function testUnwrapReturnsSealDecryptFailedForAWrapAddressedToSomeoneElse(): void
    {
        $giftWrap = $this->wrapRumour('Not for you');
        $stranger = KeyPair::generate(CryptoFixtures::signer());

        $this->assertSame(GiftWrapUnwrapFailure::SealDecryptFailed, $this->adapter->unwrap($giftWrap, $stranger->getPrivateKey()));
    }

    public function testUnwrapReturnsSealMalformedWhenTheWrapDoesNotHoldAnEvent(): void
    {
        $giftWrap = $this->wrapPayload('{"not":"an event"}');

        $this->assertSame(GiftWrapUnwrapFailure::SealMalformed, $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()));
    }

    public function testUnwrapReturnsSealMalformedWhenTheSealCarriesTags(): void
    {
        $seal = $this->sealPayload(
            $this->createRumour('Hello')->toJson(),
            $this->senderKeyPair,
            tags: new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey())]),
        );

        $this->assertSame(
            GiftWrapUnwrapFailure::SealMalformed,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsSealMalformedWhenTheSealWritesItsTagsAsAnObject(): void
    {
        $seal = $this->sealPayload($this->createRumour('Hello')->toJson(), $this->senderKeyPair);

        $this->assertSame(
            GiftWrapUnwrapFailure::SealMalformed,
            $this->adapter->unwrap(
                $this->wrapPayload(str_replace('"tags":[]', '"tags":{}', $seal->toJson())),
                $this->recipientKeyPair->getPrivateKey(),
            ),
        );
    }

    public function testUnwrapReturnsSealMalformedForATaggedSealOfTheWrongKind(): void
    {
        $seal = $this->sealPayload(
            $this->createRumour('Hello')->toJson(),
            $this->senderKeyPair,
            EventKind::TEXT_NOTE,
            new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey())]),
        );

        $this->assertSame(
            GiftWrapUnwrapFailure::SealMalformed,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapOpensASealWhoseOnlyTagIsAnExpiration(): void
    {
        $rumour = $this->unwrapped(
            $this->adapter,
            $this->wrapPayload($this->sealWithTags([['expiration', '1700000000']])->toJson()),
            $this->recipientKeyPair,
        );

        $this->assertSame('Hello', (string) $rumour->getContent());
    }

    public function testUnwrapOpensASealWhoseExpirationTagsDisagree(): void
    {
        $rumour = $this->unwrapped(
            $this->adapter,
            $this->wrapPayload($this->sealWithTags([['expiration', '1700000000'], ['expiration', '1700000001']])->toJson()),
            $this->recipientKeyPair,
        );

        $this->assertSame('Hello', (string) $rumour->getContent());
    }

    public function testUnwrapOpensASealWhoseExpirationHasPassed(): void
    {
        $rumour = $this->unwrapped(
            $this->adapter,
            $this->wrapPayload($this->sealWithTags([['expiration', '1']])->toJson()),
            $this->recipientKeyPair,
        );

        $this->assertSame('Hello', (string) $rumour->getContent());
    }

    public function testUnwrapOpensASealWhoseExpirationTagCarriesFurtherElements(): void
    {
        $rumour = $this->unwrapped(
            $this->adapter,
            $this->wrapPayload($this->sealWithTags([['expiration', '1700000000', 'extra']])->toJson()),
            $this->recipientKeyPair,
        );

        $this->assertSame('Hello', (string) $rumour->getContent());
    }

    /**
     * @param list<list<string>> $tags
     */
    #[DataProvider('refusedSealTags')]
    public function testUnwrapReturnsSealMalformedForASealWithATagThatIsNotAValidExpiration(array $tags): void
    {
        $this->assertSame(
            GiftWrapUnwrapFailure::SealMalformed,
            $this->adapter->unwrap($this->wrapPayload($this->sealWithTags($tags)->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function refusedSealTags(): iterable
    {
        yield 'expiration beside another tag' => [[['expiration', '1700000000'], ['alt', 'sealed']]];
        yield 'expiration with no value' => [[['expiration']]];
        yield 'expiration that is not a decimal timestamp' => [[['expiration', 'soon']]];
        yield 'expiration that is not canonical decimal' => [[['expiration', '01700000000']]];
        yield 'expiration that is negative' => [[['expiration', '-1']]];
        yield 'valueless expiration beside a valid one' => [[['expiration', '1700000000'], ['expiration']]];
    }

    public function testUnwrapOpensAnEphemeralGiftWrap(): void
    {
        $seal = $this->sealPayload($this->createRumour('Live')->toJson(), $this->senderKeyPair);

        $rumour = $this->unwrapped(
            $this->adapter,
            $this->wrapPayload($seal->toJson(), EventKind::EPHEMERAL_GIFT_WRAP),
            $this->recipientKeyPair,
        );

        $this->assertSame('Live', (string) $rumour->getContent());
    }

    public function testUnwrapReturnsSealMalformedWhenAnEphemeralGiftWrapHoldsATaggedSeal(): void
    {
        $seal = $this->sealPayload(
            $this->createRumour('Live')->toJson(),
            $this->senderKeyPair,
            tags: new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey())]),
        );

        $this->assertSame(
            GiftWrapUnwrapFailure::SealMalformed,
            $this->adapter->unwrap(
                $this->wrapPayload($seal->toJson(), EventKind::EPHEMERAL_GIFT_WRAP),
                $this->recipientKeyPair->getPrivateKey(),
            ),
        );
    }

    public function testUnwrapReturnsWrapSignatureInvalidForAForgedEphemeralGiftWrap(): void
    {
        $giftWrap = EventMother::fromRumour(Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EPHEMERAL_GIFT_WRAP),
            EventContent::fromString('fake'),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->assertSame(
            GiftWrapUnwrapFailure::WrapSignatureInvalid,
            $this->adapter->unwrap($giftWrap, $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsSealWrongKindWhenTheWrapHoldsANonSealEvent(): void
    {
        $seal = $this->sealPayload($this->createRumour('Hello')->toJson(), $this->senderKeyPair, EventKind::TEXT_NOTE);

        $this->assertSame(
            GiftWrapUnwrapFailure::SealWrongKind,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsSealSignatureInvalidForAForgedSeal(): void
    {
        $seal = $this->sealPayload($this->createRumour('Hello')->toJson(), $this->senderKeyPair);
        $forged = new Event(
            Rumour::draft(
                $seal->getPubkey(),
                $seal->getKind(),
                $seal->getContent(),
                $seal->getTags(),
                Timestamp::fromInt($seal->getCreatedAt()->toInt() - 1),
            ),
            $seal->getId(),
            $seal->getSignature(),
        );

        $this->assertSame(
            GiftWrapUnwrapFailure::SealSignatureInvalid,
            $this->adapter->unwrap($this->wrapPayload($forged->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsRumourDecryptFailedWhenTheSealCannotBeOpened(): void
    {
        $seal = Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::SEAL),
            EventContent::fromString('not a nip-44 payload'),
            new TagCollection(),
            Timestamp::now(),
        )->sign($this->senderKeyPair, CryptoFixtures::signer());

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourDecryptFailed,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsRumourMalformedWhenTheSealDoesNotHoldAnEvent(): void
    {
        $seal = $this->sealPayload('["not","an","event"]', $this->senderKeyPair);

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourMalformed,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsRumourMalformedWhenTheRumourWritesItsTagsAsAnObject(): void
    {
        $rumour = $this->createRumour('Hello');
        $tags = json_encode($rumour->getTags()->toJsonArray(), JSON_THROW_ON_ERROR);
        $seal = $this->sealPayload(str_replace('"tags":'.$tags, '"tags":{"0":'.substr($tags, 1, -1).'}', $rumour->toJson()), $this->senderKeyPair);

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourMalformed,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testUnwrapReturnsRumourPubkeyMismatchWhenTheSealerIsNotTheAuthor(): void
    {
        $impostor = KeyPair::generate(CryptoFixtures::signer());
        $seal = $this->sealPayload($this->createRumour('Impersonated')->toJson(), $impostor);

        $this->assertSame(
            GiftWrapUnwrapFailure::RumourPubkeyMismatch,
            $this->adapter->unwrap($this->wrapPayload($seal->toJson()), $this->recipientKeyPair->getPrivateKey()),
        );
    }

    public function testDeterministicWrapWithInjectedEnvelopeFactory(): void
    {
        $ephemeralKeyPair = KeyPair::generate(CryptoFixtures::signer());
        $envelope = new GiftWrapEnvelope(
            $ephemeralKeyPair,
            Timestamp::fromInt(1700000000),
            Timestamp::fromInt(1700000100),
        );

        $envelopeFactory = $this->createStub(GiftWrapEnvelopeFactoryInterface::class);
        $envelopeFactory->method('create')->willReturn($envelope);

        $giftWrapper = new GiftWrapper(new ConversationCipher(new Nip44Cipher(), CryptoFixtures::ecdh()), CryptoFixtures::signer(), $envelopeFactory);

        $rumour = $this->createRumour('Deterministic test');

        $giftWrap = $giftWrapper->wrapForRecipient(
            $rumour,
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey(),
        );

        $this->assertTrue($giftWrap->getPubkey()->equals($ephemeralKeyPair->getPublicKey()));
        $this->assertSame(1700000100, $giftWrap->getCreatedAt()->toInt());

        $unwrapped = $this->unwrapped($giftWrapper, $giftWrap, $this->recipientKeyPair);
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

        $unwrapped = $this->unwrapped($this->adapter, $giftWrap, $this->senderKeyPair);

        $this->assertSame('Self-copy', (string) $unwrapped->getContent());
    }

    public function testWrapForChatRoomWrapsToEveryReceiverAndTheSender(): void
    {
        $third = KeyPair::generate(CryptoFixtures::signer());
        $rumour = new RumourFactory($this->senderKeyPair->getPublicKey())->createPrivateMessage(
            new PublicKeyCollection([$this->recipientKeyPair->getPublicKey(), $third->getPublicKey()]),
            EventContent::fromString('Hello room'),
        );

        $wraps = $this->adapter->wrapForChatRoom($rumour, $this->senderKeyPair->getPrivateKey());

        $addressed = array_map(static fn (Event $wrap): string => (string) $wrap->getTags()->getSoleValueByType(TagType::pubkey())->getValue(), $wraps->toArray());
        sort($addressed);
        $this->assertSame($rumour->getChatRoom()->toHexes(), $addressed);

        foreach ([$this->senderKeyPair, $this->recipientKeyPair, $third] as $member) {
            $wrap = array_find($wraps->toArray(), static fn (Event $candidate): bool => $candidate->getTags()->getSoleValueByType(TagType::pubkey())->getValue() === $member->getPublicKey()->toHex());
            $this->assertSame('Hello room', (string) $this->unwrapped($this->adapter, $wrap ?? $this->fail('Expected a wrap for every member'), $member)->getContent());
        }
    }

    public function testWrapForChatRoomWrapsToTheSenderOnceWhenTheSenderIsAlsoTagged(): void
    {
        $rumour = new RumourFactory($this->senderKeyPair->getPublicKey())->createPrivateMessage(
            new PublicKeyCollection([$this->recipientKeyPair->getPublicKey(), $this->senderKeyPair->getPublicKey()]),
            EventContent::fromString('Hello'),
        );

        $this->assertCount(2, $this->adapter->wrapForChatRoom($rumour, $this->senderKeyPair->getPrivateKey()));
    }

    public function testWrapForChatRoomWrapsAReactionToTheSendersOwnMessageOncePerMember(): void
    {
        $factory = new RumourFactory($this->senderKeyPair->getPublicKey());
        $room = new PublicKeyCollection([$this->recipientKeyPair->getPublicKey()]);
        $reaction = $factory->createPrivateReaction($room, $factory->createPrivateMessage($room, EventContent::fromString('Hello')));

        $addressed = array_map(static fn (Event $wrap): string => (string) $wrap->getTags()->getSoleValueByType(TagType::pubkey())->getValue(), $this->adapter->wrapForChatRoom($reaction, $this->senderKeyPair->getPrivateKey())->toArray());
        sort($addressed);

        $this->assertSame($reaction->getChatRoom()->toHexes(), $addressed);
    }

    public function testWrapForChatRoomWrapsARoomOfOneExactlyOnceToItsAuthor(): void
    {
        $rumour = new RumourFactory($this->senderKeyPair->getPublicKey())->createPrivateMessage(
            new PublicKeyCollection([$this->senderKeyPair->getPublicKey()]),
            EventContent::fromString('Note to self'),
        );

        $wraps = $this->adapter->wrapForChatRoom($rumour, $this->senderKeyPair->getPrivateKey())->toArray();

        $this->assertCount(1, $wraps);
        $this->assertSame($this->senderKeyPair->getPublicKey()->toHex(), $wraps[0]->getTags()->getSoleValueByType(TagType::pubkey())->getValue());
        $this->assertSame('Note to self', (string) $this->unwrapped($this->adapter, $wraps[0], $this->senderKeyPair)->getContent());
    }

    public function testWrapForChatRoomRefusesASenderKeyThatDidNotWriteTheRumour(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->adapter->wrapForChatRoom($this->createRumour('Hello'), $this->recipientKeyPair->getPrivateKey());
    }

    public function testWrapsARumourThatSerialisesToTheLargestLengthAGiftWrapHolds(): void
    {
        $rumour = $this->rumourSerialisingTo(GiftWrapper::MAX_RUMOUR_LENGTH);

        $giftWrap = $this->adapter->wrapForRecipient($rumour, $this->senderKeyPair->getPrivateKey(), $this->recipientKeyPair->getPublicKey());

        $this->assertSame($rumour->toJson(), $this->unwrapped($this->adapter, $giftWrap, $this->recipientKeyPair)->toJson());
    }

    public function testWrapRefusesARumourOneByteOverTheLargestLengthAGiftWrapHolds(): void
    {
        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('163841 bytes');

        $this->adapter->wrapForRecipient(
            $this->rumourSerialisingTo(GiftWrapper::MAX_RUMOUR_LENGTH + 1),
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey(),
        );
    }

    public function testWrapRefusesAnOversizedRumourBeforeEncryptingOrSigningAnything(): void
    {
        $cipher = $this->createMock(ConversationCipherInterface::class);
        $cipher->expects($this->never())->method('encrypt');
        $envelopeFactory = $this->createMock(GiftWrapEnvelopeFactoryInterface::class);
        $envelopeFactory->expects($this->never())->method('create');
        $giftWrapper = new GiftWrapper($cipher, CryptoFixtures::signer(), $envelopeFactory);

        $this->expectException(GiftWrapException::class);

        $giftWrapper->wrapForRecipient(
            $this->rumourSerialisingTo(GiftWrapper::MAX_RUMOUR_LENGTH + 1),
            $this->senderKeyPair->getPrivateKey(),
            $this->recipientKeyPair->getPublicKey(),
        );
    }

    public function testWrapForChatRoomDerivesTheSendersPublicKeyOnceForTheWholeRoom(): void
    {
        $signer = CryptoFixtures::signer();
        $counting = $this->createMock(SignatureServiceInterface::class);
        $counting->expects($this->once())->method('derivePublicKey')->willReturnCallback($signer->derivePublicKey(...));
        $counting->method('sign')->willReturnCallback($signer->sign(...));
        $giftWrapper = new GiftWrapper(new ConversationCipher(new Nip44Cipher(), CryptoFixtures::ecdh()), $counting, new RandomGiftWrapEnvelopeFactory($signer));
        $rumour = new RumourFactory($this->senderKeyPair->getPublicKey())->createPrivateMessage(
            new PublicKeyCollection([$this->recipientKeyPair->getPublicKey(), KeyPair::generate($signer)->getPublicKey()]),
            EventContent::fromString('Hello room'),
        );

        $this->assertCount(3, $giftWrapper->wrapForChatRoom($rumour, $this->senderKeyPair->getPrivateKey()));
    }

    public function testWrapForChatRoomRefusesAnOversizedRumourBeforeWrappingToAnyMember(): void
    {
        $envelopeFactory = $this->createMock(GiftWrapEnvelopeFactoryInterface::class);
        $envelopeFactory->expects($this->never())->method('create');
        $giftWrapper = new GiftWrapper(new ConversationCipher(new Nip44Cipher(), CryptoFixtures::ecdh()), CryptoFixtures::signer(), $envelopeFactory);

        $this->expectException(GiftWrapException::class);
        $this->expectExceptionMessage('163840');

        $giftWrapper->wrapForChatRoom($this->rumourSerialisingTo(GiftWrapper::MAX_RUMOUR_LENGTH + 1), $this->senderKeyPair->getPrivateKey());
    }

    private function rumourSerialisingTo(int $length): Rumour
    {
        $overhead = strlen($this->createRumour('')->toJson());
        $rumour = $this->createRumour(str_repeat('a', $length - $overhead));
        $this->assertSame($length, strlen($rumour->toJson()));

        return $rumour;
    }

    private function createRumour(string $content): Rumour
    {
        return Rumour::draft(
            $this->senderKeyPair->getPublicKey(),
            EventKind::fromInt(EventKind::PRIVATE_MESSAGE),
            EventContent::fromString($content),
            new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey())]),
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

    private function unwrapped(GiftWrapper $giftWrapper, Event $giftWrap, KeyPair $recipient): Rumour
    {
        $rumour = $giftWrapper->unwrap($giftWrap, $recipient->getPrivateKey());
        $this->assertInstanceOf(Rumour::class, $rumour);

        return $rumour;
    }

    private function sealAndWrap(Event $signedInner, KeyPair $authorKeyPair): Event
    {
        return $this->wrapPayload($this->sealPayload($signedInner->toJson(), $authorKeyPair)->toJson());
    }

    /**
     * @param list<list<string>> $tags
     */
    private function sealWithTags(array $tags): Event
    {
        return $this->sealPayload(
            $this->createRumour('Hello')->toJson(),
            $this->senderKeyPair,
            tags: new TagCollection(array_map(Tag::fromArray(...), $tags)),
        );
    }

    private function sealPayload(
        string $plaintext,
        KeyPair $authorKeyPair,
        int $kind = EventKind::SEAL,
        TagCollection $tags = new TagCollection(),
    ): Event {
        $sealKey = ConversationKey::derive($authorKeyPair->getPrivateKey(), $this->recipientKeyPair->getPublicKey(), CryptoFixtures::ecdh());

        return Rumour::draft(
            $authorKeyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString(new Nip44Cipher()->encrypt($plaintext, $sealKey)),
            $tags,
            Timestamp::now(),
        )->sign($authorKeyPair, CryptoFixtures::signer());
    }

    private function wrapPayload(string $plaintext, int $kind = EventKind::GIFT_WRAP): Event
    {
        $signer = CryptoFixtures::signer();
        $recipientPublicKey = $this->recipientKeyPair->getPublicKey();
        $ephemeralKeyPair = KeyPair::generate($signer);
        $wrapKey = ConversationKey::derive($ephemeralKeyPair->getPrivateKey(), $recipientPublicKey, CryptoFixtures::ecdh());

        return $this->signedGiftWrap($ephemeralKeyPair, new Nip44Cipher()->encrypt($plaintext, $wrapKey), $kind);
    }

    private function signedGiftWrap(KeyPair $keyPair, string $content, int $kind = EventKind::GIFT_WRAP): Event
    {
        return Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString($content),
            new TagCollection([Tag::pubkey($this->recipientKeyPair->getPublicKey())]),
            Timestamp::now(),
        )->sign($keyPair, CryptoFixtures::signer());
    }
}
