<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;

interface Nip42ValidatorInterface
{
    public function validate(Event $event, RelayChallenge $relayChallenge): ?Nip42ValidationFailure;
}
