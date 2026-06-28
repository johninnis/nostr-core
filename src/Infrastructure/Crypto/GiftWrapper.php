<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\GiftWrapException;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\GiftWrapServiceInterface;
use Innis\Nostr\Core\Domain\Service\Nip44EncryptionInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use InvalidArgumentException;
use Override;
use Throwable;

// Deliberate: a composed cryptographic capability, kept with the crypto family despite reaching primitives only through ports — see ADR-0035
final class GiftWrapper implements GiftWrapServiceInterface
{
    public function __construct(
        private readonly Nip44EncryptionInterface $encryption,
        private readonly SignatureServiceInterface $signatureService,
        private readonly EcdhServiceInterface $ecdhService,
        private readonly GiftWrapEnvelopeFactoryInterface $envelopeFactory,
    ) {
    }

    public static function create(
        Nip44EncryptionInterface $encryption,
        SignatureServiceInterface $signatureService,
        EcdhServiceInterface $ecdhService,
    ): self {
        return new self($encryption, $signatureService, $ecdhService, new RandomGiftWrapEnvelopeFactory($signatureService));
    }

    #[Override]
    public function wrapForRecipient(
        Event $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
    ): Event {
        $this->validateRumour($rumour, $senderPrivateKey);

        $senderKeyPair = KeyPair::fromPrivateKey($senderPrivateKey, $this->signatureService);

        $envelope = $this->envelopeFactory->create();
        $ephemeralKeyPair = $envelope->getEphemeralKeyPair();

        try {
            $seal = new Event(
                $senderKeyPair->getPublicKey(),
                $envelope->getSealTimestamp(),
                EventKind::fromInt(EventKind::SEAL),
                new TagCollection(),
                EventContent::fromString($this->encryptFor($rumour, $senderKeyPair, $recipientPublicKey)),
            )->sign($senderKeyPair, $this->signatureService);

            return new Event(
                $ephemeralKeyPair->getPublicKey(),
                $envelope->getWrapTimestamp(),
                EventKind::fromInt(EventKind::GIFT_WRAP),
                new TagCollection([Tag::pubkey($recipientPublicKey->toHex())]),
                EventContent::fromString($this->encryptFor($seal, $ephemeralKeyPair, $recipientPublicKey)),
            )->sign($ephemeralKeyPair, $this->signatureService);
        } finally {
            $ephemeralKeyPair->getPrivateKey()->zero();
        }
    }

    #[Override]
    public function unwrap(
        Event $giftWrap,
        PrivateKey $recipientPrivateKey,
    ): Event {
        $this->validateGiftWrap($giftWrap);

        $seal = $this->decryptLayer($giftWrap, $recipientPrivateKey, 'gift wrap');
        $this->validateSeal($seal);

        $rumour = $this->decryptLayer($seal, $recipientPrivateKey, 'seal');
        $this->validateDecryptedRumour($rumour, $seal);

        return $rumour;
    }

    private function encryptFor(Event $innerEvent, KeyPair $signingKeyPair, PublicKey $recipientPublicKey): string
    {
        $conversationKey = ConversationKey::derive($signingKeyPair->getPrivateKey(), $recipientPublicKey, $this->ecdhService);

        try {
            return $this->encryption->encrypt($this->serialiseEvent($innerEvent), $conversationKey);
        } finally {
            $conversationKey->zero();
        }
    }

    private function decryptLayer(Event $envelope, PrivateKey $recipientPrivateKey, string $layerName): Event
    {
        $conversationKey = ConversationKey::derive($recipientPrivateKey, $envelope->getPubkey(), $this->ecdhService);

        try {
            try {
                $json = $this->encryption->decrypt((string) $envelope->getContent(), $conversationKey);
            } catch (Throwable $e) {
                throw new GiftWrapException('Failed to decrypt '.$layerName, 0, $e);
            }

            try {
                return $this->deserialiseEvent($json);
            } catch (Throwable $e) {
                throw new GiftWrapException('Failed to parse decrypted '.$layerName, 0, $e);
            }
        } finally {
            $conversationKey->zero();
        }
    }

    private function validateRumour(Event $rumour, PrivateKey $senderPrivateKey): void
    {
        if (!$rumour->getKind()->is(EventKind::PRIVATE_MESSAGE)) {
            throw new GiftWrapException('Rumour must be kind 14 (private message)');
        }

        if ($rumour->isSigned()) {
            throw new GiftWrapException('Rumour must not be signed');
        }

        if (!$this->signatureService->derivePublicKey($senderPrivateKey)->equals($rumour->getPubkey())) {
            throw new InvalidArgumentException('Sender private key does not match rumour public key');
        }
    }

    private function validateGiftWrap(Event $giftWrap): void
    {
        if (!$giftWrap->getKind()->is(EventKind::GIFT_WRAP)) {
            throw new GiftWrapException('Event must be kind 1059 (gift wrap)');
        }

        if (!$giftWrap->isSigned() || !$giftWrap->verify($this->signatureService)) {
            throw new GiftWrapException('Gift wrap signature is invalid');
        }
    }

    private function validateSeal(Event $seal): void
    {
        if (!$seal->getKind()->is(EventKind::SEAL)) {
            throw new GiftWrapException('Decrypted event is not a seal (kind 13)');
        }

        if (!$seal->isSigned() || !$seal->verify($this->signatureService)) {
            throw new GiftWrapException('Seal signature is invalid');
        }
    }

    private function validateDecryptedRumour(Event $rumour, Event $seal): void
    {
        if (!$rumour->getKind()->is(EventKind::PRIVATE_MESSAGE)) {
            throw new GiftWrapException('Decrypted event is not a rumour (kind 14)');
        }

        if ($rumour->isSigned()) {
            throw new GiftWrapException('Decrypted rumour must not be signed');
        }

        if (!$rumour->getPubkey()->equals($seal->getPubkey())) {
            throw new GiftWrapException('Rumour pubkey does not match seal pubkey');
        }
    }

    private function serialiseEvent(Event $event): string
    {
        try {
            return $event->toJson();
        } catch (InvalidEventException $exception) {
            throw new GiftWrapException('Failed to serialise event', previous: $exception);
        }
    }

    private function deserialiseEvent(string $json): Event
    {
        return Event::fromJson($json)
            ?? throw new GiftWrapException('Failed to deserialise event JSON');
    }
}
