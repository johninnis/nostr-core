<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Secp256k1;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

final class Bip340ComplianceTest extends TestCase
{
    private const VECTORS_PATH = __DIR__.'/../Fixtures/bip340-vectors.csv';

    protected function tearDown(): void
    {
        $this->setFfiAvailability(null);
    }

    public function testVerifyFfiMatchesExpectedAcrossAllVectors(): void
    {
        $this->setFfiAvailability(true);
        $this->assertTrue(Secp256k1::isAvailable(), 'FFI libsecp256k1 must be available for this test');

        $problems = [];
        foreach ($this->vectors() as $vector) {
            $problem = $this->collectVerifyMismatch($vector, 'FFI');
            if (null !== $problem) {
                $problems[] = $problem;
            }
        }

        $this->assertSame([], $problems, "FFI verify divergences:\n".implode("\n", $problems));
    }

    public function testVerifyPurePhpMatchesExpectedAcrossAllVectors(): void
    {
        $this->setFfiAvailability(false);
        $this->assertFalse(Secp256k1::isAvailable(), 'FFI must be forced off for this test');

        $problems = [];
        foreach ($this->vectors() as $vector) {
            $problem = $this->collectVerifyMismatch($vector, 'pure-PHP');
            if (null !== $problem) {
                $problems[] = $problem;
            }
        }

        $this->assertSame([], $problems, "pure-PHP verify divergences:\n".implode("\n", $problems));
    }

    public function testSignThenVerifyRoundTripsOnBothPathsForSigningVectors(): void
    {
        $problems = [];

        foreach ($this->signingVectors() as $vector) {
            $privateKey = PrivateKey::fromHex(strtolower($vector['secret']));
            $this->assertNotNull($privateKey);

            $this->setFfiAvailability(true);
            $ffiSig = $privateKey->sign($vector['message']);

            $this->setFfiAvailability(false);
            $phpSig = $privateKey->sign($vector['message']);

            foreach ([true, false] as $verifyFfi) {
                $this->setFfiAvailability($verifyFfi);
                $verifyLabel = $verifyFfi ? 'FFI' : 'pure-PHP';

                foreach ([['FFI', $ffiSig], ['pure-PHP', $phpSig]] as [$signLabel, $sig]) {
                    try {
                        $ok = $privateKey->getPublicKey()->verify($vector['message'], $sig);
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

    public function testPublicKeyDerivationMatchesAcrossBothPaths(): void
    {
        foreach ($this->signingVectors() as $vector) {
            $privateKey = PrivateKey::fromHex(strtolower($vector['secret']));
            $this->assertNotNull($privateKey);

            $this->setFfiAvailability(true);
            $ffiPub = $privateKey->getPublicKey()->toHex();

            $this->setFfiAvailability(false);
            $phpPub = $privateKey->getPublicKey()->toHex();

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

    private function collectVerifyMismatch(array $vector, string $pathLabel): ?string
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
            $actual = $publicKey->verify($vector['message'], $signature);
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

    private function setFfiAvailability(?bool $available): void
    {
        Secp256k1::reset();

        if (null === $available) {
            return;
        }

        $reflection = new ReflectionClass(Secp256k1::class);
        $initialised = $reflection->getProperty('initialised');
        $availableProp = $reflection->getProperty('available');

        if ($available) {
            Secp256k1::isAvailable();

            return;
        }

        $initialised->setValue(null, true);
        $availableProp->setValue(null, false);
    }
}
