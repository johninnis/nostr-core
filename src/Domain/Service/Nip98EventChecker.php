<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Sha256Hash;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use Override;

final readonly class Nip98EventChecker implements Nip98EventCheckerInterface
{
    private const int DEFAULT_TIMESTAMP_TOLERANCE = 60;

    public function __construct(
        private SignatureServiceInterface $signatureService,
        private int $timestampTolerance = self::DEFAULT_TIMESTAMP_TOLERANCE,
    ) {
        if ($timestampTolerance < 0) {
            throw new InvalidArgumentException("NIP-98 timestamp tolerance must be a non-negative number of seconds, got {$timestampTolerance}");
        }
    }

    #[Override]
    public function check(Event $event, Nip98Request $request, Timestamp $at): ?Nip98ValidationFailure
    {
        return $this->validateKind($event)
            ?? $this->validateTimestamp($event, $at)
            ?? $this->validateExpiration($event, $at)
            ?? $this->validateUrl($event, $request->getUrl())
            ?? $this->validateMethod($event, $request->getMethod())
            ?? $this->validatePayload($event, $request->getBodyHash())
            ?? $this->validateSignature($event);
    }

    #[Override]
    public function getReplayWindowSeconds(): int
    {
        return 2 * $this->timestampTolerance + 1;
    }

    private function validateKind(Event $event): ?Nip98ValidationFailure
    {
        return $event->getKind()->is(EventKind::HTTP_AUTH)
            ? null
            : Nip98ValidationFailure::WrongKind;
    }

    private function validateSignature(Event $event): ?Nip98ValidationFailure
    {
        return $event->verify($this->signatureService)
            ? null
            : Nip98ValidationFailure::BadSignature;
    }

    private function validateTimestamp(Event $event, Timestamp $at): ?Nip98ValidationFailure
    {
        return $event->getCreatedAt()->isWithinSecondsOf($at, $this->timestampTolerance)
            ? null
            : Nip98ValidationFailure::TimestampOutsideTolerance;
    }

    private function validateExpiration(Event $event, Timestamp $at): ?Nip98ValidationFailure
    {
        return $event->isExpiredAt($at) ? Nip98ValidationFailure::Expired : null;
    }

    private function validateUrl(Event $event, HttpUrl $requestUrl): ?Nip98ValidationFailure
    {
        $claim = $event->getTags()->getSoleValueByType(TagType::fromString(TagType::URL));
        $claimed = $claim->getValue();

        if (null === $claimed) {
            return SoleTagValueState::Absent === $claim->getState()
                ? Nip98ValidationFailure::MissingUrlTag
                : Nip98ValidationFailure::DisagreeingUrlTags;
        }

        $claimedUrl = HttpUrl::tryFromString($claimed);

        if (null === $claimedUrl) {
            return Nip98ValidationFailure::MalformedUrl;
        }

        return $claimedUrl->equals($requestUrl) ? null : Nip98ValidationFailure::UrlMismatch;
    }

    private function validateMethod(Event $event, string $requestMethod): ?Nip98ValidationFailure
    {
        $claim = $event->getTags()->getSoleValueByType(TagType::method());
        $claimed = $claim->getValue();

        if (null === $claimed) {
            return SoleTagValueState::Absent === $claim->getState()
                ? Nip98ValidationFailure::MissingMethodTag
                : Nip98ValidationFailure::DisagreeingMethodTags;
        }

        return $claimed === $requestMethod
            ? null
            : Nip98ValidationFailure::MethodMismatch;
    }

    private function validatePayload(Event $event, ?Sha256Hash $requestBodyHash): ?Nip98ValidationFailure
    {
        $claim = $event->getTags()->getSoleValueByType(TagType::payload());
        $claimed = $claim->getValue();

        return match (true) {
            SoleTagValueState::Disagreeing === $claim->getState() => Nip98ValidationFailure::DisagreeingPayloadTags,
            null === $requestBodyHash => null === $claimed ? null : Nip98ValidationFailure::PayloadTagWithoutBodyHash,
            null === $claimed => Nip98ValidationFailure::MissingPayloadTag,
            default => hash_equals((string) $requestBodyHash, $claimed) ? null : Nip98ValidationFailure::PayloadMismatch,
        };
    }
}
