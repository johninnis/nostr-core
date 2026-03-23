<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\PaymentReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;

final class PaymentReceiptParser
{
    public static function fromEvent(Event $event): ?PaymentReceipt
    {
        return ZapReceipt::fromEvent($event) ?? Nutzap::fromEvent($event);
    }
}
