<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface GiftWrapServiceInterface
{
    public function wrapForRecipient(
        Event $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
    ): Event;

    public function unwrap(
        Event $giftWrap,
        PrivateKey $recipientPrivateKey,
    ): Event;
}
