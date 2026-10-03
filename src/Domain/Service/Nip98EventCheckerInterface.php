<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

interface Nip98EventCheckerInterface
{
    public function check(Event $event, Nip98Request $request, Timestamp $at): ?Nip98ValidationFailure;

    public function getReplayWindowSeconds(): int;
}
