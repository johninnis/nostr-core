<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Cipher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('ffi')]
final class Nip49PasswordLifecycleTest extends TestCase
{
    private const string EMPTY_PASSWORD_NCRYPTSEC = 'ncryptsec1qggw7t0c7gnf7wlaq22n8hcfzrp5x3k634qupzvxdqul58xttg00fzhgme3knt3vn54qye88jlm6mx68w2v2eu20sg5mmmqrvcftqefjalfz4zu93df8ys7gcgteewcytlhlz63lg8z5ylq4svm5ed2s';
    private const string EMPTY_PASSWORD_KEY_HEX = '1111111111111111111111111111111111111111111111111111111111111111';

    public function testItRoundTripsWhenTheProviderReturnsAFreshStringEachCall(): void
    {
        $cipher = Nip49Cipher::create();
        $privateKey = PrivateKey::generate();
        $expectedHex = $privateKey->toHex();

        $ncryptsec = $cipher->encrypt($privateKey, self::freshPassword(...));
        $recovered = $cipher->decrypt($ncryptsec, self::freshPassword(...));

        $this->assertSame($expectedHex, $recovered->toHex());
    }

    public function testEncryptingUnderAnEmptyPasswordIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Nip49Cipher::create()->encrypt(PrivateKey::generate(), static fn (): string => '');
    }

    // Deliberate: such an ncryptsec may already exist and its owner must still be able to recover the key — see nostr-adrs ADR-0129
    public function testAnNcryptsecMadeUnderAnEmptyPasswordIsStillRead(): void
    {
        $ncryptsec = Ncryptsec::tryFromString(self::EMPTY_PASSWORD_NCRYPTSEC) ?? self::fail('The fixture is no ncryptsec');

        $this->assertSame(self::EMPTY_PASSWORD_KEY_HEX, Nip49Cipher::create()->decrypt($ncryptsec, static fn (): string => '')->toHex());
    }

    private static function freshPassword(): string
    {
        return str_repeat('p', 1).'assword-from-a-provider';
    }
}
