<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Collection\MintKeysetCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\NutzapDleqVerifier;
use Innis\Nostr\Core\Domain\Service\NutzapVerifier;
use Innis\Nostr\Core\Domain\ValueObject\Payment\Nutzap;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapInfo;
use Innis\Nostr\Core\Domain\ValueObject\Payment\NutzapProof;
use Innis\Nostr\Core\Infrastructure\Crypto\CashuDleqVerifier;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Math;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CashuDleqVectorComplianceTest extends TestCase
{
    private const int HASH_TO_CURVE_VECTOR_COUNT = 3;
    private const int PROOF_VECTOR_COUNT = 19;
    private const int LOCKED_PROOF_COUNT = 2;

    public function testTheHashToCurveVectorsAreFullyLoaded(): void
    {
        $this->assertCount(self::HASH_TO_CURVE_VECTOR_COUNT, self::hashToCurveVectors());
    }

    public function testTheProofVectorsAreFullyLoaded(): void
    {
        $this->assertCount(self::PROOF_VECTOR_COUNT, self::proofVectors());
    }

    #[DataProvider('hashToCurveCases')]
    public function testHashesEachMessageToItsPointLikeNut00(string $messageHex, string $expectedPoint): void
    {
        $expected = Secp256k1Math::decompressPoint(self::bytes($expectedPoint)) ?? self::fail('The vector names no curve point');
        $actual = Secp256k1Math::cashuHashToCurve(self::bytes($messageHex)) ?? self::fail('The message hashed to no curve point');

        $this->assertSame(bin2hex(Secp256k1Math::serialiseUncompressed($expected)), bin2hex(Secp256k1Math::serialiseUncompressed($actual)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hashToCurveCases(): iterable
    {
        foreach (self::hashToCurveVectors() as [$messageHex, $expectedPoint]) {
            yield $messageHex => [$messageHex, $expectedPoint];
        }
    }

    #[DataProvider('proofCases')]
    public function testChecksEachProofsDleqLikeTheTypeScriptImplementation(string $mintKey, string $proofJson, bool $valid): void
    {
        $proof = NutzapProof::tryFromJson($proofJson) ?? self::fail('The vector holds no proof');

        $this->assertSame($valid, new CashuDleqVerifier()->isValid($proof, $mintKey));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function proofCases(): iterable
    {
        foreach (self::proofVectors() as [$description, $mintKey, $proofJson, $valid]) {
            yield $description => [$mintKey, $proofJson, $valid];
        }
    }

    public function testTheKeysResponseHoldsTheKeysetThatSignedTheLockedProofs(): void
    {
        $this->assertCount(self::LOCKED_PROOF_COUNT, self::lockedProofTags());
    }

    public function testANutzapCarryingTheLockedProofsVerifiesAgainstTheLockKeysInfo(): void
    {
        $info = NutzapInfo::tryFromEvent(NutzapMother::infoEvent([['pubkey', self::lockKey()], ['mint', NutzapMother::MINT]]))
            ?? self::fail('Expected nutzap info');

        $this->assertNull(new NutzapVerifier()->verify(self::lockedNutzap(), $info));
    }

    public function testANutzapCarryingTheLockedProofsVerifiesItsDleqAgainstTheKeysResponse(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(self::vectorFile()['keysResponse'] ?? null);

        $this->assertNull(new NutzapDleqVerifier(new CashuDleqVerifier())->verify(self::lockedNutzap(), $keysets));
    }

    private static function lockedNutzap(): Nutzap
    {
        return NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', NutzapMother::MINT], ['unit', 'sat'], ...self::lockedProofTags()]);
    }

    /**
     * @return list<list<string>>
     */
    private static function lockedProofTags(): array
    {
        $keysets = MintKeysetCollection::fromKeysResponse(self::vectorFile()['keysResponse'] ?? null);
        $locked = array_filter(
            self::proofVectors(),
            static fn (array $vector): bool => $vector[3] && null !== $keysets->findById(NutzapProof::tryFromJson($vector[2])?->getKeysetId() ?? ''),
        );

        return array_values(array_map(static fn (array $vector): array => ['proof', $vector[2]], $locked));
    }

    private static function lockKey(): string
    {
        $lockKey = self::vectorFile()['lockKey'] ?? null;
        if (!is_string($lockKey)) {
            throw new RuntimeException('cashu-dleq.json has no lock key');
        }

        return $lockKey;
    }

    private static function bytes(string $hex): string
    {
        $bytes = hex2bin($hex);
        if (false === $bytes) {
            throw new RuntimeException(sprintf('cashu-dleq.json holds %s, which is not hex', $hex));
        }

        return $bytes;
    }

    /**
     * @return list<array{string, string}>
     */
    private static function hashToCurveVectors(): array
    {
        $vectors = [];
        foreach (self::listIn(self::vectorFile(), 'hashToCurve') as $vector) {
            $messageHex = is_array($vector) ? ($vector[0] ?? null) : null;
            $point = is_array($vector) ? ($vector[1] ?? null) : null;
            if (!is_string($messageHex) || !is_string($point)) {
                throw new RuntimeException('cashu-dleq.json holds a hashToCurve vector that is not a message and a point');
            }
            $vectors[] = [$messageHex, $point];
        }

        return $vectors;
    }

    /**
     * @return list<array{string, string, string, bool}>
     */
    private static function proofVectors(): array
    {
        $vectors = [];
        foreach (self::listIn(self::vectorFile(), 'proofs') as $vector) {
            $description = is_array($vector) ? ($vector[0] ?? null) : null;
            $mintKey = is_array($vector) ? ($vector[1] ?? null) : null;
            $proof = is_array($vector) ? ($vector[2] ?? null) : null;
            $valid = is_array($vector) ? ($vector[3] ?? null) : null;
            if (!is_string($description) || !is_string($mintKey) || !is_array($proof) || !is_bool($valid)) {
                throw new RuntimeException('cashu-dleq.json holds a proof vector that is not a description, a mint key, a proof and a verdict');
            }
            $vectors[] = [$description, $mintKey, json_encode($proof, JSON_THROW_ON_ERROR), $valid];
        }

        return $vectors;
    }

    /**
     * @param array<array-key, mixed> $file
     *
     * @return list<mixed>
     */
    private static function listIn(array $file, string $key): array
    {
        $list = $file[$key] ?? null;
        if (!is_array($list) || !array_is_list($list)) {
            throw new RuntimeException(sprintf('cashu-dleq.json has no %s list', $key));
        }

        return $list;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function vectorFile(): array
    {
        $json = file_get_contents(__DIR__.'/../Vectors/cashu-dleq.json');
        if (false === $json) {
            throw new RuntimeException('Cannot read tests/Vectors/cashu-dleq.json');
        }

        return JsonWireFormat::objectFields(JsonWireFormat::decode($json)) ?? throw new RuntimeException('cashu-dleq.json is not a JSON object');
    }
}
