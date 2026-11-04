<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\DeletionCoverage;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeletionCoverageTest extends TestCase
{
    private const string AUTHOR = 'a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1';
    private const string OTHER = 'b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2';
    private const int CREATED_AT = 1700000000;

    public function testARegularEventIsLookedUpByItsIdUnderItsAuthor(): void
    {
        $note = self::event(self::AUTHOR, EventKind::TEXT_NOTE);

        $this->assertSame(
            [['authors' => [self::AUTHOR], 'kinds' => [EventKind::EVENT_DELETION], '#e' => [$note->getId()->toHex()]]],
            DeletionCoverage::lookupFilters($note)->toJsonArray(),
        );
    }

    public function testAReplaceableVersionIsAlsoLookedUpByItsCoordinateFromItsCreatedAt(): void
    {
        $pins = self::event(self::AUTHOR, EventKind::PIN_LIST);

        $this->assertSame(
            [
                ['authors' => [self::AUTHOR], 'kinds' => [EventKind::EVENT_DELETION], '#e' => [$pins->getId()->toHex()]],
                ['authors' => [self::AUTHOR], 'kinds' => [EventKind::EVENT_DELETION], '#a' => ['10001:'.self::AUTHOR.':'], 'since' => self::CREATED_AT],
            ],
            DeletionCoverage::lookupFilters($pins)->toJsonArray(),
        );
    }

    public function testAnAddressableVersionIsLookedUpByItsCoordinatesIdentifier(): void
    {
        $article = self::event(self::AUTHOR, EventKind::LONGFORM_CONTENT, [['d', 'article']]);

        $this->assertSame(
            ['authors' => [self::AUTHOR], 'kinds' => [EventKind::EVENT_DELETION], '#a' => ['30023:'.self::AUTHOR.':article'], 'since' => self::CREATED_AT],
            DeletionCoverage::lookupFilters($article)->toJsonArray()[1] ?? null,
        );
    }

    public function testAnAddressableEventWhoseDTagsDisagreeHasNoCoordinateAndIsLookedUpByItsIdAlone(): void
    {
        $article = self::event(self::AUTHOR, EventKind::LONGFORM_CONTENT, [['d', 'article'], ['d', 'draft']]);

        $this->assertSame(
            [['authors' => [self::AUTHOR], 'kinds' => [EventKind::EVENT_DELETION], '#e' => [$article->getId()->toHex()]]],
            DeletionCoverage::lookupFilters($article)->toJsonArray(),
        );
    }

    public function testAGiftWrapIsAlsoLookedUpUnderEachKeyItIsAddressedTo(): void
    {
        $wrap = self::event(self::AUTHOR, EventKind::GIFT_WRAP, [['p', self::OTHER], ['p', 'not a key']]);

        $this->assertSame(
            [['authors' => [self::AUTHOR, self::OTHER], 'kinds' => [EventKind::EVENT_DELETION], '#e' => [$wrap->getId()->toHex()]]],
            DeletionCoverage::lookupFilters($wrap)->toJsonArray(),
        );
    }

    public function testAGiftWrapAddressedToItsOwnSignerNamesThatKeyOnce(): void
    {
        $wrap = self::event(self::AUTHOR, EventKind::GIFT_WRAP, [['p', self::AUTHOR]]);

        $this->assertSame(
            [['authors' => [self::AUTHOR], 'kinds' => [EventKind::EVENT_DELETION], '#e' => [$wrap->getId()->toHex()]]],
            DeletionCoverage::lookupFilters($wrap)->toJsonArray(),
        );
    }

    public function testADeletionRequestIsNeverLookedUpSinceNothingDeletesIt(): void
    {
        $this->assertTrue(DeletionCoverage::lookupFilters(self::event(self::AUTHOR, EventKind::EVENT_DELETION))->isEmpty());
    }

    public function testTheCoordinatesOfTheRequestsAuthorsOwnEventsReplaceableOnesIncluded(): void
    {
        $deletion = self::event(self::AUTHOR, EventKind::EVENT_DELETION, [['a', '30023:'.self::AUTHOR.':article'], ['a', '10001:'.self::AUTHOR.':']]);

        $this->assertSame(
            ['30023:'.self::AUTHOR.':article', '10001:'.self::AUTHOR.':'],
            self::strings(DeletionCoverage::deletedCoordinates($deletion)),
        );
    }

    public function testACoordinateTaggedTwiceIsDeletedOnce(): void
    {
        $deletion = self::event(self::AUTHOR, EventKind::EVENT_DELETION, [['a', '10001:'.self::AUTHOR.':'], ['a', '10001:'.self::AUTHOR.':', 'wss://relay.example.com']]);

        $this->assertSame(['10001:'.self::AUTHOR.':'], self::strings(DeletionCoverage::deletedCoordinates($deletion)));
    }

    public function testACoordinateNamingAnotherAuthorIsIgnored(): void
    {
        $deletion = self::event(self::AUTHOR, EventKind::EVENT_DELETION, [['a', '30023:'.self::OTHER.':article']]);

        $this->assertTrue(DeletionCoverage::deletedCoordinates($deletion)->isEmpty());
    }

    public function testAnATagThatNamesNoCoordinateIsIgnored(): void
    {
        $deletion = self::event(self::AUTHOR, EventKind::EVENT_DELETION, [['a', '1:'.self::AUTHOR.':'], ['a', 'nonsense']]);

        $this->assertTrue(DeletionCoverage::deletedCoordinates($deletion)->isEmpty());
    }

    public function testAnEventThatIsNotADeletionRequestDeletesNoCoordinate(): void
    {
        $event = self::event(self::AUTHOR, EventKind::TEXT_NOTE, [['a', '30023:'.self::AUTHOR.':article']]);

        $this->assertTrue(DeletionCoverage::deletedCoordinates($event)->isEmpty());
    }

    /**
     * @param list<list<string>> $tags
     */
    private static function event(string $pubkey, int $kind, array $tags = []): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            PublicKey::tryFromHex($pubkey) ?? throw new RuntimeException('Invalid fixture key'),
            EventKind::fromInt($kind),
            tags: TagCollection::tryFromArray($tags) ?? throw new RuntimeException('Invalid fixture tags'),
            createdAt: Timestamp::fromInt(self::CREATED_AT),
        ));
    }

    /**
     * @return list<string>
     */
    private static function strings(EventCoordinateCollection $coordinates): array
    {
        return array_map(strval(...), $coordinates->toArray());
    }
}
