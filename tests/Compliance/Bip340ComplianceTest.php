<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Infrastructure\Adapter\NativeRandomBytesGeneratorAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1SignatureAdapter;
use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use Innis\Nostr\Core\Tests\Fixtures\QueuedRandomBytesGenerator;
use PHPUnit\Framework\TestCase;
use Throwable;

final class Bip340ComplianceTest extends TestCase
{
    private const VECTORS_PATH = __DIR__.'/../Fixtures/bip340-vectors.csv';

    public function testVerifyFfiMatchesExpectedAcrossAllVectors(): void
    {
        $service = $this->ffiService();

        $problems = [];
        foreach ($this->vectors() as $vector) {
            $problem = $this->collectVerifyMismatch($service, $vector, 'FFI');
            if (null !== $problem) {
                $problems[] = $problem;
            }
        }

        $this->assertSame([], $problems, "FFI verify divergences:\n".implode("\n", $problems));
    }

    public function testVerifyPurePhpMatchesExpectedAcrossAllVectors(): void
    {
        $service = $this->purePhpService();

        $problems = [];
        foreach ($this->vectors() as $vector) {
            $problem = $this->collectVerifyMismatch($service, $vector, 'pure-PHP');
            if (null !== $problem) {
                $problems[] = $problem;
            }
        }

        $this->assertSame([], $problems, "pure-PHP verify divergences:\n".implode("\n", $problems));
    }

    public function testSignThenVerifyRoundTripsOnBothPathsForSigningVectors(): void
    {
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();

        $problems = [];

        foreach ($this->signingVectors() as $vector) {
            $privateKey = PrivateKey::fromHex(strtolower($vector['secret']));
            $this->assertNotNull($privateKey);

            $ffiSig = $ffiService->sign($privateKey, $vector['message']);
            $phpSig = $purePhpService->sign($privateKey, $vector['message']);
            $publicKey = $ffiService->derivePublicKey($privateKey);

            foreach ([['FFI', $ffiService], ['pure-PHP', $purePhpService]] as [$verifyLabel, $verifier]) {
                foreach ([['FFI', $ffiSig], ['pure-PHP', $phpSig]] as [$signLabel, $sig]) {
                    try {
                        $ok = $verifier->verify($publicKey, $vector['message'], $sig);
                    } catch (Throwable $e) {
                        $problems[] = sprintf(
                            'Vector %d (msg len %d): verify(%s-signed) under %s verifier THREW %s — %s',
                            $vector['index'],
                            strlen($vector['message']),
                            $signLabel,
                            $verifyLabel,
                            $e::class,
                            $e->getMessage(),
                        );

                        continue;
                    }

                    if (!$ok) {
                        $problems[] = sprintf(
                            'Vector %d (msg len %d): %s-signed sig FAILED verification under %s verifier',
                            $vector['index'],
                            strlen($vector['message']),
                            $signLabel,
                            $verifyLabel,
                        );
                    }
                }
            }
        }

        $this->assertSame([], $problems, "Sign/verify parity issues:\n".implode("\n", $problems));
    }

    public function testSignProducesByteIdenticalSignaturesForSpecVectors(): void
    {
        $problems = [];

        foreach ($this->signingVectors() as $vector) {
            $auxBytes = hex2bin($vector['aux']);
            $this->assertNotFalse($auxBytes, sprintf('Vector %d: aux_rand hex decode failed', $vector['index']));

            $privateKey = PrivateKey::fromHex(strtolower($vector['secret']));
            $this->assertNotNull($privateKey);

            foreach ([['FFI', $this->ffiServiceWithFixedAux($auxBytes)], ['pure-PHP', $this->purePhpServiceWithFixedAux($auxBytes)]] as [$label, $service]) {
                $actualSigHex = $service->sign($privateKey, $vector['message'])->toHex();
                $expectedSigHex = strtolower($vector['signature']);

                if ($actualSigHex !== $expectedSigHex) {
                    $problems[] = sprintf(
                        'Vector %d (%s, msg len %d): got %s, expected %s',
                        $vector['index'],
                        $label,
                        strlen($vector['message']),
                        $actualSigHex,
                        $expectedSigHex,
                    );
                }
            }
        }

        $this->assertSame([], $problems, "Byte-identical sign divergences:\n".implode("\n", $problems));
    }

