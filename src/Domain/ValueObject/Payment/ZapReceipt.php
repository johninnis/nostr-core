<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\DecimalIntegerParser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class ZapReceipt implements PaymentReceiptInterface
{
    private function __construct(
        private Event $receipt,
        private Event $zapRequest,
        private ZapAmount $amount,
    ) {
    }

    // Deliberate: exactly one description and one bolt11 are read, and the zap request is parsed once here and held, so the verifier and every other reader see the same request — see ADR-0079
    public static function tryFromEvent(Event $event): ?self
    {
        if (!$event->getKind()->is(EventKind::ZAP_RECEIPT)) {
            return null;
        }

        $description = $event->getTags()->getSoleValueByType(TagType::description())->getValue();
        $zapRequest = null === $description ? null : Event::tryFromJson($description);
        $bolt11 = $event->getTags()->getSoleValueByType(TagType::bolt11())->getValue();
        $amount = null === $bolt11 ? null : ZapAmount::tryFromBolt11($bolt11);

        if (null === $zapRequest || null === $amount || !$zapRequest->getKind()->is(EventKind::ZAP_REQUEST)) {
            return null;
        }

        return self::requestedAmountMatches($zapRequest, $amount) ? new self($event, $zapRequest, $amount) : null;
    }

    public function getReceipt(): Event
    {
        return $this->receipt;
    }

    public function getZapRequest(): Event
    {
        return $this->zapRequest;
    }

    #[Override]
    public function getSenderPubkey(): PublicKey
    {
        return $this->zapRequest->getPubkey();
    }

    #[Override]
    public function getRecipientPubkey(): ?PublicKey
    {
        return $this->receipt->getTags()->getSolePubkeyByType(TagType::pubkey());
    }

    #[Override]
    public function getAmount(): ZapAmount
    {
        return $this->amount;
    }

    #[Override]
    public function getMessage(): ?string
    {
        $message = (string) $this->zapRequest->getContent();

        return '' === $message ? null : $message;
    }

    private static function requestedAmountMatches(Event $zapRequest, ZapAmount $invoiceAmount): bool
    {
        return array_all(
            $zapRequest->getTags()->getValuesByType(TagType::amount()),
            static fn (string $requested): bool => DecimalIntegerParser::tryParse($requested) === $invoiceAmount->toMillisats(),
        );
    }
}
