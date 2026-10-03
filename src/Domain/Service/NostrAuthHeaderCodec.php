<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;

final class NostrAuthHeaderCodec
{
    public const string SCHEME = 'Nostr';

    public const string HEADER_PREFIX = self::SCHEME.' ';
    public const int MAX_HEADER_LENGTH = 4096;

    private function __construct()
    {
    }

    public static function decode(string $authHeader): Event|AuthHeaderDecodeFailure
    {
        if (strlen($authHeader) > self::MAX_HEADER_LENGTH) {
            return AuthHeaderDecodeFailure::TooLong;
        }

        // Deliberate: the scheme token is case-insensitive per RFC 9110, is public, and proves nothing on its own — see ADR-0073
        if (0 !== strncasecmp($authHeader, self::HEADER_PREFIX, strlen(self::HEADER_PREFIX))) {
            return AuthHeaderDecodeFailure::BadFormat;
        }

        $json = Base64Codec::tryDecodeCanonical(substr($authHeader, strlen(self::HEADER_PREFIX)));
        if (null === $json) {
            return AuthHeaderDecodeFailure::BadBase64;
        }

        $fields = JsonWireFormat::decodeObject($json);
        if (null === $fields) {
            return AuthHeaderDecodeFailure::BadJson;
        }

        $event = Event::tryFromArray($fields);

        return $event ?? AuthHeaderDecodeFailure::InvalidEvent;
    }

    public static function encode(Event $event): ?string
    {
        $authHeader = self::HEADER_PREFIX.base64_encode($event->toJson());

        return strlen($authHeader) > self::MAX_HEADER_LENGTH ? null : $authHeader;
    }
}