    public function testPublicKeyDerivationMatchesAcrossBothPaths(): void
    {
        $ffiService = $this->ffiService();
        $purePhpService = $this->purePhpService();

        foreach ($this->signingVectors() as $vector) {
            $privateKey = PrivateKey::fromHex(strtolower($vector['secret']));
            $this->assertNotNull($privateKey);

            $ffiPub = $ffiService->derivePublicKey($privateKey)->toHex();
            $phpPub = $purePhpService->derivePublicKey($privateKey)->toHex();

            $this->assertSame(
                strtolower($vector['public']),
                $ffiPub,
                sprintf('FFI pubkey derivation mismatch on vector %d', $vector['index']),
            );
            $this->assertSame(
                strtolower($vector['public']),
                $phpPub,
                sprintf('pure-PHP pubkey derivation mismatch on vector %d', $vector['index']),
            );
        }
    }

    private function collectVerifyMismatch(SignatureServiceInterface $service, array $vector, string $pathLabel): ?string
    {
        $publicKey = PublicKey::fromHex(strtolower($vector['public']));

        if (null === $publicKey) {
            if ($vector['expected']) {
                return sprintf('Vector %d (%s): pubkey rejected by fromHex but CSV expected TRUE', $vector['index'], $pathLabel);
            }

            return null;
        }

        $signature = Signature::fromHex(strtolower($vector['signature']));

        if (null === $signature) {
            if ($vector['expected']) {
                return sprintf('Vector %d (%s): sig rejected by fromHex but CSV expected TRUE', $vector['index'], $pathLabel);
            }

            return null;
        }

        try {
            $actual = $service->verify($publicKey, $vector['message'], $signature);
        } catch (Throwable $e) {
            return sprintf(
                'Vector %d (%s): verify THREW %s — %s (CSV: %s). Comment: %s',
                $vector['index'],
                $pathLabel,
                $e::class,
                $e->getMessage(),
                $vector['expected'] ? 'TRUE' : 'FALSE',
                $vector['comment'],
            );
        }

        if ($actual !== $vector['expected']) {
            return sprintf(
                'Vector %d (%s): got %s, expected %s. Comment: %s',
                $vector['index'],
                $pathLabel,
                $actual ? 'TRUE' : 'FALSE',
                $vector['expected'] ? 'TRUE' : 'FALSE',
                $vector['comment'],
            );
        }

        return null;
    }

    private function vectors(): iterable
    {
        $handle = fopen(self::VECTORS_PATH, 'r');
        assert(false !== $handle);

        fgetcsv($handle, escape: '\\');

        while (false !== ($row = fgetcsv($handle, escape: '\\'))) {
            $messageHex = (string) $row[4];
            $message = '' === $messageHex ? '' : (hex2bin($messageHex) ?: '');

            yield [
                'index' => (int) $row[0],
                'secret' => (string) $row[1],
                'public' => (string) $row[2],
                'aux' => (string) $row[3],
                'message' => $message,
                'signature' => (string) $row[5],
                'expected' => 'TRUE' === $row[6],
                'comment' => (string) ($row[7] ?? ''),
            ];
        }

        fclose($handle);
    }

    private function signingVectors(): iterable
    {
        foreach ($this->vectors() as $vector) {
            if ('' !== $vector['secret']) {
                yield $vector;
            }
        }
    }

    private function ffiService(): Secp256k1SignatureAdapter
    {
        $randomBytes = new NativeRandomBytesGeneratorAdapter();
        $ffi = LibSecp256k1Ffi::tryLoad($randomBytes->bytes(32))
            ?? self::markTestSkipped('libsecp256k1 FFI unavailable');

        return new Secp256k1SignatureAdapter($ffi, $randomBytes);
    }

    private function purePhpService(): Secp256k1SignatureAdapter
    {
        return new Secp256k1SignatureAdapter(null, new NativeRandomBytesGeneratorAdapter());
    }

    private function ffiServiceWithFixedAux(string $aux): Secp256k1SignatureAdapter
    {
        $ffi = LibSecp256k1Ffi::tryLoad((new NativeRandomBytesGeneratorAdapter())->bytes(32))
            ?? self::markTestSkipped('libsecp256k1 FFI unavailable');

        return new Secp256k1SignatureAdapter($ffi, QueuedRandomBytesGenerator::withBytes($aux));
    }

    private function purePhpServiceWithFixedAux(string $aux): Secp256k1SignatureAdapter
    {
        return new Secp256k1SignatureAdapter(null, QueuedRandomBytesGenerator::withBytes($aux));
    }
}
