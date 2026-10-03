<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Collection\EventCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\EcdhException;
use Innis\Nostr\Core\Domain\Exception\EncryptionException;
use Innis\Nostr\Core\Domain\Exception\GiftWrapException;
use Innis\Nostr\Core\Domain\Failure\GiftWrapUnwrapFailure;
use Innis\Nostr\Core\Domain\Failure\RumourParseFailure;
use Innis\Nostr\Core\Domain\Service\ConversationCipher;
use Innis\Nostr\Core\Domain\Service\ConversationCipherInterface;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\GiftWrapServiceInterface;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\Nip44EncryptionInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use Override;

final readonly class GiftWrapper implements GiftWrapServiceInterface
{
    // Deliberate: the seal is the rumour's NIP-44 payload inside a second NIP-44 plaintext, so the default 262144-byte maximum admits a rumour only up to the padded length whose payload still fits it — see ADR-0117
    public const int MAX_RUMOUR_LENGTH = 163840;

    public function __construct(
        private ConversationCipherInterface $cipher,
        private SignatureServiceInterface $signatureService,
        private GiftWrapEnvelopeFactoryInterface $envelopeFactory,
    ) {
    }

    public static function create(
        Nip44EncryptionInterface $encryption,
        SignatureServiceInterface $signatureService,
        EcdhServiceInterface $ecdhService,
    ): self {
        return new self(new ConversationCipher($encryption, $ecdhService), $signatureService, new RandomGiftWrapEnvelopeFactory($signatureService));
    }

    #[Override]
    public function wrapForRecipient(
        Rumour $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
    ): Event {
        $senderKeyPair = $this->senderKeyPairFor($rumour, $senderPrivateKey);

        return $this->sealAndWrap($this->wrappableRumourJson($rumour), $senderKeyPair, $recipientPublicKey);
    }

    #[Override]
    public function wrapForChatRoom(
        Rumour $rumour,
        PrivateKey $senderPrivateKey,
    ): EventCollection {
        $senderKeyPair = $this->senderKeyPairFor($rumour, $senderPrivateKey);
        $rumourJson = $this->wrappableRumourJson($rumour);

        return new EventCollection(array_map(
            fn (PublicKey $member): Event => $this->sealAndWrap($rumourJson, $senderKeyPair, $member),
            $rumour->getChatRoom()->toArray(),
        ));
    }

    #[Override]
    public function unwrap(
        Event $giftWrap,
        PrivateKey $recipientPrivateKey,
    ): Rumour|GiftWrapUnwrapFailure {
        $seal = $this->openGiftWrap($giftWrap, $recipientPrivateKey);

        if ($seal instanceof GiftWrapUnwrapFailure) {
            return $seal;
        }

        $rumour = $this->openSeal($seal, $recipientPrivateKey);

        if ($rumour instanceof GiftWrapUnwrapFailure) {
            return $rumour;
        }

        return $rumour->getPubkey()->equals($seal->getPubkey())
            ? $rumour
            : GiftWrapUnwrapFailure::RumourPubkeyMismatch;
    }

    private function openGiftWrap(Event $giftWrap, PrivateKey $recipientPrivateKey): Event|GiftWrapUnwrapFailure
    {
        if (!$giftWrap->getKind()->is(EventKind::GIFT_WRAP) && !$giftWrap->getKind()->is(EventKind::EPHEMERAL_GIFT_WRAP)) {
            return GiftWrapUnwrapFailure::NotGiftWrap;
        }

        if (!$giftWrap->verify($this->signatureService)) {
            return GiftWrapUnwrapFailure::WrapSignatureInvalid;
        }

        $sealJson = $this->decrypt($giftWrap, $recipientPrivateKey);

        if (null === $sealJson) {
            return GiftWrapUnwrapFailure::SealDecryptFailed;
        }

        $seal = Event::tryFromJson($sealJson);

        if (null === $seal || !self::holdsOnlyExpirations($seal->getTags())) {
            return GiftWrapUnwrapFailure::SealMalformed;
        }

        if (!$seal->getKind()->is(EventKind::SEAL)) {
            return GiftWrapUnwrapFailure::SealWrongKind;
        }

        return $seal->verify($this->signatureService) ? $seal : GiftWrapUnwrapFailure::SealSignatureInvalid;
    }

    // Deliberate: NIP-17 asks for a disappearing message's expiration on the seal as well as the wrap, so a seal may carry expiration tags that each state a valid NIP-40 timestamp, and nothing else — see ADR-0133
    private static function holdsOnlyExpirations(TagCollection $tags): bool
    {
        $expiration = TagType::expiration();

        return array_all(
            $tags->toArray(),
            static fn (Tag $tag): bool => $tag->getType()->equals($expiration) && null !== Timestamp::tryFromDecimalString($tag->getValue() ?? ''),
        );
    }

    private function openSeal(Event $seal, PrivateKey $recipientPrivateKey): Rumour|GiftWrapUnwrapFailure
    {
        $rumourJson = $this->decrypt($seal, $recipientPrivateKey);

        if (null === $rumourJson) {
            return GiftWrapUnwrapFailure::RumourDecryptFailed;
        }

        $data = JsonWireFormat::decodeObject($rumourJson);

        if (null === $data) {
            return GiftWrapUnwrapFailure::RumourMalformed;
        }

        if (isset($data['sig']) && '' !== $data['sig']) {
            return GiftWrapUnwrapFailure::RumourSigned;
        }

        $rumour = Rumour::tryFromArray($data);

        return $rumour instanceof Rumour ? $rumour : match ($rumour) {
            RumourParseFailure::Malformed => GiftWrapUnwrapFailure::RumourMalformed,
            RumourParseFailure::IdMismatch => GiftWrapUnwrapFailure::RumourIdMismatch,
        };
    }

    private function decrypt(Event $envelope, PrivateKey $recipientPrivateKey): ?string
    {
        // Deliberate: the ciphertext comes from a peer, so the primitive's thrown decryption fault is converted to a returned failure here, where it enters — see ADR-0089
        try {
            return $this->cipher->decrypt((string) $envelope->getContent(), $recipientPrivateKey, $envelope->getPubkey());
        } catch (EcdhException|EncryptionException) {
            return null;
        }
    }

    private function senderKeyPairFor(Rumour $rumour, PrivateKey $senderPrivateKey): KeyPair
    {
        $senderKeyPair = KeyPair::fromPrivateKey($senderPrivateKey, $this->signatureService);

        if (!$senderKeyPair->getPublicKey()->equals($rumour->getPubkey())) {
            throw new InvalidArgumentException('Sender private key does not match rumour public key');
        }

        return $senderKeyPair;
    }

    private function sealAndWrap(string $rumourJson, KeyPair $senderKeyPair, PublicKey $recipientPublicKey): Event
    {
        $envelope = $this->envelopeFactory->create();
        $ephemeralKeyPair = $envelope->getEphemeralKeyPair();

        try {
            $seal = Rumour::draft(
                $senderKeyPair->getPublicKey(),
                EventKind::fromInt(EventKind::SEAL),
                EventContent::fromString($this->cipher->encrypt($rumourJson, $senderKeyPair->getPrivateKey(), $recipientPublicKey)),
                new TagCollection(),
                $envelope->getSealTimestamp(),
            )->sign($senderKeyPair, $this->signatureService);

            return Rumour::draft(
                $ephemeralKeyPair->getPublicKey(),
                EventKind::fromInt(EventKind::GIFT_WRAP),
                EventContent::fromString($this->cipher->encrypt($seal->toJson(), $ephemeralKeyPair->getPrivateKey(), $recipientPublicKey)),
                new TagCollection([Tag::pubkey($recipientPublicKey)]),
                $envelope->getWrapTimestamp(),
            )->sign($ephemeralKeyPair, $this->signatureService);
        } finally {
            $ephemeralKeyPair->getPrivateKey()->zero();
        }
    }

    private function wrappableRumourJson(Rumour $rumour): string
    {
        $rumourJson = $rumour->toJson();
        $length = strlen($rumourJson);

        if ($length > self::MAX_RUMOUR_LENGTH) {
            throw new GiftWrapException(sprintf('Rumour serialises to %d bytes; a gift wrap holds at most %d, the largest rumour whose seal fits the NIP-44 maximum plaintext of %d bytes', $length, self::MAX_RUMOUR_LENGTH, Nip44Cipher::DEFAULT_MAX_PLAINTEXT_LENGTH));
        }

        return $rumourJson;
    }
}
