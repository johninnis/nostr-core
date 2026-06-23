<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use JsonException;

final class NostrAuthHeaderCodec
{
    public const string HEADER_PREFIX = 'Nostr ';
    public const int MAX_HEADER_LENGTH = 4096;
    private const int JSON_MAX_DEPTH = 16;

    private function __construct()
    {
    }

    public static function decode(string $authHeader): Event|AuthHeaderDecodeFailure
    {
        if (strlen($authHeader) > self::MAX_HEADER_LENGTH) {
            return AuthHeaderDecodeFailure::TooLong;
        }

        if (!str_starts_with($authHeader, self::HEADER_PREFIX)) {
            return AuthHeaderDecodeFailure::BadFormat;
        }

        $json = base64_decode(substr($authHeader, strlen(self::HEADER_PREFIX)), true);
        if (false === $json) {
            return AuthHeaderDecodeFailure::BadBase64;
        }

        try {
            $data = json_decode($json, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return AuthHeaderDecodeFailure::BadJson;
        }

        if (!is_array($data)) {
            return AuthHeaderDecodeFailure::BadJson;
        }

        $event = Event::fromArray($data);

        return $event ?? AuthHeaderDecodeFailure::InvalidEvent;
    }

    public static function encode(Event $event): string
    {
        return self::HEADER_PREFIX.base64_encode($event->toJson());
    }
}
