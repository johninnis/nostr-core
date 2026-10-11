<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\MintKeysetCollection;
use Innis\Nostr\Core\Domain\Failure\NutzapDleqFailure;
use Innis\Nostr\Core\Domain\Service\CashuDleqVerifierInterface;
use Innis\Nostr\Core\Domain\Service\NutzapDleqVerifier;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use PHPUnit\Framework\TestCase;

final class NutzapDleqVerifierTest extends TestCase
{
    private const string OTHER_KEYSET_ID = '00ffd48b8f5ecf80';
    private const string KEY_FOR_21 = '0279be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const string KEY_FOR_5 = '02c6047f9441ed7d6d3045406e95c07cd85c778e4b8cef3ca7abac09b95c709ee5';
    private const string OTHER_KEY_FOR_21 = '02f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9';

    public function testANutzapWhoseEveryProofCarriesAValidDleqVerifies(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5)]);

        $this->assertNull($this->verifier(true)->verify($nutzap, self::satKeysets()));
    }

    public function testANutzapWithoutAProofHasNoProof(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', NutzapMother::MINT], ['proof', 'not a proof']]);

        $this->assertSame(NutzapDleqFailure::NoProof, $this->verifier(true)->verify($nutzap, self::satKeysets()));
    }

    public function testAProofNamingAKeysetNotSuppliedIsInAnUnknownKeyset(): void
    {
        $keysets = self::keysets([self::keyset(self::OTHER_KEYSET_ID, 'sat', [21 => self::KEY_FOR_21])]);

        $this->assertSame(NutzapDleqFailure::UnknownKeyset, $this->verifier(true)->verify(NutzapMother::payingNutzap(), $keysets));
    }

    public function testAProofNamingNoKeysetIsInAnUnknownKeyset(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5, keysetId: null)]);

        $this->assertSame(NutzapDleqFailure::UnknownKeyset, $this->verifier(true)->verify($nutzap, self::satKeysets()));
    }

    public function testAKeysetIsFoundOnlyByItsIdAsWritten(): void
    {
        $keysets = self::keysets([self::keyset(strtoupper(NutzapMother::KEYSET_ID), 'sat', [21 => self::KEY_FOR_21])]);

        $this->assertSame(NutzapDleqFailure::UnknownKeyset, $this->verifier(true)->verify(NutzapMother::payingNutzap(), $keysets));
    }

    public function testAKeysetInAnotherUnitIsAUnitMismatch(): void
    {
        $keysets = self::keysets([self::keyset(NutzapMother::KEYSET_ID, 'usd', [21 => self::KEY_FOR_21])]);

        $this->assertSame(NutzapDleqFailure::UnitMismatch, $this->verifier(true)->verify(NutzapMother::payingNutzap(), $keysets));
    }

    public function testAUnitIsComparedExactlyAsWritten(): void
    {
        $keysets = self::keysets([self::keyset(NutzapMother::KEYSET_ID, 'SAT', [21 => self::KEY_FOR_21])]);

        $this->assertSame(NutzapDleqFailure::UnitMismatch, $this->verifier(true)->verify(NutzapMother::payingNutzap(), $keysets));
    }

    public function testANutzapInAUnitOtherThanSatVerifiesAgainstAKeysetInThatUnit(): void
    {
        $keysets = self::keysets([self::keyset(NutzapMother::KEYSET_ID, 'usd', [21 => self::KEY_FOR_21])]);

        $this->assertNull($this->verifier(true)->verify(NutzapMother::payingNutzap([['unit', 'usd']]), $keysets));
    }

    public function testAKeysetWithoutAKeyForAProofsAmountHasNoMintKey(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(8)]);

        $this->assertSame(NutzapDleqFailure::NoMintKey, $this->verifier(true)->verify($nutzap, self::satKeysets()));
    }

    public function testAProofWhoseDleqDoesNotVerifyIsDleqInvalid(): void
    {
        $this->assertSame(NutzapDleqFailure::DleqInvalid, $this->verifier(false)->verify(NutzapMother::payingNutzap(), self::satKeysets()));
    }

    public function testEachProofIsCheckedAgainstTheKeyForItsAmount(): void
    {
        $keysByAmount = [21 => self::KEY_FOR_21, 5 => self::KEY_FOR_5];
        $cashuDleqVerifier = $this->createStub(CashuDleqVerifierInterface::class);
        $cashuDleqVerifier->method('isValid')->willReturnCallback(
            static fn (NutzapProof $proof, string $mintKey): bool => ($keysByAmount[$proof->getAmount()] ?? null) === $mintKey,
        );

        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5)]);

        $this->assertNull(new NutzapDleqVerifier($cashuDleqVerifier)->verify($nutzap, self::satKeysets()));
    }

    public function testEachProofIsCheckedAgainstTheKeyOfItsOwnKeyset(): void
    {
        $keysById = [NutzapMother::KEYSET_ID => self::KEY_FOR_21, self::OTHER_KEYSET_ID => self::OTHER_KEY_FOR_21];
        $cashuDleqVerifier = $this->createStub(CashuDleqVerifierInterface::class);
        $cashuDleqVerifier->method('isValid')->willReturnCallback(
            static fn (NutzapProof $proof, string $mintKey): bool => ($keysById[$proof->getKeysetId() ?? ''] ?? null) === $mintKey,
        );
        $keysets = self::keysets([
            self::keyset(NutzapMother::KEYSET_ID, 'sat', [21 => self::KEY_FOR_21]),
            self::keyset(self::OTHER_KEYSET_ID, 'sat', [21 => self::OTHER_KEY_FOR_21]),
        ]);

        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(21, keysetId: self::OTHER_KEYSET_ID)]);

        $this->assertNull(new NutzapDleqVerifier($cashuDleqVerifier)->verify($nutzap, $keysets));
    }

    public function testEveryProofsKeysetIsFoundBeforeAnyUnitIsCompared(): void
    {
        $keysets = self::keysets([self::keyset(NutzapMother::KEYSET_ID, 'usd', [21 => self::KEY_FOR_21])]);
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5, keysetId: self::OTHER_KEYSET_ID)]);

        $this->assertSame(NutzapDleqFailure::UnknownKeyset, $this->verifier(true)->verify($nutzap, $keysets));
    }

    public function testEveryUnitIsComparedBeforeAnyMintKeyIsLookedUp(): void
    {
        $keysets = self::keysets([
            self::keyset(NutzapMother::KEYSET_ID, 'sat', []),
            self::keyset(self::OTHER_KEYSET_ID, 'usd', [5 => self::KEY_FOR_5]),
        ]);
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5, keysetId: self::OTHER_KEYSET_ID)]);

        $this->assertSame(NutzapDleqFailure::UnitMismatch, $this->verifier(true)->verify($nutzap, $keysets));
    }

    public function testEveryMintKeyIsLookedUpBeforeAnyDleqIsChecked(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(8)]);

        $this->assertSame(NutzapDleqFailure::NoMintKey, $this->verifier(false)->verify($nutzap, self::satKeysets()));
    }

    public function testNoDleqIsCheckedForANutzapThatFailsAnEarlierCheck(): void
    {
        $cashuDleqVerifier = $this->createMock(CashuDleqVerifierInterface::class);
        $cashuDleqVerifier->expects($this->never())->method('isValid');

        new NutzapDleqVerifier($cashuDleqVerifier)->verify(NutzapMother::payingNutzap([NutzapMother::proofTag(8)]), self::satKeysets());
    }

    private function verifier(bool $dleqIsValid): NutzapDleqVerifier
    {
        $cashuDleqVerifier = $this->createStub(CashuDleqVerifierInterface::class);
        $cashuDleqVerifier->method('isValid')->willReturn($dleqIsValid);

        return new NutzapDleqVerifier($cashuDleqVerifier);
    }

    private static function satKeysets(): MintKeysetCollection
    {
        return self::keysets([self::keyset(NutzapMother::KEYSET_ID, 'sat', [21 => self::KEY_FOR_21, 5 => self::KEY_FOR_5])]);
    }

    /**
     * @param array<int, string> $keys
     *
     * @return array{id: string, unit: string, keys: object}
     */
    private static function keyset(string $id, string $unit, array $keys): array
    {
        return ['id' => $id, 'unit' => $unit, 'keys' => (object) $keys];
    }

    /**
     * @param list<array{id: string, unit: string, keys: object}> $keysets
     */
    private static function keysets(array $keysets): MintKeysetCollection
    {
        return MintKeysetCollection::fromKeysResponse(['keysets' => $keysets]);
    }
}
