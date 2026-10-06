<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Generator;
use Innis\Nostr\Core\Domain\Service\ExpirationDerivation;
use PHPUnit\Framework\TestCase;

final class ExpirationDerivationTest extends TestCase
{
    public function testTheEarliestStatedValueThatParsesWins(): void
    {
        $this->assertSame(100, ExpirationDerivation::earliestStated(['soon', '9999999999', '100', '0100'])?->toInt());
    }

    public function testNullWhenNothingParses(): void
    {
        $this->assertNull(ExpirationDerivation::earliestStated(['soon', '-1', '']));
    }

    public function testReadsAnyIterableOfStrings(): void
    {
        $stated = static function (): Generator {
            yield '100';
            yield '50';
        };

        $this->assertSame(50, ExpirationDerivation::earliestStated($stated())?->toInt());
    }
}
