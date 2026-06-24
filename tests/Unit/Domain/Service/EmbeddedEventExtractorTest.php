<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\EmbeddedEventExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmbeddedEventExtractorTest extends TestCase
{
    private const PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testReturnsNullWhenEventIsNotARepost(): void
    {
        $event = $this->buildEvent(EventKind::TEXT_NOTE, 'anything');

        $this->assertNull(EmbeddedEventExtractor::extract($event));
    }

    public function testReturnsNullWhenRepostContentIsEmpty(): void
    {
        $event = $this->buildEvent(EventKind::REPOST, '');

        $this->assertNull(EmbeddedEventExtractor::extract($event));
    }

    public function testReturnsNullWhenRepostContentIsNotAJsonObject(): void
    {
        $event = $this->buildEvent(EventKind::REPOST, 'not json at all');

        $this->assertNull(EmbeddedEventExtractor::extract($event));
    }

    public function testReturnsNullWhenEmbeddedObjectLacksEventFields(): void
    {
        $event = $this->buildEvent(EventKind::REPOST, '{"foo":"bar"}');

        $this->assertNull(EmbeddedEventExtractor::extract($event));
    }

    public function testExtractsEmbeddedEventFromKind6Repost(): void
    {
        $embedded = $this->buildEvent(EventKind::TEXT_NOTE, 'reposted note');
        $repost = $this->buildEvent(EventKind::REPOST, $embedded->toJson());

        $extracted = EmbeddedEventExtractor::extract($repost);

        $this->assertNotNull($extracted);
        $this->assertTrue($extracted->getId()->equals($embedded->getId()));
        $this->assertSame('reposted note', (string) $extracted->getContent());
    }

    public function testExtractsEmbeddedEventFromKind16GenericRepost(): void
    {
        $embedded = $this->buildEvent(EventKind::TEXT_NOTE, 'reposted note');
        $repost = $this->buildEvent(EventKind::GENERIC_REPOST, $embedded->toJson());

        $extracted = EmbeddedEventExtractor::extract($repost);

        $this->assertNotNull($extracted);
        $this->assertTrue($extracted->getId()->equals($embedded->getId()));
    }

    private function buildEvent(int $kind, string $content): Event
    {
        return new Event(
            PublicKey::fromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            Timestamp::fromInt(1700000000),
            EventKind::fromInt($kind),
            TagCollection::empty(),
            EventContent::fromString($content),
        );
    }
}
