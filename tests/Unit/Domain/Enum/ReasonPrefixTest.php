<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use PHPUnit\Framework\TestCase;

final class ReasonPrefixTest extends TestCase
{
    public function testFormatJoinsThePrefixAndTheDetailWithAColonAndASpace(): void
    {
        $this->assertSame('rate-limited: slow down there chief', ReasonPrefix::RateLimited->format('slow down there chief'));
    }

    public function testTryFromMessageReadsAKnownPrefix(): void
    {
        $this->assertSame(ReasonPrefix::Blocked, ReasonPrefix::tryFromMessage('blocked: you are banned from posting here'));
    }

    public function testTryFromMessageReadsTheNip42Prefix(): void
    {
        $this->assertSame(ReasonPrefix::AuthRequired, ReasonPrefix::tryFromMessage('auth-required: we only accept events from registered users'));
    }

    public function testTryFromMessageRoundTripsWhatFormatWrote(): void
    {
        $this->assertSame(ReasonPrefix::Invalid, ReasonPrefix::tryFromMessage(ReasonPrefix::Invalid->format('bad signature')));
    }

    public function testTryFromMessageIsNullForAnUnknownPrefix(): void
    {
        $this->assertNull(ReasonPrefix::tryFromMessage('teapot: short and stout'));
    }

    public function testTryFromMessageIsNullWithoutASeparator(): void
    {
        $this->assertNull(ReasonPrefix::tryFromMessage('blocked'));
    }

    public function testTryFromMessageIsNullForAnEmptyMessage(): void
    {
        $this->assertNull(ReasonPrefix::tryFromMessage(''));
    }

    public function testTryFromMessageOnlyReadsUpToTheFirstColon(): void
    {
        $this->assertSame(ReasonPrefix::Error, ReasonPrefix::tryFromMessage('error: could not connect: timeout'));
    }
}
