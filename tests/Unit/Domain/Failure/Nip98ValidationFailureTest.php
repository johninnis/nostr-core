<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip98ValidationFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(Nip98ValidationFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{Nip98ValidationFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'wrong kind' => [Nip98ValidationFailure::WrongKind, 'wrong_kind'];
        yield 'unsigned' => [Nip98ValidationFailure::Unsigned, 'unsigned'];
        yield 'bad signature' => [Nip98ValidationFailure::BadSignature, 'bad_signature'];
        yield 'timestamp outside tolerance' => [Nip98ValidationFailure::TimestampOutsideTolerance, 'timestamp_outside_tolerance'];
        yield 'missing url tag' => [Nip98ValidationFailure::MissingUrlTag, 'missing_url_tag'];
        yield 'multiple url tags' => [Nip98ValidationFailure::MultipleUrlTags, 'multiple_url_tags'];
        yield 'malformed url' => [Nip98ValidationFailure::MalformedUrl, 'malformed_url'];
        yield 'url mismatch' => [Nip98ValidationFailure::UrlMismatch, 'url_mismatch'];
        yield 'missing method tag' => [Nip98ValidationFailure::MissingMethodTag, 'missing_method_tag'];
        yield 'multiple method tags' => [Nip98ValidationFailure::MultipleMethodTags, 'multiple_method_tags'];
        yield 'method mismatch' => [Nip98ValidationFailure::MethodMismatch, 'method_mismatch'];
        yield 'multiple payload tags' => [Nip98ValidationFailure::MultiplePayloadTags, 'multiple_payload_tags'];
        yield 'payload tag without body hash' => [Nip98ValidationFailure::PayloadTagWithoutBodyHash, 'payload_tag_without_body_hash'];
        yield 'missing payload tag' => [Nip98ValidationFailure::MissingPayloadTag, 'missing_payload_tag'];
        yield 'payload mismatch' => [Nip98ValidationFailure::PayloadMismatch, 'payload_mismatch'];
        yield 'replayed' => [Nip98ValidationFailure::Replayed, 'replayed'];
        yield 'header too long' => [Nip98ValidationFailure::HeaderTooLong, 'header_too_long'];
        yield 'header bad format' => [Nip98ValidationFailure::HeaderBadFormat, 'header_bad_format'];
        yield 'header bad base64' => [Nip98ValidationFailure::HeaderBadBase64, 'header_bad_base64'];
        yield 'header bad json' => [Nip98ValidationFailure::HeaderBadJson, 'header_bad_json'];
        yield 'header invalid event' => [Nip98ValidationFailure::HeaderInvalidEvent, 'header_invalid_event'];
    }

    public function testMessageIsAHumanReadableDescription(): void
    {
        $this->assertSame('Event must be kind 27235', Nip98ValidationFailure::WrongKind->message());
    }

    public function testEveryCaseSeparatesItsCodeFromItsMessage(): void
    {
        foreach (Nip98ValidationFailure::cases() as $failure) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $failure->value);
            $this->assertNotSame('', $failure->message());
            $this->assertNotSame($failure->value, $failure->message());
        }
    }
}
