<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class Nip42Validator implements Nip42ValidatorInterface
{
    private const int DEFAULT_TIMESTAMP_TOLERANCE = 600;

    public function __construct(
        private ClockInterface $clock,
        private int $timestampTolerance = self::DEFAULT_TIMESTAMP_TOLERANCE,
    ) {
    }

    // Deliberate: the signature is not checked here — an AUTH event is validated as an event before it reaches NIP-42 — see ADR-0066
    #[Override]
    public function validate(Event $event, Challenge $challenge, RelayUrl $relayUrl): ?Nip42ValidationFailure
    {
        return $this->validateKind($event)
            ?? $this->validateTimestamp($event)
            ?? $this->validateChallenge($event, $challenge)
            ?? $this->validateRelay($event, $relayUrl);
    }

    private function validateKind(Event $event): ?Nip42ValidationFailure
    {
        return $event->getKind()->is(EventKind::CLIENT_AUTH)
            ? null
            : Nip42ValidationFailure::WrongKind;
    }

    private function validateChallenge(Event $event, Challenge $challenge): ?Nip42ValidationFailure
    {
        $claimedValues = $event->getTags()->getValuesByType(TagType::fromString(TagType::CHALLENGE));

        if (1 !== count($claimedValues)) {
            return Nip42ValidationFailure::ChallengeMismatch;
        }

        $claimed = Challenge::tryFromString($claimedValues[0]);

        return true === $claimed?->equals($challenge)
            ? null
            : Nip42ValidationFailure::ChallengeMismatch;
    }

    private function validateRelay(Event $event, RelayUrl $relayUrl): ?Nip42ValidationFailure
    {
        $claimedValues = $event->getTags()->getValuesByType(TagType::fromString(TagType::RELAY));

        if (1 !== count($claimedValues)) {
            return Nip42ValidationFailure::RelayMismatch;
        }

        $claimed = RelayUrl::tryFromString($claimedValues[0]);

        return true === $claimed?->equals($relayUrl)
            ? null
            : Nip42ValidationFailure::RelayMismatch;
    }

    private function validateTimestamp(Event $event): ?Nip42ValidationFailure
    {
        return $this->clock->now()->differenceInSeconds($event->getCreatedAt()) > $this->timestampTolerance
            ? Nip42ValidationFailure::TimestampOutsideTolerance
            : null;
    }
}
