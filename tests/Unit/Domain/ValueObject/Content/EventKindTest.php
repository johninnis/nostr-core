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
        $this->assertSame(0, EventKind::fromInt(EventKind::METADATA)->toInt());
        $this->assertSame(1, EventKind::fromInt(EventKind::TEXT_NOTE)->toInt());
        $this->assertSame(3, EventKind::fromInt(EventKind::FOLLOW_LIST)->toInt());
        $this->assertSame(4, EventKind::fromInt(EventKind::ENCRYPTED_DIRECT_MESSAGE)->toInt());
        $this->assertSame(5, EventKind::fromInt(EventKind::EVENT_DELETION)->toInt());
    }

    public function testIsRegular(): void
    {
        $this->assertTrue(EventKind::fromInt(1)->isRegular());
        $this->assertTrue(EventKind::fromInt(9999)->isRegular());
        $this->assertFalse(EventKind::fromInt(EventKind::METADATA)->isRegular());
        $this->assertFalse(EventKind::fromInt(EventKind::FOLLOW_LIST)->isRegular());
        $this->assertFalse(EventKind::fromInt(10000)->isRegular());
    }

    public function testIsReplaceable(): void
    {
        $this->assertTrue(EventKind::fromInt(EventKind::METADATA)->isReplaceable());
        $this->assertTrue(EventKind::fromInt(EventKind::FOLLOW_LIST)->isReplaceable());
        $this->assertTrue(EventKind::fromInt(10000)->isReplaceable());
        $this->assertTrue(EventKind::fromInt(19999)->isReplaceable());
        $this->assertFalse(EventKind::fromInt(EventKind::TEXT_NOTE)->isReplaceable());
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

    public function testEqualsMatchesKindConstant(): void
    {
        $kind = EventKind::fromInt(EventKind::HTTP_AUTH);

        $this->assertTrue($kind->is(EventKind::HTTP_AUTH));
        $this->assertFalse($kind->is(EventKind::TEXT_NOTE));
    }

    public function testNip17StaticFactoryMethods(): void
    {
        $this->assertSame(13, EventKind::fromInt(EventKind::SEAL)->toInt());
        $this->assertSame(14, EventKind::fromInt(EventKind::PRIVATE_MESSAGE)->toInt());
        $this->assertSame(1059, EventKind::fromInt(EventKind::GIFT_WRAP)->toInt());
    }

    public function testNip17KindConstants(): void
    {
        $this->assertSame(13, EventKind::SEAL);
        $this->assertSame(14, EventKind::PRIVATE_MESSAGE);
    }

    public function testAdditionalStaticFactoryMethods(): void
    {
        $this->assertSame(6, EventKind::fromInt(EventKind::REPOST)->toInt());
        $this->assertSame(7, EventKind::fromInt(EventKind::REACTION)->toInt());
        $this->assertSame(1111, EventKind::fromInt(EventKind::COMMENT)->toInt());
        $this->assertSame(30023, EventKind::fromInt(EventKind::LONGFORM_CONTENT)->toInt());
        $this->assertSame(30311, EventKind::fromInt(EventKind::LIVE_EVENT)->toInt());
        $this->assertSame(10002, EventKind::fromInt(EventKind::RELAY_LIST)->toInt());
        $this->assertSame(10000, EventKind::fromInt(EventKind::MUTE_LIST)->toInt());
        $this->assertSame(20, EventKind::fromInt(EventKind::PICTURE)->toInt());
        $this->assertSame(21, EventKind::fromInt(EventKind::VIDEO)->toInt());
        $this->assertSame(22, EventKind::fromInt(EventKind::SHORT_FORM_VIDEO)->toInt());
        $this->assertSame(22242, EventKind::fromInt(EventKind::CLIENT_AUTH)->toInt());
        $this->assertSame(24133, EventKind::fromInt(EventKind::NOSTR_CONNECT)->toInt());
        $this->assertSame(9735, EventKind::fromInt(EventKind::ZAP_RECEIPT)->toInt());
        $this->assertSame(9321, EventKind::fromInt(EventKind::NUTZAP)->toInt());
        $this->assertSame(10050, EventKind::fromInt(EventKind::DM_RELAY_LIST)->toInt());
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
        $this->assertSame(10008, EventKind::PROFILE_BADGES);
        $this->assertSame(10009, EventKind::USER_GROUPS_LIST);
        $this->assertSame(10011, EventKind::EXTERNAL_IDENTITIES);
        $this->assertSame(10012, EventKind::RELAY_FEEDS_LIST);
        $this->assertSame(10013, EventKind::PRIVATE_EVENT_RELAY_LIST);
        $this->assertSame(10015, EventKind::INTERESTS_LIST);
        $this->assertSame(10017, EventKind::GIT_AUTHORS_LIST);
        $this->assertSame(10018, EventKind::GIT_REPOSITORIES_LIST);
        $this->assertSame(10019, EventKind::NUTZAP_MINT_RECOMMENDATION);
        $this->assertSame(10020, EventKind::MEDIA_FOLLOWS_LIST);
        $this->assertSame(10030, EventKind::CUSTOM_EMOJI_LIST);
        $this->assertSame(10050, EventKind::DM_RELAY_LIST);
        $this->assertSame(10051, EventKind::KEY_PACKAGE_RELAYS);
        $this->assertSame(10101, EventKind::GOOD_WIKI_AUTHORS_LIST);
        $this->assertSame(10102, EventKind::GOOD_WIKI_RELAYS_LIST);
        $this->assertSame(10166, EventKind::RELAY_MONITOR_ANNOUNCEMENT);
        $this->assertSame(10312, EventKind::ROOM_PRESENCE);
        $this->assertSame(13194, EventKind::WALLET_INFO);
        $this->assertSame(17375, EventKind::CASHU_WALLET);
    }

    public function testRegularAndEphemeralKindConstants(): void
    {
        $this->assertSame(8, EventKind::BADGE_AWARD);
        $this->assertSame(9, EventKind::CHAT_MESSAGE);
        $this->assertSame(11, EventKind::THREAD);
        $this->assertSame(15, EventKind::FILE_MESSAGE);
        $this->assertSame(17, EventKind::WEBSITE_REACTION);
        $this->assertSame(62, EventKind::VANISH_REQUEST);
        $this->assertSame(1018, EventKind::POLL_RESPONSE);
        $this->assertSame(1040, EventKind::OPENTIMESTAMPS);
        $this->assertSame(1063, EventKind::FILE_METADATA);
        $this->assertSame(1068, EventKind::POLL);
        $this->assertSame(1311, EventKind::LIVE_CHAT_MESSAGE);
        $this->assertSame(1984, EventKind::REPORTING);
        $this->assertSame(4550, EventKind::COMMUNITY_POST_APPROVAL);
        $this->assertSame(7374, EventKind::CASHU_RESERVED_TOKENS);
        $this->assertSame(7375, EventKind::CASHU_WALLET_TOKENS);
        $this->assertSame(7376, EventKind::CASHU_WALLET_HISTORY);
        $this->assertSame(9041, EventKind::ZAP_GOAL);
        $this->assertSame(23194, EventKind::WALLET_REQUEST);
        $this->assertSame(23195, EventKind::WALLET_RESPONSE);
        $this->assertSame(24242, EventKind::BLOSSOM_BLOB);
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
        $this->assertSame(30008, EventKind::BADGE_SET);
        $this->assertSame(30009, EventKind::BADGE_DEFINITION);
        $this->assertSame(30015, EventKind::INTEREST_SET);
        $this->assertSame(30024, EventKind::LONGFORM_CONTENT_DRAFT);
        $this->assertSame(30030, EventKind::EMOJI_SET);
        $this->assertSame(30063, EventKind::RELEASE_ARTIFACT_SET);
        $this->assertSame(30166, EventKind::RELAY_DISCOVERY);
        $this->assertSame(30267, EventKind::APP_CURATION_SET);
        $this->assertSame(30312, EventKind::INTERACTIVE_ROOM);
        $this->assertSame(30313, EventKind::CONFERENCE_EVENT);
        $this->assertSame(30315, EventKind::USER_STATUS);
        $this->assertSame(30630, EventKind::SITE_MANIFEST);
        $this->assertSame(30631, EventKind::WEB_PAGE);
        $this->assertSame(30632, EventKind::WEB_PAGE_DRAFT);
        $this->assertSame(30818, EventKind::WIKI_ARTICLE);
        $this->assertSame(30819, EventKind::WIKI_REDIRECT);
        $this->assertSame(31234, EventKind::DRAFT_EVENT);
        $this->assertSame(31922, EventKind::CALENDAR_EVENT_DATE);
        $this->assertSame(31923, EventKind::CALENDAR_EVENT_TIME);
        $this->assertSame(31924, EventKind::CALENDAR);
        $this->assertSame(31925, EventKind::CALENDAR_EVENT_RSVP);
        $this->assertSame(31989, EventKind::HANDLER_RECOMMENDATION);
        $this->assertSame(31990, EventKind::HANDLER_INFORMATION);
        $this->assertSame(34235, EventKind::VIDEO_ADDRESSABLE);
        $this->assertSame(34236, EventKind::SHORT_FORM_VIDEO_ADDRESSABLE);
        $this->assertSame(34550, EventKind::COMMUNITY_DEFINITION);
        $this->assertSame(38172, EventKind::CASHU_MINT_ANNOUNCEMENT);
        $this->assertSame(38173, EventKind::FEDIMINT_ANNOUNCEMENT);
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
