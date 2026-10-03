<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip49WorkFactor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Nip49WorkFactorTest extends TestCase
{
    public function testEncryptsAtTheFloorOf16ByDefault(): void
    {
        $this->assertSame(16, new Nip49WorkFactor()->getEncryptLogN());
    }

    public function testAdmitsForDecryptionAtMostItsCeiling(): void
    {
        $workFactor = new Nip49WorkFactor(maxDecryptLogN: 18);

        $this->assertSame(
            [true, false, false],
            [$workFactor->admitsForDecryption(18), $workFactor->admitsForDecryption(19), $workFactor->admitsForDecryption(0)],
        );
    }

    public function testRefusesAnEncryptLogNBelowTheFloor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip49WorkFactor(encryptLogN: 15);
    }

    public function testRefusesAnEncryptLogNAboveTheLibraryCeiling(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip49WorkFactor(encryptLogN: 23);
    }

    public function testRefusesAMaxDecryptLogNAboveTheLibraryCeiling(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip49WorkFactor(maxDecryptLogN: 23);
    }

    public function testRefusesAMaxDecryptLogNBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip49WorkFactor(maxDecryptLogN: 0);
    }

    public function testRefusesAMaxDecryptLogNBelowTheEncryptLogN(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip49WorkFactor(encryptLogN: 18, maxDecryptLogN: 17);
    }

    public function testAdmitsForDecryptionWhatItEncryptsAt(): void
    {
        $this->assertTrue(new Nip49WorkFactor(encryptLogN: 18, maxDecryptLogN: 18)->admitsForDecryption(18));
    }
}
