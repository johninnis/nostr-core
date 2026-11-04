<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\DeletionCoverage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeletionVectorComplianceTest extends TestCase
{
    private const int VECTOR_COUNT = 28;

    public function testTheVectorsAreFullyLoaded(): void
    {
        $this->assertCount(self::VECTOR_COUNT, self::vectors());
    }

    #[DataProvider('vectorCases')]
    public function testJudgesTheCaseLikeTheTypeScriptImplementation(Event $deletion, Event $target, bool $covers): void
    {
        $this->assertSame($covers, DeletionCoverage::covers($deletion, $target));
    }

    #[DataProvider('coveringCases')]
    public function testTheLookupFiltersFindTheRequestThatDeletesTheTarget(Event $deletion, Event $target): void
    {
        $this->assertTrue(DeletionCoverage::lookupFilters($target)->matches($deletion));
    }

    /**
     * @return iterable<string, array{Event, Event, bool}>
     */
    public static function vectorCases(): iterable
    {
        yield from self::vectors();
    }

    /**
     * @return iterable<string, array{Event, Event}>
     */
    public static function coveringCases(): iterable
    {
        foreach (self::vectors() as $description => [$deletion, $target, $covers]) {
            if ($covers) {
                yield $description => [$deletion, $target];
            }
        }
    }

    /**
     * @return array<string, array{Event, Event, bool}>
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__.'/../Vectors/deletion.json');
        if (false === $json) {
            throw new RuntimeException('Cannot read tests/Vectors/deletion.json');
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['cases'] ?? null)) {
            throw new RuntimeException('deletion.json has no cases list');
        }

        $vectors = [];
        foreach ($decoded['cases'] as $case) {
            $description = is_array($case) ? ($case['description'] ?? null) : null;
            $deletion = is_array($case) ? Event::tryFromArray($case['deletion'] ?? null) : null;
            $target = is_array($case) ? Event::tryFromArray($case['target'] ?? null) : null;
            $covers = is_array($case) ? ($case['covers'] ?? null) : null;
            if (!is_string($description) || null === $deletion || null === $target || !is_bool($covers)) {
                throw new RuntimeException('deletion.json holds a case that is not a description, two events and a verdict');
            }
            $vectors[$description] = [$deletion, $target, $covers];
        }

        return $vectors;
    }
}
