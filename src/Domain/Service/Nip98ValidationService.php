<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\Nip98ValidationException;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class Nip98ValidationService
{
    private const DEFAULT_TIMESTAMP_TOLERANCE = 60;

    public function __construct(
        private int $timestampTolerance = self::DEFAULT_TIMESTAMP_TOLERANCE,
    ) {
    }

    public function validate(Event $event, string $requestUrl, string $requestMethod, ?string $requestBodyHash = null): PublicKey
    {
        $this->validateKind($event);
        $this->validateSignature($event);
        $this->validateTimestamp($event);
        $this->validateUrl($event, $requestUrl);
        $this->validateMethod($event, $requestMethod);
        $this->validatePayloadTagConsistency($event, $requestBodyHash);

        if (null !== $requestBodyHash) {
            $this->validatePayload($event, $requestBodyHash);
        }

        return $event->getPubkey();
    }

    private function validateKind(Event $event): void
    {
        if (!$event->getKind()->equals(EventKind::httpAuth())) {
            throw new Nip98ValidationException('Event must be kind 27235');
        }
    }

    private function validateSignature(Event $event): void
    {
        if (!$event->isSigned()) {
            throw new Nip98ValidationException('Event must be signed');
        }

        if (!$event->verify()) {
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

        if (strtoupper($methodValues[0]) !== strtoupper($requestMethod)) {
            throw new Nip98ValidationException('Method in method tag does not match request method');
        }
    }

    private function validatePayloadTagConsistency(Event $event, ?string $requestBodyHash): void
    {
        $hasPayloadTag = [] !== $event->getTags()->getValuesByType(TagType::payload());

        if (null === $requestBodyHash && $hasPayloadTag) {
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
            return $url;
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
