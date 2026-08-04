<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Cipher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('ffi')]
final class Nip49PasswordLifecycleTest extends TestCase
{
    private const int LOG_N = 16;

    public function testItRoundTripsWhenTheProviderReturnsAFreshStringEachCall(): void
    {
        $cipher = Nip49Cipher::create();
        $privateKey = PrivateKey::generate();
        $expectedHex = $privateKey->toHex();

        $ncryptsec = $cipher->encrypt($privateKey, self::freshPassword(...), self::LOG_N);
        $recovered = $cipher->decrypt($ncryptsec, self::freshPassword(...));

        $this->assertSame($expectedHex, $recovered->toHex());
    }

    // Deliberate: revealPassword XORs against zeros to detach the buffer; an empty password makes that a zero-length XOR, which must stay a legal no-op rather than an error — see ADR-0028
    public function testAnEmptyPasswordSurvivesTheDetachmentStep(): void
    {
        $cipher = Nip49Cipher::create();
        $privateKey = PrivateKey::generate();
        $expectedHex = $privateKey->toHex();

        $ncryptsec = $cipher->encrypt($privateKey, static fn (): string => '', self::LOG_N);

        $this->assertSame($expectedHex, $cipher->decrypt($ncryptsec, static fn (): string => '')->toHex());
    }

    private static function freshPassword(): string
    {
        return str_repeat('p', 1).'assword-from-a-provider';
    }
}
