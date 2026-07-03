<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\PaymentReceiptInterface;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;

final class PaymentReceiptParser
{
    private function __construct()
    {
    }

    public static function tryFromEvent(Event $event): ?PaymentReceiptInterface
    {
        return ZapReceipt::tryFromEvent($event) ?? Nutzap::tryFromEvent($event);
    }
}
