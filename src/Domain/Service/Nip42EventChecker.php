<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use Override;

final readonly class Nip42EventChecker implements Nip42EventCheckerInterface
{
    private const int DEFAULT_TIMESTAMP_TOLERANCE = 600;

    public function __construct(private int $timestampTolerance = self::DEFAULT_TIMESTAMP_TOLERANCE)
    {
        if ($timestampTolerance < 0) {
            throw new InvalidArgumentException("NIP-42 timestamp tolerance must be a non-negative number of seconds, got {$timestampTolerance}");
        }
    }

    // Deliberate: the signature is not checked here — an AUTH event is validated as an event before it reaches NIP-42 — see ADR-0127
    #[Override]
    public function check(Event $event, RelayChallenge $relayChallenge, Timestamp $at): ?Nip42ValidationFailure
    {
        return $this->validateKind($event)
            ?? $this->validateTimestamp($event, $at)
            ?? $this->validateChallenge($event, $relayChallenge->getChallenge())
            ?? $this->validateRelay($event, $relayChallenge->getRelayUrl());
    }

    private function validateKind(Event $event): ?Nip42ValidationFailure
    {
        return $event->getKind()->is(EventKind::CLIENT_AUTH)
            ? null
            : Nip42ValidationFailure::WrongKind;
    }

    private function validateTimestamp(Event $event, Timestamp $at): ?Nip42ValidationFailure
    {
        return $event->getCreatedAt()->isWithinSecondsOf($at, $this->timestampTolerance)
            ? null
            : Nip42ValidationFailure::TimestampOutsideTolerance;
    }

    private function validateChallenge(Event $event, Challenge $challenge): ?Nip42ValidationFailure
    {
        $claimed = Challenge::tryFromString($event->getTags()->getSoleValueByType(TagType::fromString(TagType::CHALLENGE))->getValue());

        return true === $claimed?->equals($challenge)
            ? null
            : Nip42ValidationFailure::ChallengeMismatch;
    }

    private function validateRelay(Event $event, RelayUrl $relayUrl): ?Nip42ValidationFailure
    {
        $claimed = RelayUrl::tryFromString($event->getTags()->getSoleValueByType(TagType::fromString(TagType::RELAY))->getValue());

        return true === $claimed?->equals($relayUrl)
            ? null
            : Nip42ValidationFailure::RelayMismatch;
    }
}
