<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class ZapReceipt implements PaymentReceiptInterface
{
    private function __construct(
        private ?PublicKey $senderPubkey,
        private ?PublicKey $recipientPubkey,
        private ZapAmount $amount,
        private ?string $message,
    ) {
    }

    #[Override]
    public function getSenderPubkey(): ?PublicKey
    {
        return $this->senderPubkey;
    }

    #[Override]
    public function getRecipientPubkey(): ?PublicKey
    {
        return $this->recipientPubkey;
    }

    #[Override]
    public function getAmount(): ZapAmount
    {
        return $this->amount;
    }

    #[Override]
    public function getMessage(): ?string
    {
        return $this->message;
    }

    public static function fromEvent(Event $event): ?self
    {
        if (!$event->getKind()->is(EventKind::ZAP_RECEIPT)) {
            return null;
        }

        $tags = $event->getTags();
        $zapRequest = self::extractZapRequest($tags);

        $amount = self::resolveAmount($zapRequest, $tags);
        if (null === $amount) {
            return null;
        }

        $senderPubkey = $tags->getFirstPubkeyByType(TagType::senderPubkey())
            ?? self::extractPubkeyFromZapRequest($zapRequest);

        $recipientPubkey = $tags->getFirstPubkeyByType(TagType::pubkey());

        $message = null !== $zapRequest ? JsonWireFormat::stringField($zapRequest, 'content') : null;
        if ('' === $message) {
            $message = null;
        }

        return new self($senderPubkey, $recipientPubkey, $amount, $message);
    }

    private static function extractZapRequest(TagCollection $tags): ?array
    {
        $values = $tags->getValuesByType(TagType::description());

        foreach ($values as $value) {
            $decoded = JsonWireFormat::decodeArray($value);
            if (null !== $decoded) {
                return $decoded;
            }
        }

        return null;
    }

    private static function extractPubkeyFromZapRequest(?array $zapRequest): ?PublicKey
    {
        if (null === $zapRequest) {
            return null;
        }

        $pubkey = JsonWireFormat::stringField($zapRequest, 'pubkey');

        return null !== $pubkey ? PublicKey::fromHex($pubkey) : null;
    }

    private static function resolveAmount(?array $zapRequest, TagCollection $tags): ?ZapAmount
    {
        $bolt11Amount = self::extractBolt11Amount($tags);
        if (null === $bolt11Amount) {
            return null;
        }

        if (!self::zapRequestAmountMatches($zapRequest['tags'] ?? [], $bolt11Amount)) {
            return null;
        }

        return $bolt11Amount;
    }

    private static function extractBolt11Amount(TagCollection $tags): ?ZapAmount
    {
        foreach ($tags->getValuesByType(TagType::bolt11()) as $value) {
            $parsed = ZapAmount::fromBolt11($value);
            if (null !== $parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private static function zapRequestAmountMatches(array $requestTags, ZapAmount $bolt11Amount): bool
    {
        foreach ($requestTags as $tag) {
            if (!is_array($tag) || 'amount' !== ($tag[0] ?? null) || !isset($tag[1])) {
                continue;
            }

            if (!is_numeric($tag[1]) || (int) $tag[1] !== $bolt11Amount->toMillisats()) {
                return false;
            }
        }

        return true;
    }
}
