<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

enum Nip98ValidationFailure: string
{
    case WrongKind = 'Event must be kind 27235';
    case Unsigned = 'Event must be signed';
    case BadSignature = 'Event signature is invalid';
    case TimestampOutsideTolerance = 'Event timestamp is outside tolerance';
    case MissingUrlTag = 'Event missing u tag';
    case MultipleUrlTags = 'Event must contain exactly one u tag';
    case MalformedUrl = 'Malformed URL';
    case UrlMismatch = 'URL in u tag does not match request URL';
    case MissingMethodTag = 'Event missing method tag';
    case MultipleMethodTags = 'Event must contain exactly one method tag';
    case MethodMismatch = 'Method in method tag does not match request method';
    case MultiplePayloadTags = 'Event must contain at most one payload tag';
    case PayloadTagWithoutBodyHash = 'Event contains payload tag but no request body hash was supplied for verification';
    case MissingPayloadTag = 'Event missing payload tag';
    case PayloadMismatch = 'Payload hash does not match request body';
    case Replayed = 'Auth event has already been used';
    case HeaderTooLong = 'Authorization header exceeds maximum length';
    case HeaderBadFormat = 'Invalid Authorization header format';
    case HeaderBadBase64 = 'Invalid base64 in Authorization header';
    case HeaderBadJson = 'Invalid JSON in Authorization header';
    case HeaderInvalidEvent = 'Invalid event in Authorization header';
}
