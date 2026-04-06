<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventKindTest extends TestCase
{
    public function testCanCreateFromInt(): void
    {
        $kind = EventKind::fromInt(1);

        $this->assertSame(1, $kind->toInt());
        $this->assertSame('1', (string) $kind);
    }

    public function testThrowsExceptionForNegativeKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event kind must be between 0 and 65535');

        EventKind::fromInt(-1);
    }

    public function testThrowsExceptionForTooLargeKind(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event kind must be between 0 and 65535');

        EventKind::fromInt(65536);
    }

    public function testStaticFactoryMethods(): void
    {
        $this->assertSame(0, EventKind::metadata()->toInt());
        $this->assertSame(1, EventKind::textNote()->toInt());
        $this->assertSame(3, EventKind::followList()->toInt());
        $this->assertSame(4, EventKind::encryptedDirectMessage()->toInt());
        $this->assertSame(5, EventKind::eventDeletion()->toInt());
    }

    public function testIsRegular(): void
    {
        $this->assertTrue(EventKind::fromInt(1)->isRegular());
        $this->assertTrue(EventKind::fromInt(9999)->isRegular());
        $this->assertFalse(EventKind::metadata()->isRegular());
        $this->assertFalse(EventKind::followList()->isRegular());
        $this->assertFalse(EventKind::fromInt(10000)->isRegular());
    }

    public function testIsReplaceable(): void
    {
        $this->assertTrue(EventKind::metadata()->isReplaceable());
        $this->assertTrue(EventKind::followList()->isReplaceable());
        $this->assertTrue(EventKind::fromInt(10000)->isReplaceable());
        $this->assertTrue(EventKind::fromInt(19999)->isReplaceable());
        $this->assertFalse(EventKind::textNote()->isReplaceable());
        $this->assertFalse(EventKind::fromInt(20000)->isReplaceable());
    }

    public function testIsEphemeral(): void
    {
        $regularKind = EventKind::fromInt(1);
        $ephemeralKind = EventKind::fromInt(20000);
        $upperBoundKind = EventKind::fromInt(29999);
        $beyondBoundKind = EventKind::fromInt(30000);

        $this->assertFalse($regularKind->isEphemeral());
        $this->assertTrue($ephemeralKind->isEphemeral());
        $this->assertTrue($upperBoundKind->isEphemeral());
        $this->assertFalse($beyondBoundKind->isEphemeral());
    }

    public function testIsParameterisedReplaceable(): void
    {
        $regularKind = EventKind::fromInt(1);
        $parameterisedKind = EventKind::fromInt(30000);
        $upperBoundKind = EventKind::fromInt(39999);
        $beyondBoundKind = EventKind::fromInt(40000);

        $this->assertFalse($regularKind->isParameterisedReplaceable());
        $this->assertTrue($parameterisedKind->isParameterisedReplaceable());
        $this->assertTrue($upperBoundKind->isParameterisedReplaceable());
        $this->assertFalse($beyondBoundKind->isParameterisedReplaceable());
    }

    public function testEqualsWorksCorrectly(): void
    {
        $kind1 = EventKind::fromInt(1);
        $kind2 = EventKind::fromInt(1);
        $kind3 = EventKind::fromInt(2);

        $this->assertTrue($kind1->equals($kind2));
        $this->assertFalse($kind1->equals($kind3));
    }

    public function testNip17StaticFactoryMethods(): void
    {
        $this->assertSame(13, EventKind::seal()->toInt());
        $this->assertSame(14, EventKind::privateMessage()->toInt());
        $this->assertSame(1059, EventKind::giftWrap()->toInt());
    }

    public function testNip17KindConstants(): void
    {
        $this->assertSame(13, EventKind::SEAL);
        $this->assertSame(14, EventKind::PRIVATE_MESSAGE);
    }

    public function testAdditionalStaticFactoryMethods(): void
    {
        $this->assertSame(6, EventKind::repost()->toInt());
        $this->assertSame(7, EventKind::reaction()->toInt());
        $this->assertSame(1111, EventKind::comment()->toInt());
        $this->assertSame(30023, EventKind::longformContent()->toInt());
        $this->assertSame(30311, EventKind::liveEvent()->toInt());
        $this->assertSame(10002, EventKind::relayList()->toInt());
        $this->assertSame(10000, EventKind::muteList()->toInt());
        $this->assertSame(21, EventKind::video()->toInt());
        $this->assertSame(22, EventKind::shortFormVideo()->toInt());
        $this->assertSame(22242, EventKind::clientAuth()->toInt());
        $this->assertSame(24133, EventKind::nostrConnect()->toInt());
        $this->assertSame(9735, EventKind::zapReceipt()->toInt());
        $this->assertSame(9321, EventKind::nutzap()->toInt());
        $this->assertSame(10050, EventKind::dmRelayList()->toInt());
    }

    public function testReplaceableListKindConstants(): void
    {
        $this->assertSame(10000, EventKind::MUTE_LIST);
        $this->assertSame(10001, EventKind::PIN_LIST);
        $this->assertSame(10002, EventKind::RELAY_LIST);
        $this->assertSame(10003, EventKind::BOOKMARK_LIST);
        $this->assertSame(10004, EventKind::COMMUNITIES_LIST);
        $this->assertSame(10005, EventKind::PUBLIC_CHATS_LIST);
        $this->assertSame(10006, EventKind::BLOCKED_RELAYS_LIST);
        $this->assertSame(10007, EventKind::SEARCH_RELAYS_LIST);
        $this->assertSame(10009, EventKind::USER_GROUPS_LIST);
        $this->assertSame(10012, EventKind::RELAY_FEEDS_LIST);
        $this->assertSame(10015, EventKind::INTERESTS_LIST);
        $this->assertSame(10017, EventKind::GIT_AUTHORS_LIST);
        $this->assertSame(10018, EventKind::GIT_REPOSITORIES_LIST);
        $this->assertSame(10020, EventKind::MEDIA_FOLLOWS_LIST);
        $this->assertSame(10030, EventKind::CUSTOM_EMOJI_LIST);
        $this->assertSame(10050, EventKind::DM_RELAY_LIST);
        $this->assertSame(10051, EventKind::KEY_PACKAGE_RELAYS);
        $this->assertSame(10101, EventKind::GOOD_WIKI_AUTHORS_LIST);
        $this->assertSame(10102, EventKind::GOOD_WIKI_RELAYS_LIST);
    }

    public function testAllReplaceableListKindsAreReplaceable(): void
    {
        $listKinds = [
            EventKind::METADATA,
            EventKind::FOLLOW_LIST,
            EventKind::MUTE_LIST,
            EventKind::PIN_LIST,
            EventKind::RELAY_LIST,
            EventKind::BOOKMARK_LIST,
            EventKind::COMMUNITIES_LIST,
            EventKind::PUBLIC_CHATS_LIST,
            EventKind::BLOCKED_RELAYS_LIST,
            EventKind::SEARCH_RELAYS_LIST,
            EventKind::USER_GROUPS_LIST,
            EventKind::RELAY_FEEDS_LIST,
            EventKind::INTERESTS_LIST,
            EventKind::GIT_AUTHORS_LIST,
            EventKind::GIT_REPOSITORIES_LIST,
            EventKind::MEDIA_FOLLOWS_LIST,
            EventKind::CUSTOM_EMOJI_LIST,
            EventKind::DM_RELAY_LIST,
            EventKind::KEY_PACKAGE_RELAYS,
            EventKind::GOOD_WIKI_AUTHORS_LIST,
            EventKind::GOOD_WIKI_RELAYS_LIST,
        ];

        foreach ($listKinds as $kind) {
            $this->assertTrue(
                EventKind::fromInt($kind)->isReplaceable(),
                "Kind {$kind} should be replaceable"
            );
        }
    }

    public function testParameterisedReplaceableSetKindConstants(): void
    {
        $this->assertSame(30000, EventKind::FOLLOW_SET);
        $this->assertSame(30002, EventKind::RELAY_SET);
        $this->assertSame(30003, EventKind::BOOKMARK_SET);
        $this->assertSame(30004, EventKind::CURATION_SET_ARTICLES);
        $this->assertSame(30005, EventKind::CURATION_SET_VIDEO);
        $this->assertSame(30006, EventKind::CURATION_SET_PICTURES);
        $this->assertSame(30007, EventKind::KIND_MUTE_SET);
        $this->assertSame(30015, EventKind::INTEREST_SET);
        $this->assertSame(30030, EventKind::EMOJI_SET);
        $this->assertSame(30063, EventKind::RELEASE_ARTIFACT_SET);
        $this->assertSame(30267, EventKind::APP_CURATION_SET);
        $this->assertSame(31924, EventKind::CALENDAR);
        $this->assertSame(39089, EventKind::STARTER_PACK);
        $this->assertSame(39092, EventKind::MEDIA_STARTER_PACK);
    }

    public function testAllSetKindsAreParameterisedReplaceable(): void
    {
        $setKinds = [
            EventKind::FOLLOW_SET,
            EventKind::RELAY_SET,
            EventKind::BOOKMARK_SET,
            EventKind::CURATION_SET_ARTICLES,
            EventKind::CURATION_SET_VIDEO,
            EventKind::CURATION_SET_PICTURES,
            EventKind::KIND_MUTE_SET,
            EventKind::INTEREST_SET,
            EventKind::EMOJI_SET,
            EventKind::RELEASE_ARTIFACT_SET,
            EventKind::APP_CURATION_SET,
            EventKind::CALENDAR,
            EventKind::STARTER_PACK,
            EventKind::MEDIA_STARTER_PACK,
        ];

        foreach ($setKinds as $kind) {
            $this->assertTrue(
                EventKind::fromInt($kind)->isParameterisedReplaceable(),
                "Kind {$kind} should be parameterised replaceable"
            );
        }
    }

    public function testToStringReturnsKindAsString(): void
    {
        $this->assertSame('42', (string) EventKind::fromInt(42));
    }

    public function testBoundaryKindValues(): void
    {
        $zero = EventKind::fromInt(0);
        $max = EventKind::fromInt(65535);

        $this->assertSame(0, $zero->toInt());
        $this->assertSame(65535, $max->toInt());
    }
}
