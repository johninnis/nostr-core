<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

interface Nip42EventCheckerInterface
{
    public function check(Event $event, RelayChallenge $relayChallenge, Timestamp $at): ?Nip42ValidationFailure;
}
