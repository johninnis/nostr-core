<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\EmbeddedEventExtractor;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmbeddedEventExtractorTest extends TestCase
{
    private const string PUBKEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

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

    public function testRejectsAnEmbeddedEventWhoseTagsAreWrittenAsAnObject(): void
    {
        $embedded = $this->buildEvent(EventKind::TEXT_NOTE, 'reposted note');
        $repost = $this->buildEvent(EventKind::REPOST, str_replace('"tags":[]', '"tags":{}', $embedded->toJson()));

        $this->assertNull(EmbeddedEventExtractor::extract($repost));
    }

    public function testRejectsEmbeddedObjectThatTheEventParserRejects(): void
    {
        $repost = $this->buildEvent(EventKind::REPOST, '{"pubkey":"nothex","created_at":1700000000,"kind":1,"tags":[],"content":"x"}');

        $this->assertNull(EmbeddedEventExtractor::extract($repost));
    }

    public function testExtractionGateIsTheEventParserAlone(): void
    {
        $rumour = Rumour::draft(
            PublicKey::tryFromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('reposted note'),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        );
        $embedded = json_encode([
            'id' => $rumour->getId()->toHex(),
            'pubkey' => self::PUBKEY,
            'created_at' => 1700000000,
            'kind' => EventKind::TEXT_NOTE,
            'tags' => [],
            'content' => 'reposted note',
            'sig' => EventMother::signature()->toHex(),
        ]);
        $this->assertIsString($embedded);

        $extracted = EmbeddedEventExtractor::extract($this->buildEvent(EventKind::REPOST, $embedded));

        $this->assertNotNull($extracted);
        $this->assertSame('reposted note', (string) $extracted->getContent());
    }

    private function buildEvent(int $kind, string $content): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex(self::PUBKEY) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt($kind),
            EventContent::fromString($content),
            new TagCollection(),
            Timestamp::fromInt(1700000000),
        ));
    }
}
