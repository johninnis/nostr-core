<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\DTO\Nip98Request;
use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Application\Port\Nip98ReplayGuardInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class Nip98Validator implements Nip98ValidatorInterface
{
    private const int DEFAULT_TIMESTAMP_TOLERANCE = 60;

    private int $replayTtlSeconds;

    public function __construct(
        private SignatureServiceInterface $signatureService,
        private Nip98ReplayGuardInterface $replayGuard,
        private ClockInterface $clock,
        private int $timestampTolerance = self::DEFAULT_TIMESTAMP_TOLERANCE,
    ) {
        $this->replayTtlSeconds = 2 * $this->timestampTolerance;
    }

    #[Override]
    public function validate(Event $event, Nip98Request $request): PublicKey|Nip98ValidationFailure
    {
        $requestBodyHash = $request->getBodyHash();

        $failure = $this->validateKind($event)
            ?? $this->validateTimestamp($event)
            ?? $this->validateUrl($event, $request->getUrl())
            ?? $this->validateMethod($event, $request->getMethod())
            ?? $this->validatePayloadTagConsistency($event, $requestBodyHash)
            ?? $this->validateSignature($event)
            ?? (null !== $requestBodyHash ? $this->validatePayload($event, $requestBodyHash) : null);

        if (null !== $failure) {
            return $failure;
        }

        if (!$this->replayGuard->recordOnce($event->getId(), $this->replayTtlSeconds)) {
            return Nip98ValidationFailure::Replayed;
        }

        return $event->getPubkey();
    }

    #[Override]
    public function validateAuthHeader(string $authHeader, Nip98Request $request): PublicKey|Nip98ValidationFailure
    {
        $event = $this->parseAuthHeader($authHeader);

        if ($event instanceof Nip98ValidationFailure) {
            return $event;
        }

        return $this->validate($event, $request);
    }

    private function parseAuthHeader(string $authHeader): Event|Nip98ValidationFailure
    {
        $decoded = NostrAuthHeaderCodec::decode($authHeader);

        if ($decoded instanceof Event) {
            return $decoded;
        }

        return match ($decoded) {
            AuthHeaderDecodeFailure::TooLong => Nip98ValidationFailure::HeaderTooLong,
            AuthHeaderDecodeFailure::BadFormat => Nip98ValidationFailure::HeaderBadFormat,
            AuthHeaderDecodeFailure::BadBase64 => Nip98ValidationFailure::HeaderBadBase64,
            AuthHeaderDecodeFailure::BadJson => Nip98ValidationFailure::HeaderBadJson,
            AuthHeaderDecodeFailure::InvalidEvent => Nip98ValidationFailure::HeaderInvalidEvent,
        };
    }

    private function validateKind(Event $event): ?Nip98ValidationFailure
    {
        return $event->getKind()->is(EventKind::HTTP_AUTH)
            ? null
            : Nip98ValidationFailure::WrongKind;
    }

    private function validateSignature(Event $event): ?Nip98ValidationFailure
    {
        if (!$event->isSigned()) {
            return Nip98ValidationFailure::Unsigned;
        }

        return $event->verify($this->signatureService)
            ? null
            : Nip98ValidationFailure::BadSignature;
    }

    private function validateTimestamp(Event $event): ?Nip98ValidationFailure
    {
        $difference = $this->clock->now()->differenceInSeconds($event->getCreatedAt());

        return $difference > $this->timestampTolerance
            ? Nip98ValidationFailure::TimestampOutsideTolerance
            : null;
    }

    private function validateUrl(Event $event, string $requestUrl): ?Nip98ValidationFailure
    {
        $urlValues = $event->getTags()->getValuesByType(TagType::fromString(TagType::URL));

        if ([] === $urlValues) {
            return Nip98ValidationFailure::MissingUrlTag;
        }

        if (count($urlValues) > 1) {
            return Nip98ValidationFailure::MultipleUrlTags;
        }

        $eventUrl = $this->normaliseUrl($urlValues[0]);
        $expectedUrl = $this->normaliseUrl($requestUrl);

        if (null === $eventUrl || null === $expectedUrl) {
            return Nip98ValidationFailure::MalformedUrl;
        }

        return $eventUrl === $expectedUrl ? null : Nip98ValidationFailure::UrlMismatch;
    }

    private function validateMethod(Event $event, string $requestMethod): ?Nip98ValidationFailure
    {
        $methodValues = $event->getTags()->getValuesByType(TagType::method());

        if ([] === $methodValues) {
            return Nip98ValidationFailure::MissingMethodTag;
        }

        if (count($methodValues) > 1) {
            return Nip98ValidationFailure::MultipleMethodTags;
        }

        return strtoupper($methodValues[0]) === strtoupper($requestMethod)
            ? null
            : Nip98ValidationFailure::MethodMismatch;
    }

    private function validatePayloadTagConsistency(Event $event, ?string $requestBodyHash): ?Nip98ValidationFailure
    {
        $payloadValues = $event->getTags()->getValuesByType(TagType::payload());

        if (count($payloadValues) > 1) {
            return Nip98ValidationFailure::MultiplePayloadTags;
        }

        if (null === $requestBodyHash && [] !== $payloadValues) {
            return Nip98ValidationFailure::PayloadTagWithoutBodyHash;
        }

        return null;
    }

    private function validatePayload(Event $event, string $requestBodyHash): ?Nip98ValidationFailure
    {
        $payloadValues = $event->getTags()->getValuesByType(TagType::payload());

        if ([] === $payloadValues) {
            return Nip98ValidationFailure::MissingPayloadTag;
        }

        return hash_equals(strtolower($requestBodyHash), strtolower($payloadValues[0]))
            ? null
            : Nip98ValidationFailure::PayloadMismatch;
    }

    private function normaliseUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        if (false === $parsed) {
            return null;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? '/';
        $query = $parsed['query'] ?? null;

        if (('https' === $scheme && 443 === $port) || ('http' === $scheme && 80 === $port)) {
            $port = null;
        }

        $normalised = $scheme.'://'.$host;

        if (null !== $port) {
            $normalised .= ':'.$port;
        }

        $normalised .= $path;

        if (null !== $query) {
            $normalised .= '?'.$query;
        }

        return $normalised;
    }
}
