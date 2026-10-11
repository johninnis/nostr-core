<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\NutzapProofCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NutzapProofCollectionTest extends TestCase
{
    public function testTheConstructorRejectsAnElementOfAnotherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NutzapProofCollection([EventId::tryFromHex(hash('sha256', 'seed')) ?? self::fail('invalid fixture event id')]);
    }

    public function testFromStringsKeepsTheProofsThatStateAnAmountInOrder(): void
    {
        $proofs = NutzapProofCollection::fromStrings([NutzapMother::proofTag(10)[1], 'not a proof', NutzapMother::proofTag(5)[1]]);

        $this->assertSame([10, 5], array_map(static fn (NutzapProof $proof): int => $proof->getAmount(), $proofs->toArray()));
    }
}
