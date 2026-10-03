<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelayUrlCorpusComplianceTest extends TestCase
{
    private const int VECTOR_COUNT = 92;

    public function testTheCorpusIsFullyLoaded(): void
    {
        $this->assertCount(self::VECTOR_COUNT, self::vectors());
    }

    #[DataProvider('corpus')]
    public function testCanonicalisesLikeEveryOtherImplementation(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, RelayUrl::tryFromString($input)?->__toString());
    }

    #[DataProvider('canonicalOutputs')]
    public function testACanonicalUrlIsItsOwnCanonicalForm(string $canonical): void
    {
        $this->assertSame($canonical, RelayUrl::tryFromString($canonical)?->__toString());
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function corpus(): iterable
    {
        foreach (self::vectors() as $vector) {
            yield $vector['name'] => [$vector['input'], $vector['expected']];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function canonicalOutputs(): iterable
    {
        foreach (self::vectors() as $vector) {
            if (null !== $vector['expected']) {
                yield $vector['name'] => [$vector['expected']];
            }
        }
    }

    /**
     * @return list<array{name: string, input: ?string, expected: ?string}>
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__.'/../Vectors/normalise-url.json');
        if (false === $json) {
            throw new RuntimeException('Cannot read tests/Vectors/normalise-url.json');
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('normalise-url.json is not a JSON array');
        }

        $vectors = [];
        foreach ($decoded as $vector) {
            if (!is_array($vector) || !is_string($vector['name'] ?? null)) {
                throw new RuntimeException('normalise-url.json holds a vector without a name');
            }
            $input = $vector['input'] ?? null;
            $expected = $vector['expected'] ?? null;
            if ((null !== $input && !is_string($input)) || (null !== $expected && !is_string($expected))) {
                throw new RuntimeException("Vector {$vector['name']} has a non-string input or expectation");
            }
            $vectors[] = ['name' => $vector['name'], 'input' => $input, 'expected' => $expected];
        }

        return $vectors;
    }
}
