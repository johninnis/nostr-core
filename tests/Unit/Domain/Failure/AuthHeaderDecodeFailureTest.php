<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Failure;

use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthHeaderDecodeFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(AuthHeaderDecodeFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{AuthHeaderDecodeFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'too long' => [AuthHeaderDecodeFailure::TooLong, 'header_too_long'];
        yield 'bad format' => [AuthHeaderDecodeFailure::BadFormat, 'header_bad_format'];
        yield 'bad base64' => [AuthHeaderDecodeFailure::BadBase64, 'header_bad_base64'];
        yield 'bad json' => [AuthHeaderDecodeFailure::BadJson, 'header_bad_json'];
        yield 'invalid event' => [AuthHeaderDecodeFailure::InvalidEvent, 'header_invalid_event'];
    }

    #[DataProvider('caseMessages')]
    public function testMessageIsAHumanReadableDescription(AuthHeaderDecodeFailure $failure, string $message): void
    {
        $this->assertSame($message, $failure->message());
    }

    /**
     * @return iterable<string, array{AuthHeaderDecodeFailure, string}>
     */
    public static function caseMessages(): iterable
    {
        yield 'too long' => [AuthHeaderDecodeFailure::TooLong, 'Authorization header exceeds maximum length'];
        yield 'bad format' => [AuthHeaderDecodeFailure::BadFormat, 'Invalid Authorization header format'];
        yield 'bad base64' => [AuthHeaderDecodeFailure::BadBase64, 'Invalid base64 in Authorization header'];
        yield 'bad json' => [AuthHeaderDecodeFailure::BadJson, 'Invalid JSON in Authorization header'];
        yield 'invalid event' => [AuthHeaderDecodeFailure::InvalidEvent, 'Invalid event in Authorization header'];
    }
}
