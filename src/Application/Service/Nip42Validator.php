<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\Service\Nip42EventCheckerInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Override;

final readonly class Nip42Validator implements Nip42ValidatorInterface
{
    public function __construct(
        private Nip42EventCheckerInterface $checker,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function validate(Event $event, RelayChallenge $relayChallenge): ?Nip42ValidationFailure
    {
        return $this->checker->check($event, $relayChallenge, $this->clock->now());
    }
}
