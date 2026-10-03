<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\GiftWrapUnwrapFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;

interface GiftWrapServiceInterface
{
    public function wrapForRecipient(
        Rumour $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
    ): Event;

    public function wrapForChatRoom(
        Rumour $rumour,
        PrivateKey $senderPrivateKey,
    ): EventCollection;

    public function unwrap(
        Event $giftWrap,
        PrivateKey $recipientPrivateKey,
    ): Rumour|GiftWrapUnwrapFailure;
}
