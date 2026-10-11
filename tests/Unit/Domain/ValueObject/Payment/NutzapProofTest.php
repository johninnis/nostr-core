<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NutzapProofTest extends TestCase
{
    public function testReadsTheAmountTheProofStates(): void
    {
        $this->assertSame(21, self::proof(NutzapMother::proofTag(21)[1])->getAmount());
    }

    public function testReadsANegativeAmountAsStatedForTheNutzapToRefuse(): void
    {
        $this->assertSame(-5, self::proof(NutzapMother::proofTag(-5)[1])->getAmount());
    }

    public function testReadsTheLockKeyFromTheSecret(): void
    {
        $this->assertSame(NutzapMother::P2PK_KEY, self::proof(NutzapMother::proofTag(21)[1])->getLockKey()?->toHex());
    }

    public function testCarriesTheKeysetIdAsWritten(): void
    {
        $this->assertSame(NutzapMother::KEYSET_ID, self::proof(NutzapMother::proofTag(21)[1])->getKeysetId());
    }

    public function testCarriesTheSecretAsWritten(): void
    {
        $this->assertSame(NutzapMother::secret('02'.NutzapMother::P2PK_KEY), self::proof(NutzapMother::proofTag(21)[1])->getSecret());
    }

    public function testCarriesTheUnblindedSignatureAsWritten(): void
    {
        $this->assertSame(NutzapMother::UNBLINDED_SIGNATURE, self::proof(NutzapMother::proofTag(21)[1])->getUnblindedSignature());
    }

    public function testCarriesTheDleqChallenge(): void
    {
        $this->assertSame(NutzapMother::DLEQ_E, self::proof(NutzapMother::proofTag(21)[1])->getDleq()?->getE());
    }

    public function testCarriesTheDleqResponse(): void
    {
        $this->assertSame(NutzapMother::DLEQ_S, self::proof(NutzapMother::proofTag(21)[1])->getDleq()?->getS());
    }

    public function testCarriesTheDleqBlindingFactor(): void
    {
        $this->assertSame(NutzapMother::DLEQ_R, self::proof(NutzapMother::proofTag(21)[1])->getDleq()?->getR());
    }

    public function testAProofWithoutADleqCarriesNone(): void
    {
        $this->assertNull(self::proof('{"amount":21}')->getDleq());
    }

    #[DataProvider('dleqsThatAreNoObject')]
    public function testADleqThatIsNoJsonObjectIsAbsent(string $json): void
    {
        $this->assertNull(self::proof($json)->getDleq());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dleqsThatAreNoObject(): iterable
    {
        yield 'a string' => ['{"amount":21,"dleq":"e"}'];
        yield 'a JSON array' => ['{"amount":21,"dleq":["e","s","r"]}'];
        yield 'null' => ['{"amount":21,"dleq":null}'];
    }

    public function testAnEmptyDleqObjectIsADleqStatingNothing(): void
    {
        $this->assertNull(self::proof('{"amount":21,"dleq":{}}')->getDleq()?->getE());
    }

    public function testADleqWithoutABlindingFactorKeepsWhatItHas(): void
    {
        $this->assertSame('e', self::proof('{"amount":21,"dleq":{"e":"e","s":"s"}}')->getDleq()?->getE());
    }

    public function testADleqWithoutABlindingFactorHasNone(): void
    {
        $this->assertNull(self::proof('{"amount":21,"dleq":{"e":"e","s":"s"}}')->getDleq()?->getR());
    }

    public function testADleqValueThatIsNotAStringIsAbsent(): void
    {
        $this->assertNull(self::proof('{"amount":21,"dleq":{"e":1,"s":"s","r":"r"}}')->getDleq()?->getE());
    }

    #[DataProvider('proofsMissingTheirKeysetId')]
    public function testAKeysetIdThatIsMissingOrNotAStringIsAbsent(string $json): void
    {
        $this->assertNull(self::proof($json)->getKeysetId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function proofsMissingTheirKeysetId(): iterable
    {
        yield 'missing' => ['{"amount":21}'];
        yield 'a number' => ['{"amount":21,"id":7}'];
    }

    #[DataProvider('proofsMissingTheirUnblindedSignature')]
    public function testAnUnblindedSignatureThatIsMissingOrNotAStringIsAbsent(string $json): void
    {
        $this->assertNull(self::proof($json)->getUnblindedSignature());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function proofsMissingTheirUnblindedSignature(): iterable
    {
        yield 'missing' => ['{"amount":21}'];
        yield 'an object' => ['{"amount":21,"C":{}}'];
    }

    public function testASecretThatIsNotAStringIsAbsent(): void
    {
        $this->assertNull(self::proof('{"amount":21,"secret":["P2PK",{"nonce":"n","data":"02'.NutzapMother::P2PK_KEY.'"}]}')->getSecret());
    }

    #[DataProvider('proofsLockedToNoKey')]
    public function testAProofWhoseSecretLocksToNoKeyHasNoLockKey(string $json): void
    {
        $this->assertNull(self::proof($json)->getLockKey());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function proofsLockedToNoKey(): iterable
    {
        yield 'no secret' => ['{"amount":21}'];
        yield 'a secret that is not a string' => ['{"amount":21,"secret":["P2PK",{"nonce":"n","data":"02'.NutzapMother::P2PK_KEY.'"}]}'];
        yield 'a secret that is no P2PK secret' => ['{"amount":21,"secret":"407915bc212be61a77e3e6d2aeb4c727980bda51cd06a6afc29e2861768a7837"}'];
    }

    public function testIsLockedToTheKeyItsSecretNames(): void
    {
        $this->assertTrue(self::proof(NutzapMother::proofTag(21)[1])->isLockedTo(self::key(NutzapMother::P2PK_KEY)));
    }

    public function testIsNotLockedToAnotherKey(): void
    {
        $this->assertFalse(self::proof(NutzapMother::proofTag(21)[1])->isLockedTo(self::key(NutzapMother::OTHER_P2PK_KEY)));
    }

    public function testAProofWithNoLockKeyIsLockedToNoKey(): void
    {
        $this->assertFalse(self::proof('{"amount":21}')->isLockedTo(self::key(NutzapMother::P2PK_KEY)));
    }

    #[DataProvider('proofsStatingNoAmount')]
    public function testAProofThatStatesNoAmountIsNoProof(string $json): void
    {
        $this->assertNull(NutzapProof::tryFromJson($json));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function proofsStatingNoAmount(): iterable
    {
        yield 'not JSON' => ['not json'];
        yield 'a JSON array' => ['[21]'];
        yield 'no amount' => ['{"secret":"s"}'];
        yield 'a string amount' => ['{"amount":"21"}'];
        yield 'a fractional amount' => ['{"amount":1.5}'];
    }

    private static function proof(string $json): NutzapProof
    {
        return NutzapProof::tryFromJson($json) ?? self::fail('invalid fixture proof');
    }

    private static function key(string $hex): PublicKey
    {
        return PublicKey::tryFromHex($hex) ?? self::fail('invalid fixture key');
    }
}
