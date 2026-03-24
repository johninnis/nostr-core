<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class ZapReceipt implements PaymentReceipt
{
    private function __construct(
        private ?PublicKey $senderPubkey,
        private ?PublicKey $recipientPubkey,
        private ?ZapAmount $amount,
        private ?string $message,
    ) {
    }

    public function getSenderPubkey(): ?PublicKey
    {
        return $this->senderPubkey;
    }

    public function getRecipientPubkey(): ?PublicKey
    {
        return $this->recipientPubkey;
    }

    public function getAmount(): ?ZapAmount
    {
        return $this->amount;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public static function fromEvent(Event $event): ?self
    {
        if (EventKind::ZAP_RECEIPT !== $event->getKind()->toInt()) {
            return null;
        }

        $tags = $event->getTags();
        $zapRequest = self::extractZapRequest($tags);

        $senderPubkey = $tags->getFirstPubkeyByType(TagType::senderPubkey())
            ?? self::extractPubkeyFromZapRequest($zapRequest);

        $recipientPubkey = $tags->getFirstPubkeyByType(TagType::pubkey());

        $amount = self::resolveAmount($zapRequest, $tags);

        $message = $zapRequest['content'] ?? null;
        if ('' === $message) {
            $message = null;
        }

        return new self($senderPubkey, $recipientPubkey, $amount, $message);
    }

    private static function extractZapRequest(TagCollection $tags): ?array
    {
        $values = $tags->getValuesByType(TagType::description());

        foreach ($values as $value) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function extractPubkeyFromZapRequest(?array $zapRequest): ?PublicKey
    {
        if (null === $zapRequest || !isset($zapRequest['pubkey'])) {
            return null;
        }

        return PublicKey::fromHex($zapRequest['pubkey']);
    }

    private static function resolveAmount(?array $zapRequest, TagCollection $tags): ?ZapAmount
    {
        $fromZapRequestTags = self::extractAmountFromTags($zapRequest['tags'] ?? []);
        if (null !== $fromZapRequestTags) {
            return $fromZapRequestTags;
        }

        $receiptValues = $tags->getValuesByType(TagType::amount());
        foreach ($receiptValues as $value) {
            $intValue = (int) $value;
            if ($intValue > 0) {
                return ZapAmount::fromMillisats($intValue);
            }
        }

        $bolt11Values = $tags->getValuesByType(TagType::bolt11());
        foreach ($bolt11Values as $value) {
            $parsed = ZapAmount::fromBolt11($value);
            if (null !== $parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private static function extractAmountFromTags(array $tags): ?ZapAmount
    {
        foreach ($tags as $tag) {
            if (is_array($tag) && ($tag[0] ?? null) === 'amount' && isset($tag[1])) {
                $intValue = (int) $tag[1];
                if ($intValue > 0) {
                    return ZapAmount::fromMillisats($intValue);
                }
            }
        }

        return null;
    }
}
