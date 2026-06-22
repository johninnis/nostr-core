<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class Nutzap implements PaymentReceiptInterface
{
    private function __construct(
        private ?PublicKey $senderPubkey,
        private ?PublicKey $recipientPubkey,
        private ?ZapAmount $amount,
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
    public function getAmount(): ?ZapAmount
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
        if (!$event->getKind()->is(EventKind::NUTZAP)) {
            return null;
        }

        $tags = $event->getTags();

        $recipientPubkey = $tags->getFirstPubkeyByType(TagType::pubkey());

        $proofAmounts = self::extractProofAmounts($tags);
        $amount = null;
        if ([] !== $proofAmounts) {
            $amount = self::totalWithinCap($proofAmounts, $tags);
            if (null === $amount) {
                return null;
            }
        }

        $message = (string) $event->getContent();
        if ('' === $message) {
            $message = null;
        }

        return new self($event->getPubkey(), $recipientPubkey, $amount, $message);
    }

    private static function extractProofAmounts(TagCollection $tags): array
    {
        $amounts = [];

        foreach ($tags->getValuesByType(TagType::proof()) as $proofJson) {
            $decoded = JsonWireFormat::decodeArray($proofJson);
            if (null !== $decoded && isset($decoded['amount']) && is_numeric($decoded['amount'])) {
                $amounts[] = (int) $decoded['amount'];
            }
        }

        return $amounts;
    }

    private static function totalWithinCap(array $proofAmounts, TagCollection $tags): ?ZapAmount
    {
        $unitValues = $tags->getValuesByType(TagType::unit());
        $unit = $unitValues[0] ?? 'sat';

        $maxTotal = 'msat' === $unit
            ? ZapAmount::MAX_MILLISATS
            : intdiv(ZapAmount::MAX_MILLISATS, ZapAmount::MILLISATS_PER_SAT);

        $total = 0;
        foreach ($proofAmounts as $proofAmount) {
            if ($proofAmount < 0 || $proofAmount > $maxTotal - $total) {
                return null;
            }

            $total += $proofAmount;
        }

        return 'msat' === $unit
            ? ZapAmount::fromMillisats($total)
            : ZapAmount::fromSats($total);
    }
}
