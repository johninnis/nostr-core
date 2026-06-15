<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\AuthHeaderDecodeError;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use JsonException;

final class NostrAuthHeaderCodec
{
    public const string HEADER_PREFIX = 'Nostr ';
    public const int MAX_HEADER_LENGTH = 4096;
    private const int JSON_MAX_DEPTH = 16;

    public static function decode(string $authHeader): Event|AuthHeaderDecodeError
    {
        if (strlen($authHeader) > self::MAX_HEADER_LENGTH) {
            return AuthHeaderDecodeError::TooLong;
        }

        if (!str_starts_with($authHeader, self::HEADER_PREFIX)) {
            return AuthHeaderDecodeError::BadFormat;
        }

        $json = base64_decode(substr($authHeader, strlen(self::HEADER_PREFIX)), true);
        if (false === $json) {
            return AuthHeaderDecodeError::BadBase64;
        }

        try {
            $data = json_decode($json, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return AuthHeaderDecodeError::BadJson;
        }

        if (!is_array($data)) {
            return AuthHeaderDecodeError::BadJson;
        }

        try {
            return Event::fromArray($data);
        } catch (InvalidEventException) {
            return AuthHeaderDecodeError::InvalidEvent;
        }
    }

    public static function encode(Event $event): string
    {
        return self::HEADER_PREFIX.base64_encode($event->toJson());
    }
}
