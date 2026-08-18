<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

use Override;

enum AuthHeaderDecodeFailure: string implements AuthHeaderFailureInterface
{
    case TooLong = 'header_too_long';
    case BadFormat = 'header_bad_format';
    case BadBase64 = 'header_bad_base64';
    case BadJson = 'header_bad_json';
    case InvalidEvent = 'header_invalid_event';

    #[Override]
    public function message(): string
    {
        return match ($this) {
            self::TooLong => 'Authorization header exceeds maximum length',
            self::BadFormat => 'Invalid Authorization header format',
            self::BadBase64 => 'Invalid base64 in Authorization header',
            self::BadJson => 'Invalid JSON in Authorization header',
            self::InvalidEvent => 'Invalid event in Authorization header',
        };
    }
}
