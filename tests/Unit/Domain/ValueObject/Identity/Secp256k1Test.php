<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Secp256k1;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Secp256k1Test extends TestCase
{
    protected function tearDown(): void
    {
        Secp256k1::reset();
    }

    public function testIsAvailableReturnsBool(): void
    {
        $this->assertIsBool(Secp256k1::isAvailable());
    }

    #[DataProvider('bip340VerifyVectorsProvider')]
    public function testVerifyWithBip340Vectors(string $pubkeyHex, string $msgHex, string $sigHex, bool $expected): void
    {
        $msgBytes = hex2bin($msgHex);
        $this->assertNotFalse($msgBytes);

        $pubkey = \Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey::fromHex($pubkeyHex);
        $this->assertNotNull($pubkey);
        $result = $pubkey->verify($msgBytes, \Innis\Nostr\Core\Domain\ValueObject\Identity\Signature::fromHex($sigHex) ?? throw new RuntimeException('Invalid test sig'));

        $this->assertSame($expected, $result);
    }

    public static function bip340VerifyVectorsProvider(): array
    {
        return [
            'BIP-340 vector 0' => [
                'f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9',
                '0000000000000000000000000000000000000000000000000000000000000000',
                'e907831f80848d1069a5371b402410364bdf1c5f8307b0084c55f1ce2dca821525f66a4a85ea8b71e482a74f382d2ce5ebeee8fdb2172f477df4900d310536c0',
                true,
            ],
            'BIP-340 vector 1' => [
                'dff1d77f2a671c5f36183726db2341be58feae1da2deced843240f7b502ba659',
                '243f6a8885a308d313198a2e03707344a4093822299f31d0082efa98ec4e6c89',
                '6896bd60eeae296db48a229ff71dfe071bde413e6d43f917dc8dcf8c78de33418906d11ac976abccb20b091292bff4ea897efcb639ea871cfa95f6de339e4b0a',
                true,
            ],
            'BIP-340 vector 2' => [
                'dd308afec5777e13121fa72b9cc1b7cc0139715309b086c960e18fd969774eb8',
                '7e2d58d8b3bcdf1abadec7829054f90dda9805aab56c77333024b9d0a508b75c',
                '5831aaeed7b44bb74e5eab94ba9d4294c49bcf2a60728d8b4c200f50dd313c1bab745879a5ad954a72c45a91c3a51d3c7adea98d82f8481e0e1e03674a6f3fb7',
                true,
            ],
            'BIP-340 vector 5 - public key not on curve' => [
                'eefdea4cdb677750a420fee807eacf21eb9898ae79b9768766e4faa04a2d4a34',
                '243f6a8885a308d313198a2e03707344a4093822299f31d0082efa98ec4e6c89',
                '6cff5c3ba86c69ea4b7376f31a9bcb4f74c1976089b2d9963da2e5543e17776969e89b4c5564d00349106b8497785dd7d1d713a8ae82b32fa79d5f7fc407d39b',
                false,
            ],
        ];
    }

    public function testSignAndVerifyRoundTrip(): void
    {
        $privateKey = PrivateKey::fromHex('0000000000000000000000000000000000000000000000000000000000000003') ?? throw new RuntimeException('Invalid test key');
        $publicKey = $privateKey->getPublicKey();

        $message = hash('sha256', 'test message for round trip', true);
        $signature = $privateKey->sign($message);

        $this->assertTrue($publicKey->verify($message, $signature));
    }

    public function testDerivePublicKeyMatchesBip340Vector(): void
    {
        $privateKey = PrivateKey::fromHex('0000000000000000000000000000000000000000000000000000000000000003') ?? throw new RuntimeException('Invalid test key');
        $publicKey = $privateKey->getPublicKey();

        $this->assertSame(
            'f9308a019258c31049344f85f89d5229b531c845836f99b08601f113bce036f9',
            $publicKey->toHex()
        );
    }

    public function testVerifyRejectsInvalidSignature(): void
    {
        $privateKey = PrivateKey::fromHex('0000000000000000000000000000000000000000000000000000000000000003') ?? throw new RuntimeException('Invalid test key');
        $publicKey = $privateKey->getPublicKey();
        $message = hash('sha256', 'test message', true);

        $invalidSig = \Innis\Nostr\Core\Domain\ValueObject\Identity\Signature::fromHex(str_repeat('ab', 64));
        $this->assertNotNull($invalidSig);

        $this->assertFalse($publicKey->verify($message, $invalidSig));
    }

    public function testSignProducesDifferentSignaturesForDifferentMessages(): void
    {
        $privateKey = PrivateKey::fromHex('0000000000000000000000000000000000000000000000000000000000000003') ?? throw new RuntimeException('Invalid test key');

        $sig1 = $privateKey->sign(hash('sha256', 'message one', true));
        $sig2 = $privateKey->sign(hash('sha256', 'message two', true));

        $this->assertNotSame($sig1->toHex(), $sig2->toHex());
    }
}
