<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileEventMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Content\FileMetadata;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\TagCollectionMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileEventMetadataTest extends TestCase
{
    private const string URL = 'https://cdn.example.com/abc.png';

    public function testTryFromAcceptsMetadataStatingTheMimeTypeAndBothHashes(): void
    {
        $metadata = self::complete();

        $this->assertSame($metadata, FileEventMetadata::tryFrom($metadata)?->getMetadata());
    }

    /**
     * @return iterable<string, array{FileMetadata}>
     */
    public static function incompleteMetadata(): iterable
    {
        yield 'no mime type' => [FileMetadata::from(self::URL, null, str_repeat('a', 64), str_repeat('b', 64))];
        yield 'no hash' => [FileMetadata::from(self::URL, 'image/png', null, str_repeat('b', 64))];
        yield 'no original hash' => [FileMetadata::from(self::URL, 'image/png', str_repeat('a', 64), null)];
        yield 'short hash' => [FileMetadata::from(self::URL, 'image/png', str_repeat('a', 62), str_repeat('b', 64))];
        yield 'uppercase hash' => [FileMetadata::from(self::URL, 'image/png', str_repeat('A', 64), str_repeat('b', 64))];
        yield 'non-hex original hash' => [FileMetadata::from(self::URL, 'image/png', str_repeat('a', 64), str_repeat('z', 64))];
    }

    #[DataProvider('incompleteMetadata')]
    public function testTryFromRefusesMetadataMissingAFieldNip94Requires(FileMetadata $metadata): void
    {
        $this->assertNull(FileEventMetadata::tryFrom($metadata));
    }

    #[DataProvider('incompleteMetadata')]
    public function testFromThrowsForMetadataMissingAFieldNip94Requires(FileMetadata $metadata): void
    {
        $this->expectException(InvalidArgumentException::class);

        FileEventMetadata::from($metadata);
    }

    public function testToTagsWritesTheMetadataTags(): void
    {
        $metadata = self::complete();

        $this->assertTrue(FileEventMetadata::from($metadata)->toTags()->equals($metadata->toTags()));
    }

    public function testTryFromEventReadsAKind1063Event(): void
    {
        $parsed = FileEventMetadata::tryFromEvent(self::event(EventKind::FILE_METADATA, self::completeTags()));

        $this->assertTrue(self::complete()->equals($parsed?->getMetadata() ?? $this->fail('Expected file metadata')));
    }

    public function testTryFromEventRefusesAnotherKind(): void
    {
        $this->assertNull(FileEventMetadata::tryFromEvent(self::event(EventKind::TEXT_NOTE, self::completeTags())));
    }

    public function testTryFromEventRefusesAnEventWithoutAnOriginalHash(): void
    {
        $tags = array_values(array_filter(self::completeTags(), static fn (array $tag): bool => 'ox' !== $tag[0]));

        $this->assertNull(FileEventMetadata::tryFromEvent(self::event(EventKind::FILE_METADATA, $tags)));
    }

    public function testTryFromEventRefusesAnEventWhoseHashTagsDisagree(): void
    {
        $tags = [...self::completeTags(), ['x', str_repeat('c', 64)]];

        $this->assertNull(FileEventMetadata::tryFromEvent(self::event(EventKind::FILE_METADATA, $tags)));
    }

    public function testTryFromEventRefusesAnEventWhoseMimeTypeIsNotOne(): void
    {
        $tags = [...array_values(array_filter(self::completeTags(), static fn (array $tag): bool => 'm' !== $tag[0])), ['m', 'png']];

        $this->assertNull(FileEventMetadata::tryFromEvent(self::event(EventKind::FILE_METADATA, $tags)));
    }

    private static function complete(): FileMetadata
    {
        return FileMetadata::from(self::URL, 'image/png', str_repeat('a', 64), str_repeat('b', 64));
    }

    /**
     * @return list<list<string>>
     */
    private static function completeTags(): array
    {
        return [['url', self::URL], ['m', 'image/png'], ['x', str_repeat('a', 64)], ['ox', str_repeat('b', 64)]];
    }

    /**
     * @param list<list<string>> $tags
     */
    private static function event(int $kind, array $tags): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex(str_repeat('ab', 32)) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt($kind),
            tags: TagCollectionMother::fromRaw($tags),
        ));
    }
}
