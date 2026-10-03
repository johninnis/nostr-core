<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Application\Port\Nip98ReplayGuardInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\Service\Nip98EventCheckerInterface;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Override;

final readonly class Nip98Validator implements Nip98ValidatorInterface
{
    public function __construct(
        private Nip98EventCheckerInterface $checker,
        private Nip98ReplayGuardInterface $replayGuard,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function validate(Event $event, Nip98Request $request): PublicKey|Nip98ValidationFailure
    {
        $failure = $this->checker->check($event, $request, $this->clock->now());

        if (null !== $failure) {
            return $failure;
        }

        if (!$this->replayGuard->recordOnce($event->getId(), $this->checker->getReplayWindowSeconds())) {
            return Nip98ValidationFailure::Replayed;
        }

        return $event->getPubkey();
    }

    #[Override]
    public function validateAuthHeader(string $authHeader, Nip98Request $request): PublicKey|Nip98ValidationFailure|AuthHeaderDecodeFailure
    {
        $event = NostrAuthHeaderCodec::decode($authHeader);

        if ($event instanceof AuthHeaderDecodeFailure) {
            return $event;
        }

        return $this->validate($event, $request);
    }
}
