<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class Nutzap implements PaymentReceipt
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
        if (EventKind::NUTZAP !== $event->getKind()->toInt()) {
            return null;
        }

        $tags = $event->getTags();

        $recipientPubkey = $tags->getFirstPubkeyByType(TagType::pubkey());
        $amount = self::sumProofAmounts($tags);

        $message = (string) $event->getContent();
        if ('' === $message) {
            $message = null;
        }

        return new self($event->getPubkey(), $recipientPubkey, $amount, $message);
    }

    private static function sumProofAmounts(TagCollection $tags): ?ZapAmount
    {
        $proofValues = $tags->getValuesByType(TagType::proof());
        $totalAmount = 0;
        $hasValidProof = false;

        foreach ($proofValues as $proofJson) {
            $decoded = json_decode($proofJson, true);
            if (!is_array($decoded)) {
                continue;
            }

            if (isset($decoded['amount']) && is_numeric($decoded['amount'])) {
                $totalAmount += (int) $decoded['amount'];
                $hasValidProof = true;
            }
        }

        if (!$hasValidProof) {
            return null;
        }

        $unitValues = $tags->getValuesByType(TagType::unit());
        $unit = $unitValues[0] ?? 'sat';

        return 'msat' === $unit
            ? ZapAmount::fromMillisats($totalAmount)
            : ZapAmount::fromSats($totalAmount);
    }
}
