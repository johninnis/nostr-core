<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Closure;
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
        return self::decodeCredentials($authHeader, Base64Codec::tryDecodeCanonical(...));
    }

    // Deliberate: BUD-11 writes unpadded base64url, and the padded base64 every Blossom client wrote before it is still read — see ADR-0135
    public static function decodeBlossom(string $authHeader): Event|AuthHeaderDecodeFailure
    {
        return self::decodeCredentials(
            $authHeader,
            static fn (string $credentials): ?string => Base64Codec::tryDecodeCanonicalUnpaddedUrl($credentials) ?? Base64Codec::tryDecodeCanonical($credentials),
        );
    }

    public static function encode(Event $event): ?string
    {
        return self::withinMaxLength(self::HEADER_PREFIX.base64_encode($event->toJson()));
    }

    public static function encodeBlossom(Event $event): ?string
    {
        return self::withinMaxLength(self::HEADER_PREFIX.Base64Codec::encodeUnpaddedUrl($event->toJson()));
    }

    /**
     * @param Closure(string): ?string $decodeBase64
     */
    private static function decodeCredentials(string $authHeader, Closure $decodeBase64): Event|AuthHeaderDecodeFailure
    {
        if (strlen($authHeader) > self::MAX_HEADER_LENGTH) {
            return AuthHeaderDecodeFailure::TooLong;
        }

        // Deliberate: the scheme token is case-insensitive per RFC 9110, is public, and proves nothing on its own — see ADR-0073
        if (0 !== strncasecmp($authHeader, self::HEADER_PREFIX, strlen(self::HEADER_PREFIX))) {
            return AuthHeaderDecodeFailure::BadFormat;
        }

        $json = $decodeBase64(substr($authHeader, strlen(self::HEADER_PREFIX)));
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

    private static function withinMaxLength(string $authHeader): ?string
    {
        return strlen($authHeader) > self::MAX_HEADER_LENGTH ? null : $authHeader;
    }
}
