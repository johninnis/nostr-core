<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\GiftWrapException;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\EcdhServiceInterface;
use Innis\Nostr\Core\Domain\Service\GiftWrapServiceInterface;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\Nip44EncryptionInterface;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use InvalidArgumentException;
use Override;
use Throwable;

// Deliberate: a composed cryptographic capability, kept with the crypto family despite reaching primitives only through ports — see ADR-0035
final class GiftWrapper implements GiftWrapServiceInterface
{
    // Deliberate: four cohesive crypto collaborators for one NIP-59 operation, not a group to fold into a parameter object — see ADR-0035
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
        Rumour $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
    ): Event {
        $this->validateRumour($rumour, $senderPrivateKey);

        $senderKeyPair = KeyPair::fromPrivateKey($senderPrivateKey, $this->signatureService);

        $envelope = $this->envelopeFactory->create();
        $ephemeralKeyPair = $envelope->getEphemeralKeyPair();

        try {
            $seal = new Rumour(
                $senderKeyPair->getPublicKey(),
                $envelope->getSealTimestamp(),
                EventKind::fromInt(EventKind::SEAL),
                new TagCollection(),
                EventContent::fromString($this->encryptFor($rumour, $senderKeyPair, $recipientPublicKey)),
            )->sign($senderKeyPair, $this->signatureService);

            return new Rumour(
                $ephemeralKeyPair->getPublicKey(),
                $envelope->getWrapTimestamp(),
                EventKind::fromInt(EventKind::GIFT_WRAP),
                new TagCollection([Tag::pubkey($recipientPublicKey)]),
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
    ): Rumour {
        $this->validateGiftWrap($giftWrap);

        $sealJson = $this->decrypt($giftWrap, $recipientPrivateKey, 'gift wrap');
        $seal = Event::fromJson($sealJson)
            ?? throw new GiftWrapException('Failed to parse decrypted gift wrap');
        $this->validateSeal($seal);

        $rumourJson = $this->decrypt($seal, $recipientPrivateKey, 'seal');
        $rumour = $this->deserialiseRumour($rumourJson);
        $this->validateDecryptedRumour($rumour, $seal);

        return $rumour;
    }

    private function encryptFor(Rumour|Event $inner, KeyPair $signingKeyPair, PublicKey $recipientPublicKey): string
    {
        $conversationKey = ConversationKey::derive($signingKeyPair->getPrivateKey(), $recipientPublicKey, $this->ecdhService);

        try {
            return $this->encryption->encrypt($this->serialise($inner), $conversationKey);
        } finally {
            $conversationKey->zero();
        }
    }

    private function decrypt(Event $envelope, PrivateKey $recipientPrivateKey, string $layerName): string
    {
        $conversationKey = ConversationKey::derive($recipientPrivateKey, $envelope->getPubkey(), $this->ecdhService);

        try {
            return $this->encryption->decrypt((string) $envelope->getContent(), $conversationKey);
        } catch (Throwable $e) {
            throw new GiftWrapException('Failed to decrypt '.$layerName, 0, $e);
        } finally {
            $conversationKey->zero();
        }
    }

    private function validateRumour(Rumour $rumour, PrivateKey $senderPrivateKey): void
    {
        if (!$rumour->getKind()->is(EventKind::PRIVATE_MESSAGE)) {
            throw new GiftWrapException('Rumour must be kind 14 (private message)');
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

        if (!$giftWrap->verify($this->signatureService)) {
            throw new GiftWrapException('Gift wrap signature is invalid');
        }
    }

    private function validateSeal(Event $seal): void
    {
        if (!$seal->getKind()->is(EventKind::SEAL)) {
            throw new GiftWrapException('Decrypted event is not a seal (kind 13)');
        }

        if (!$seal->verify($this->signatureService)) {
            throw new GiftWrapException('Seal signature is invalid');
        }
    }

    private function validateDecryptedRumour(Rumour $rumour, Event $seal): void
    {
        if (!$rumour->getKind()->is(EventKind::PRIVATE_MESSAGE)) {
            throw new GiftWrapException('Decrypted event is not a rumour (kind 14)');
        }

        if (!$rumour->getPubkey()->equals($seal->getPubkey())) {
            throw new GiftWrapException('Rumour pubkey does not match seal pubkey');
        }
    }

    private function serialise(Rumour|Event $inner): string
    {
        try {
            return $inner->toJson();
        } catch (InvalidEventException $exception) {
            throw new GiftWrapException('Failed to serialise event', previous: $exception);
        }
    }

    private function deserialiseRumour(string $json): Rumour
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data) {
            throw new GiftWrapException('Failed to parse decrypted seal');
        }

        if (isset($data['sig']) && '' !== $data['sig']) {
            throw new GiftWrapException('Decrypted rumour must not be signed');
        }

        return Rumour::fromArray($data)
            ?? throw new GiftWrapException('Failed to parse decrypted seal');
    }
}
