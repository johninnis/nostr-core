<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Service\P2pkSecretParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class P2pkLockVectorComplianceTest extends TestCase
{
    private const int VECTOR_COUNT = 47;

    public function testTheVectorsAreFullyLoaded(): void
    {
        $this->assertCount(self::VECTOR_COUNT, self::vectors());
    }

    #[DataProvider('vectorCases')]
    public function testReadsTheLockLikeTheTypeScriptImplementation(string $secret, ?string $expected): void
    {
        $this->assertSame($expected, P2pkSecretParser::tryParseLockKey($secret)?->toHex());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function vectorCases(): iterable
    {
        foreach (self::vectors() as [$secret, $expected]) {
            yield json_encode($secret, JSON_THROW_ON_ERROR) => [$secret, $expected];
        }
    }

    /**
     * @return list<array{string, ?string}>
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__.'/../Vectors/p2pk-lock.json');
        if (false === $json) {
            throw new RuntimeException('Cannot read tests/Vectors/p2pk-lock.json');
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['vectors'] ?? null)) {
            throw new RuntimeException('p2pk-lock.json has no vectors list');
        }

        $vectors = [];
        foreach ($decoded['vectors'] as $vector) {
            $secret = is_array($vector) ? ($vector[0] ?? null) : null;
            $expected = is_array($vector) ? ($vector[1] ?? null) : null;
            if (!is_string($secret) || (null !== $expected && !is_string($expected))) {
                throw new RuntimeException('p2pk-lock.json holds a vector that is not a secret and a string or null expectation');
            }
            $vectors[] = [$secret, $expected];
        }

        return $vectors;
    }
}
