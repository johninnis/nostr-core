<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BlossomAuthHeaderVectorComplianceTest extends TestCase
{
    private const int VECTOR_COUNT = 9;

    private const int SPEC_FORM_VECTOR = 1;

    private const int LEGACY_FORM_VECTOR = 2;

    public function testTheVectorsAreFullyLoaded(): void
    {
        $this->assertCount(self::VECTOR_COUNT, self::vectors());
    }

    #[DataProvider('vectorCases')]
    public function testDecodesLikeTheTypeScriptImplementation(string $header, string $expected): void
    {
        $this->assertSame($expected, self::outcome(NostrAuthHeaderCodec::decodeBlossom($header)));
    }

    public function testEncodesTheLegacyFormsEventInTheFormBud11Writes(): void
    {
        $event = NostrAuthHeaderCodec::decodeBlossom(self::vectors()[self::LEGACY_FORM_VECTOR][0]);
        self::assertInstanceOf(Event::class, $event);

        $this->assertSame(self::vectors()[self::SPEC_FORM_VECTOR][0], NostrAuthHeaderCodec::encodeBlossom($event));
    }

    public function testTheNip98DecodeRefusesTheFormBud11Writes(): void
    {
        $this->assertSame(AuthHeaderDecodeFailure::BadBase64, NostrAuthHeaderCodec::decode(self::vectors()[self::SPEC_FORM_VECTOR][0]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function vectorCases(): iterable
    {
        foreach (self::vectors() as [$header, $expected]) {
            yield $header => [$header, $expected];
        }
    }

    private static function outcome(Event|AuthHeaderDecodeFailure $decoded): string
    {
        return match ($decoded) {
            AuthHeaderDecodeFailure::BadBase64 => 'bad-base64',
            AuthHeaderDecodeFailure::BadJson => 'bad-json',
            default => $decoded instanceof Event ? $decoded->getId()->toHex() : $decoded->value,
        };
    }

    /**
     * @return list<array{string, string}>
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__.'/../Vectors/blossom-auth-header.json');
        if (false === $json) {
            throw new RuntimeException('Cannot read tests/Vectors/blossom-auth-header.json');
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['vectors'] ?? null)) {
            throw new RuntimeException('blossom-auth-header.json has no vectors list');
        }

        $vectors = [];
        foreach ($decoded['vectors'] as $vector) {
            $header = is_array($vector) ? ($vector[0] ?? null) : null;
            $expected = is_array($vector) ? ($vector[1] ?? null) : null;
            if (!is_string($header) || !is_string($expected)) {
                throw new RuntimeException('blossom-auth-header.json holds a vector that is not a header and a string expectation');
            }
            $vectors[] = [$header, $expected];
        }

        return $vectors;
    }
}
