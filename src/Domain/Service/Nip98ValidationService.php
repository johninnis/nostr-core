<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Application\Port\Nip98ReplayGuardInterface;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\AuthHeaderDecodeError;
use Innis\Nostr\Core\Domain\Exception\Nip98ValidationException;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class Nip98ValidationService implements Nip98ValidationServiceInterface
{
    private const DEFAULT_TIMESTAMP_TOLERANCE = 60;

    private int $replayTtlSeconds;

    public function __construct(
        private SignatureServiceInterface $signatureService,
        private Nip98ReplayGuardInterface $replayGuard,
        private int $timestampTolerance = self::DEFAULT_TIMESTAMP_TOLERANCE,
    ) {
        $this->replayTtlSeconds = 2 * $this->timestampTolerance;
    }

    public function validate(Event $event, string $requestUrl, string $requestMethod, ?string $requestBodyHash = null): PublicKey
    {
        $this->validateKind($event);
        $this->validateTimestamp($event);
        $this->validateUrl($event, $requestUrl);
        $this->validateMethod($event, $requestMethod);
        $this->validatePayloadTagConsistency($event, $requestBodyHash);
        $this->validateSignature($event);

        if (null !== $requestBodyHash) {
            $this->validatePayload($event, $requestBodyHash);
        }

        if (!$this->replayGuard->recordOnce($event->getId(), $this->replayTtlSeconds)) {
            throw new Nip98ValidationException('Auth event has already been used');
        }

        return $event->getPubkey();
    }

    public function validateAuthHeader(string $authHeader, string $requestUrl, string $requestMethod, string $requestBody): PublicKey
    {
        $event = $this->parseAuthHeader($authHeader);
        $bodyHash = '' === $requestBody ? null : hash('sha256', $requestBody);

        return $this->validate($event, $requestUrl, $requestMethod, $bodyHash);
    }

    private function parseAuthHeader(string $authHeader): Event
    {
        $decoded = NostrAuthHeaderCodec::decode($authHeader);

        if ($decoded instanceof Event) {
            return $decoded;
        }

        $message = match ($decoded) {
            AuthHeaderDecodeError::TooLong => 'Authorization header exceeds maximum length',
            AuthHeaderDecodeError::BadFormat => 'Invalid Authorization header format',
            AuthHeaderDecodeError::BadBase64 => 'Invalid base64 in Authorization header',
            AuthHeaderDecodeError::BadJson => 'Invalid JSON in Authorization header',
            AuthHeaderDecodeError::InvalidEvent => 'Invalid event in Authorization header',
        };

        throw new Nip98ValidationException($message);
    }

    private function validateKind(Event $event): void
    {
        if (!$event->getKind()->is(EventKind::HTTP_AUTH)) {
            throw new Nip98ValidationException('Event must be kind 27235');
        }
    }

    private function validateSignature(Event $event): void
    {
        if (!$event->isSigned()) {
            throw new Nip98ValidationException('Event must be signed');
        }

        if (!$event->verify($this->signatureService)) {
            throw new Nip98ValidationException('Event signature is invalid');
        }
    }

    private function validateTimestamp(Event $event): void
    {
        $now = time();
        $eventTime = $event->getCreatedAt()->toInt();
        $diff = abs($now - $eventTime);

        if ($diff > $this->timestampTolerance) {
            throw new Nip98ValidationException('Event timestamp is outside tolerance');
        }
    }

    private function validateUrl(Event $event, string $requestUrl): void
    {
        $urlValues = $event->getTags()->getValuesByType(TagType::fromString('u'));

        if (empty($urlValues)) {
            throw new Nip98ValidationException('Event missing u tag');
        }

        if (count($urlValues) > 1) {
            throw new Nip98ValidationException('Event must contain exactly one u tag');
        }

        $eventUrl = $this->normaliseUrl($urlValues[0]);
        $expectedUrl = $this->normaliseUrl($requestUrl);

        if ($eventUrl !== $expectedUrl) {
            throw new Nip98ValidationException('URL in u tag does not match request URL');
        }
    }

    private function validateMethod(Event $event, string $requestMethod): void
    {
        $methodValues = $event->getTags()->getValuesByType(TagType::method());

        if (empty($methodValues)) {
            throw new Nip98ValidationException('Event missing method tag');
        }

        if (count($methodValues) > 1) {
            throw new Nip98ValidationException('Event must contain exactly one method tag');
        }

        if (strtoupper($methodValues[0]) !== strtoupper($requestMethod)) {
            throw new Nip98ValidationException('Method in method tag does not match request method');
        }
    }

    private function validatePayloadTagConsistency(Event $event, ?string $requestBodyHash): void
    {
        $payloadValues = $event->getTags()->getValuesByType(TagType::payload());

        if (count($payloadValues) > 1) {
            throw new Nip98ValidationException('Event must contain at most one payload tag');
        }

        if (null === $requestBodyHash && [] !== $payloadValues) {
            throw new Nip98ValidationException('Event contains payload tag but no request body hash was supplied for verification');
        }
    }

    private function validatePayload(Event $event, string $requestBodyHash): void
    {
        $payloadValues = $event->getTags()->getValuesByType(TagType::payload());

        if (empty($payloadValues)) {
            throw new Nip98ValidationException('Event missing payload tag');
        }

        if (!hash_equals(strtolower($requestBodyHash), strtolower($payloadValues[0]))) {
            throw new Nip98ValidationException('Payload hash does not match request body');
        }
    }

    private function normaliseUrl(string $url): string
    {
        $parsed = parse_url($url);

        if (false === $parsed) {
            throw new Nip98ValidationException('Malformed URL');
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
