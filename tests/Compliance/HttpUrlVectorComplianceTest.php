<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HttpUrlVectorComplianceTest extends TestCase
{
    private const int VECTOR_COUNT = 132;

    public function testTheVectorsAreFullyLoaded(): void
    {
        $this->assertCount(self::VECTOR_COUNT, self::vectors());
    }

    #[DataProvider('vectorCases')]
    public function testCanonicalisesLikeTheTypeScriptImplementation(string $input, ?string $expected): void
    {
        $this->assertSame($expected, HttpUrl::tryFromString($input)?->__toString());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function vectorCases(): iterable
    {
        foreach (self::vectors() as [$input, $expected]) {
            yield json_encode($input, JSON_THROW_ON_ERROR) => [$input, $expected];
        }
    }

    /**
     * @return list<array{string, ?string}>
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__.'/../Vectors/http-url.json');
        if (false === $json) {
            throw new RuntimeException('Cannot read tests/Vectors/http-url.json');
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['vectors'] ?? null)) {
            throw new RuntimeException('http-url.json has no vectors list');
        }

        $vectors = [];
        foreach ($decoded['vectors'] as $vector) {
            $input = is_array($vector) ? ($vector[0] ?? null) : null;
            $expected = is_array($vector) ? ($vector[1] ?? null) : null;
            if (!is_string($input) || (null !== $expected && !is_string($expected))) {
                throw new RuntimeException('http-url.json holds a vector that is not an input and a string or null expectation');
            }
            $vectors[] = [$input, $expected];
        }

        return $vectors;
    }
}
