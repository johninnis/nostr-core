<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

use Override;

enum Nip98ValidationFailure: string implements AuthHeaderFailureInterface
{
    case WrongKind = 'wrong_kind';
    case BadSignature = 'bad_signature';
    case TimestampOutsideTolerance = 'timestamp_outside_tolerance';
    case MissingUrlTag = 'missing_url_tag';
    case MultipleUrlTags = 'multiple_url_tags';
    case MalformedUrl = 'malformed_url';
    case UrlMismatch = 'url_mismatch';
    case MissingMethodTag = 'missing_method_tag';
    case MultipleMethodTags = 'multiple_method_tags';
    case MethodMismatch = 'method_mismatch';
    case MultiplePayloadTags = 'multiple_payload_tags';
    case PayloadTagWithoutBodyHash = 'payload_tag_without_body_hash';
    case MissingPayloadTag = 'missing_payload_tag';
    case PayloadMismatch = 'payload_mismatch';
    case Replayed = 'replayed';

    #[Override]
    public function message(): string
    {
        return match ($this) {
            self::WrongKind => 'Event must be kind 27235',
            self::BadSignature => 'Event signature is invalid',
            self::TimestampOutsideTolerance => 'Event timestamp is outside tolerance',
            self::MissingUrlTag => 'Event missing u tag',
            self::MultipleUrlTags => 'Event must contain exactly one u tag',
            self::MalformedUrl => 'Malformed URL',
            self::UrlMismatch => 'URL in u tag does not match request URL',
            self::MissingMethodTag => 'Event missing method tag',
            self::MultipleMethodTags => 'Event must contain exactly one method tag',
            self::MethodMismatch => 'Method in method tag does not match request method',
            self::MultiplePayloadTags => 'Event must contain at most one payload tag',
            self::PayloadTagWithoutBodyHash => 'Event contains payload tag but no request body hash was supplied for verification',
            self::MissingPayloadTag => 'Event missing payload tag',
            self::PayloadMismatch => 'Payload hash does not match request body',
            self::Replayed => 'Auth event has already been used',
        };
    }
}
