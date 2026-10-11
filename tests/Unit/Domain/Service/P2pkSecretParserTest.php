<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\P2pkSecretParser;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class P2pkSecretParserTest extends TestCase
{
    public function testACompressedKeyIsReadAsItsXCoordinate(): void
    {
        $this->assertSame(NutzapMother::P2PK_KEY, P2pkSecretParser::tryParseCompressedKey('02'.NutzapMother::P2PK_KEY)?->toHex());
    }

    public function testACompressedKeyWithAnOddPrefixIsTheSameKeyAsNut11ComparesIt(): void
    {
        $this->assertSame(NutzapMother::P2PK_KEY, P2pkSecretParser::tryParseCompressedKey('03'.NutzapMother::P2PK_KEY)?->toHex());
    }

    public function testAnUpperCaseCompressedKeyIsReadAsItsLowerCaseXCoordinate(): void
    {
        $this->assertSame(NutzapMother::P2PK_KEY, P2pkSecretParser::tryParseCompressedKey('02'.strtoupper(NutzapMother::P2PK_KEY))?->toHex());
    }

    #[DataProvider('notCompressedKeys')]
    public function testAValueThatIsNoCompressedKeyIsNoKey(string $key): void
    {
        $this->assertNull(P2pkSecretParser::tryParseCompressedKey($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notCompressedKeys(): iterable
    {
        yield 'a bare x-coordinate' => [NutzapMother::P2PK_KEY];
        yield 'an uncompressed prefix' => ['04'.NutzapMother::P2PK_KEY];
        yield 'a short x-coordinate' => ['02'.substr(NutzapMother::P2PK_KEY, 1)];
        yield 'a long x-coordinate' => ['02'.NutzapMother::P2PK_KEY.'00'];
        yield 'a character that is not hex' => ['02'.substr(NutzapMother::P2PK_KEY, 1).'g'];
        yield 'the empty string' => [''];
    }

    public function testTheLockOfAP2pkSecretIsItsDataKey(): void
    {
        $this->assertSame(NutzapMother::P2PK_KEY, P2pkSecretParser::tryParseLockKey(NutzapMother::secret('02'.NutzapMother::P2PK_KEY))?->toHex());
    }

    public function testASecretThatAlsoLetsAnotherKeySpendLocksToNoKey(): void
    {
        $secret = json_encode(['P2PK', ['nonce' => 'n', 'data' => '02'.NutzapMother::P2PK_KEY, 'tags' => [['pubkeys', '02'.NutzapMother::OTHER_P2PK_KEY]]]], JSON_THROW_ON_ERROR);

        $this->assertNull(P2pkSecretParser::tryParseLockKey($secret));
    }

    public function testASecretWhoseLockLapsesLocksToNoKey(): void
    {
        $secret = json_encode(['P2PK', ['nonce' => 'n', 'data' => '02'.NutzapMother::P2PK_KEY, 'tags' => [['locktime', '1689418329']]]], JSON_THROW_ON_ERROR);

        $this->assertNull(P2pkSecretParser::tryParseLockKey($secret));
    }
}
