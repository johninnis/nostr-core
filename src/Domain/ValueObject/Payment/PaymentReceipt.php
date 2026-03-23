<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface PaymentReceipt
{
    public function getSenderPubkey(): ?PublicKey;

    public function getRecipientPubkey(): ?PublicKey;

    public function getAmount(): ?ZapAmount;

    public function getMessage(): ?string;

    public static function fromEvent(Event $event): ?self;
}
