<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Infrastructure\Crypto;

use Innis\Nostr\Core\Infrastructure\Crypto\LibSecp256k1Ffi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

final class Secp256k1NativeRequirementTest extends TestCase
{
    #[DataProvider('adapters')]
    public function testCreateRefusesToStartWithoutTheNativeLibrary(string $adapter): void
    {
        $autoload = dirname(__DIR__, 4).'/vendor/autoload.php';
        $script = sprintf('require %s; try { \Innis\Nostr\Core\Infrastructure\Crypto\%s::create(); echo "started"; } catch (\Innis\Nostr\Core\Domain\Exception\CryptoException) { echo "refused"; }', var_export($autoload, true), $adapter);

        $this->assertSame('refused', shell_exec(sprintf('%s -d ffi.enable=0 -r %s', escapeshellarg(PHP_BINARY), escapeshellarg($script))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adapters(): iterable
    {
        yield 'the signer' => ['Secp256k1Signer'];
        yield 'the key agreement' => ['Secp256k1Ecdh'];
    }

    #[DataProvider('sonames')]
    public function testEveryPublishedSonameIsProbed(string $soname): void
    {
        $probed = new ReflectionClassConstant(LibSecp256k1Ffi::class, 'LIBRARY_NAMES')->getValue();

        $this->assertTrue(is_array($probed) && in_array($soname, $probed, true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sonames(): iterable
    {
        yield 'libsecp256k1 0.7' => ['libsecp256k1.so.6'];
        yield 'libsecp256k1 0.6' => ['libsecp256k1.so.5'];
        yield 'libsecp256k1 0.3 to 0.5' => ['libsecp256k1.so.2'];
        yield 'libsecp256k1 0.2' => ['libsecp256k1.so.1'];
    }
}
