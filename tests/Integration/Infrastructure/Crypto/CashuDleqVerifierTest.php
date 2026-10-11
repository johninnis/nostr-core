<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Innis\Nostr\Core\Infrastructure\Crypto\CashuDleqVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CashuDleqVerifierTest extends TestCase
{
    private const string GENERATOR = '0279be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';
    private const string GENERATOR_UNCOMPRESSED = '0479be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8';
    private const string CURVE_ORDER = 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';
    private const string E = 'b31e58ac6527f34975ffab13e70a48b6d2b0d35abc4b03f0151f09ee1a9763d4';
    private const string S = '8fbae004c59e754d71df67e392b6ae4e29293113ddc2ec86592a0431d16306d8';
    private const string R = 'a6d13fcd7a18442e6076f5e1e7c887ad5de40a019824bdfa9fe740d302e8d861';
    private const array NUT12_PROOF = [
        'amount' => 1,
        'id' => '00882760bfa2eb41',
        'secret' => 'daf4dd00a2b68a0858a80450f52c8a7d2ccf87d375e43e216e0c571f089f63e9',
        'C' => '024369d2d22a80ecf78f3937da9d5f30c1b9f74f0c32684d583cca0fa6a61cdcfc',
        'dleq' => ['e' => self::E, 's' => self::S, 'r' => self::R],
    ];

    public function testAProofCarryingTheDleqNut12GivesIsValid(): void
    {
        $this->assertTrue(new CashuDleqVerifier()->isValid(self::proof(self::NUT12_PROOF), self::GENERATOR));
    }

    public function testAMintKeyWrittenInUpperCaseIsValid(): void
    {
        $this->assertTrue(new CashuDleqVerifier()->isValid(self::proof(self::NUT12_PROOF), strtoupper(self::GENERATOR)));
    }

    #[DataProvider('proofFieldsANeededOneIsMissingFrom')]
    public function testAProofMissingAFieldTheCheckNeedsIsInvalid(string $field): void
    {
        $fields = self::NUT12_PROOF;
        unset($fields[$field]);

        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof($fields), self::GENERATOR));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function proofFieldsANeededOneIsMissingFrom(): iterable
    {
        yield 'no secret' => ['secret'];
        yield 'no unblinded signature' => ['C'];
        yield 'no dleq' => ['dleq'];
    }

    #[DataProvider('dleqFields')]
    public function testADleqMissingAFieldIsInvalid(string $field): void
    {
        $fields = self::NUT12_PROOF;
        unset($fields['dleq'][$field]);

        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof($fields), self::GENERATOR));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dleqFields(): iterable
    {
        yield 'no e' => ['e'];
        yield 'no s' => ['s'];
        yield 'no r' => ['r'];
    }

    #[DataProvider('scalarsOutsideTheCurveOrder')]
    public function testADleqScalarOutsideOneToTheCurveOrderIsInvalid(string $field, string $scalar): void
    {
        $fields = self::NUT12_PROOF;
        $fields['dleq'][$field] = $scalar;

        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof($fields), self::GENERATOR));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scalarsOutsideTheCurveOrder(): iterable
    {
        yield 'an e of zero' => ['e', str_repeat('0', 64)];
        yield 'an e equal to the curve order' => ['e', self::CURVE_ORDER];
        yield 'an r equal to the curve order' => ['r', self::CURVE_ORDER];
        yield 'an s of all ones' => ['s', str_repeat('f', 64)];
    }

    #[DataProvider('malformedScalars')]
    public function testADleqScalarThatIsNot32BytesOfHexIsInvalid(string $scalar): void
    {
        $fields = self::NUT12_PROOF;
        $fields['dleq']['s'] = $scalar;

        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof($fields), self::GENERATOR));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedScalars(): iterable
    {
        yield 'empty' => [''];
        yield '33 bytes' => [self::S.'00'];
        yield 'followed by a newline' => [self::S."\n"];
        yield 'with a 0x prefix' => ['0x'.substr(self::S, 2)];
        yield 'with a sign' => ['+'.substr(self::S, 1)];
    }

    #[DataProvider('mintKeysThatAreNoCompressedPoint')]
    public function testAMintKeyThatIsNoCompressedPointIsInvalid(string $mintKey): void
    {
        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof(self::NUT12_PROOF), $mintKey));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mintKeysThatAreNoCompressedPoint(): iterable
    {
        yield 'empty' => [''];
        yield 'written uncompressed' => [self::GENERATOR_UNCOMPRESSED];
        yield 'with an 04 prefix and 32 bytes' => ['04'.substr(self::GENERATOR, 2)];
        yield 'a bare x-coordinate' => [substr(self::GENERATOR, 2)];
        yield 'an x-coordinate at the field prime' => ['02fffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f'];
        yield 'followed by a newline' => [self::GENERATOR."\n"];
    }

    public function testAMintKeyOfTheOtherParityIsInvalid(): void
    {
        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof(self::NUT12_PROOF), '03'.substr(self::GENERATOR, 2)));
    }

    public function testAnSThatLeavesR1AtInfinityIsInvalid(): void
    {
        $fields = self::NUT12_PROOF;
        $fields['dleq']['s'] = self::E;

        $this->assertFalse(new CashuDleqVerifier()->isValid(self::proof($fields), self::GENERATOR));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function proof(array $fields): NutzapProof
    {
        return NutzapProof::tryFromJson(json_encode($fields, JSON_THROW_ON_ERROR)) ?? self::fail('Expected a proof');
    }
}
