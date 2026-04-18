<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use PHPUnit\Framework\TestCase;

final class NcryptsecTest extends TestCase
{
    private const SPEC_VECTOR_NCRYPTSEC = 'ncryptsec1qgg9947rlpvqu76pj5ecreduf9jxhselq2nae2kghhvd5g7dgjtcxfqtd67p9m0w57lspw8gsq6yphnm8623nsl8xn9j4jdzz84zm3frztj3z7s35vpzmqf6ksu8r89qk5z2zxfmu5gv8th8wclt0h4p';

    public function testFromStringAcceptsValidNcryptsec(): void
    {
        $this->assertNotNull(Ncryptsec::fromString(self::SPEC_VECTOR_NCRYPTSEC));
    }

    public function testFromStringRejectsWrongHrpNsec(): void
    {
        $this->assertNull(Ncryptsec::fromString('nsec1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqq2kdzv5'));
    }

    public function testFromStringRejectsWrongHrpNpub(): void
    {
        $this->assertNull(Ncryptsec::fromString('npub1qqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqqwvfd7z'));
    }

    public function testFromStringRejectsInvalidChecksum(): void
    {
        $tampered = substr(self::SPEC_VECTOR_NCRYPTSEC, 0, -6).'xxxxxx';

        $this->assertNull(Ncryptsec::fromString($tampered));
    }

    public function testFromStringRejectsGarbage(): void
    {
        $this->assertNull(Ncryptsec::fromString('not-a-bech32-string'));
    }

    public function testToStringRoundTrips(): void
    {
        $ncryptsec = Ncryptsec::fromString(self::SPEC_VECTOR_NCRYPTSEC);

        $this->assertNotNull($ncryptsec);
        $this->assertSame(self::SPEC_VECTOR_NCRYPTSEC, (string) $ncryptsec);
    }
}
