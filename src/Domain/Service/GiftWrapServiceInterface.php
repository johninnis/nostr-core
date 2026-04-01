<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

interface GiftWrapServiceInterface
{
    public function wrapForRecipient(
        Event $rumour,
        PrivateKey $senderPrivateKey,
        PublicKey $recipientPublicKey,
        ?KeyPair $ephemeralKeyPair = null,
        ?Timestamp $sealTimestamp = null,
        ?Timestamp $wrapTimestamp = null,
    ): Event;

    public function unwrap(
        Event $giftWrap,
        PrivateKey $recipientPrivateKey,
    ): Event;
}
