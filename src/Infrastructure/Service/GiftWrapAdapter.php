<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\GiftWrapException;
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
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use Throwable;

final class GiftWrapAdapter implements GiftWrapServiceInterface
{
    public function __construct(
        private readonly Nip44EncryptionInterface $encryption,
        private readonly SignatureServiceInterface $signatureService,
        private readonly EcdhServiceInterface $ecdhService,
    ) {
    }

    public function wrapForRecipient(
        Event $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
        ?KeyPair $ephemeralKeyPair = null,
        ?Timestamp $sealTimestamp = null,
        ?Timestamp $wrapTimestamp = null,
    ): Event {
        $this->validateRumour($rumour, $senderPrivateKey);

        $seal = $this->encryptAndWrap(
            $rumour,
            $senderPrivateKey,
            $recipientPublicKey,
            EventKind::seal(),
            TagCollection::empty(),
            $sealTimestamp
        );

        $ephemeral = $ephemeralKeyPair ?? KeyPair::generate($this->signatureService);
        $ownsEphemeral = null === $ephemeralKeyPair;

        try {
            return $this->encryptAndWrap(
                $seal,
                $ephemeral->getPrivateKey(),
                $recipientPublicKey,
                EventKind::giftWrap(),
                new TagCollection([Tag::pubkey($recipientPublicKey->toHex())]),
                $wrapTimestamp
            );
        } finally {
            if ($ownsEphemeral) {
                $ephemeral->getPrivateKey()->zero();
            }
        }
    }

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

    private function encryptAndWrap(
        Event $innerEvent,
        PrivateKey $signingKey,
        PublicKey $recipientPublicKey,
        EventKind $kind,
        TagCollection $tags,
        ?Timestamp $timestamp,
    ): Event {
        $conversationKey = ConversationKey::derive($signingKey, $recipientPublicKey, $this->ecdhService);

        try {
            $encrypted = $this->encryption->encrypt($this->serialiseEvent($innerEvent), $conversationKey);

            $event = new Event(
                $this->signatureService->derivePublicKey($signingKey),
                $timestamp ?? Timestamp::randomised(),
                $kind,
                $tags,
                EventContent::fromString($encrypted)
            );

            return $event->sign($signingKey, $this->signatureService);
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
        if (!$rumour->getKind()->equals(EventKind::privateMessage())) {
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
        if (!$giftWrap->getKind()->equals(EventKind::giftWrap())) {
            throw new GiftWrapException('Event must be kind 1059 (gift wrap)');
        }

        if (!$giftWrap->isSigned() || !$giftWrap->verify($this->signatureService)) {
            throw new GiftWrapException('Gift wrap signature is invalid');
        }
    }

    private function validateSeal(Event $seal): void
    {
        if (!$seal->getKind()->equals(EventKind::seal())) {
            throw new GiftWrapException('Decrypted event is not a seal (kind 13)');
        }

        if (!$seal->isSigned() || !$seal->verify($this->signatureService)) {
            throw new GiftWrapException('Seal signature is invalid');
        }
    }

    private function validateDecryptedRumour(Event $rumour, Event $seal): void
    {
        if (!$rumour->getKind()->equals(EventKind::privateMessage())) {
            throw new GiftWrapException('Decrypted event is not a rumour (kind 14)');
        }

        if (!$rumour->getPubkey()->equals($seal->getPubkey())) {
            throw new GiftWrapException('Rumour pubkey does not match seal pubkey');
        }
    }

    private function serialiseEvent(Event $event): string
    {
        $json = json_encode($event->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $json) {
            throw new GiftWrapException('Failed to serialise event');
        }

        return $json;
    }

    private function deserialiseEvent(string $json): Event
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new GiftWrapException('Failed to deserialise event JSON');
        }

        return Event::fromArray($data);
    }
}
