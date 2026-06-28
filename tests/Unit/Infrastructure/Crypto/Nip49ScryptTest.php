<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Exception\CryptoException;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Scrypt;
use PHPUnit\Framework\TestCase;

final class Nip49ScryptTest extends TestCase
{
    public function testDeriveThrowsWhenFfiHandleIsAbsent(): void
    {
        $scrypt = new Nip49Scrypt(null);

        $this->expectException(CryptoException::class);
        $this->expectExceptionMessage('libsodium FFI is not available');

        $scrypt->derive('password', str_repeat("\0", 16), 16);
    }
}
