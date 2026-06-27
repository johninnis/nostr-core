<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('kindConstants')]
    public function testKindConstantHasWireValue(int $expected, int $actual): void
    {
        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function kindConstants(): iterable
    {
        yield 'SEAL' => [13, EventKind::SEAL];
        yield 'PRIVATE_MESSAGE' => [14, EventKind::PRIVATE_MESSAGE];

        yield 'MUTE_LIST' => [10000, EventKind::MUTE_LIST];
        yield 'PIN_LIST' => [10001, EventKind::PIN_LIST];
        yield 'RELAY_LIST' => [10002, EventKind::RELAY_LIST];
        yield 'BOOKMARK_LIST' => [10003, EventKind::BOOKMARK_LIST];
        yield 'COMMUNITIES_LIST' => [10004, EventKind::COMMUNITIES_LIST];
        yield 'PUBLIC_CHATS_LIST' => [10005, EventKind::PUBLIC_CHATS_LIST];
        yield 'BLOCKED_RELAYS_LIST' => [10006, EventKind::BLOCKED_RELAYS_LIST];
        yield 'SEARCH_RELAYS_LIST' => [10007, EventKind::SEARCH_RELAYS_LIST];
        yield 'PROFILE_BADGES' => [10008, EventKind::PROFILE_BADGES];
        yield 'USER_GROUPS_LIST' => [10009, EventKind::USER_GROUPS_LIST];
        yield 'EXTERNAL_IDENTITIES' => [10011, EventKind::EXTERNAL_IDENTITIES];
        yield 'RELAY_FEEDS_LIST' => [10012, EventKind::RELAY_FEEDS_LIST];
        yield 'PRIVATE_EVENT_RELAY_LIST' => [10013, EventKind::PRIVATE_EVENT_RELAY_LIST];
        yield 'INTERESTS_LIST' => [10015, EventKind::INTERESTS_LIST];
        yield 'GIT_AUTHORS_LIST' => [10017, EventKind::GIT_AUTHORS_LIST];
        yield 'GIT_REPOSITORIES_LIST' => [10018, EventKind::GIT_REPOSITORIES_LIST];
        yield 'NUTZAP_MINT_RECOMMENDATION' => [10019, EventKind::NUTZAP_MINT_RECOMMENDATION];
        yield 'MEDIA_FOLLOWS_LIST' => [10020, EventKind::MEDIA_FOLLOWS_LIST];
        yield 'CUSTOM_EMOJI_LIST' => [10030, EventKind::CUSTOM_EMOJI_LIST];
        yield 'DM_RELAY_LIST' => [10050, EventKind::DM_RELAY_LIST];
        yield 'KEY_PACKAGE_RELAYS' => [10051, EventKind::KEY_PACKAGE_RELAYS];
        yield 'GOOD_WIKI_AUTHORS_LIST' => [10101, EventKind::GOOD_WIKI_AUTHORS_LIST];
        yield 'GOOD_WIKI_RELAYS_LIST' => [10102, EventKind::GOOD_WIKI_RELAYS_LIST];
        yield 'RELAY_MONITOR_ANNOUNCEMENT' => [10166, EventKind::RELAY_MONITOR_ANNOUNCEMENT];
        yield 'ROOM_PRESENCE' => [10312, EventKind::ROOM_PRESENCE];
        yield 'WALLET_INFO' => [13194, EventKind::WALLET_INFO];
        yield 'CASHU_WALLET' => [17375, EventKind::CASHU_WALLET];

        yield 'BADGE_AWARD' => [8, EventKind::BADGE_AWARD];
        yield 'CHAT_MESSAGE' => [9, EventKind::CHAT_MESSAGE];
        yield 'THREAD' => [11, EventKind::THREAD];
        yield 'FILE_MESSAGE' => [15, EventKind::FILE_MESSAGE];
        yield 'WEBSITE_REACTION' => [17, EventKind::WEBSITE_REACTION];
        yield 'VANISH_REQUEST' => [62, EventKind::VANISH_REQUEST];
        yield 'POLL_RESPONSE' => [1018, EventKind::POLL_RESPONSE];
        yield 'OPENTIMESTAMPS' => [1040, EventKind::OPENTIMESTAMPS];
        yield 'FILE_METADATA' => [1063, EventKind::FILE_METADATA];
        yield 'POLL' => [1068, EventKind::POLL];
        yield 'LIVE_CHAT_MESSAGE' => [1311, EventKind::LIVE_CHAT_MESSAGE];
        yield 'REPORTING' => [1984, EventKind::REPORTING];
        yield 'COMMUNITY_POST_APPROVAL' => [4550, EventKind::COMMUNITY_POST_APPROVAL];
        yield 'CASHU_RESERVED_TOKENS' => [7374, EventKind::CASHU_RESERVED_TOKENS];
        yield 'CASHU_WALLET_TOKENS' => [7375, EventKind::CASHU_WALLET_TOKENS];
        yield 'CASHU_WALLET_HISTORY' => [7376, EventKind::CASHU_WALLET_HISTORY];
        yield 'ZAP_GOAL' => [9041, EventKind::ZAP_GOAL];
        yield 'WALLET_REQUEST' => [23194, EventKind::WALLET_REQUEST];
        yield 'WALLET_RESPONSE' => [23195, EventKind::WALLET_RESPONSE];
        yield 'BLOSSOM_BLOB' => [24242, EventKind::BLOSSOM_BLOB];

        yield 'FOLLOW_SET' => [30000, EventKind::FOLLOW_SET];
        yield 'RELAY_SET' => [30002, EventKind::RELAY_SET];
        yield 'BOOKMARK_SET' => [30003, EventKind::BOOKMARK_SET];
        yield 'CURATION_SET_ARTICLES' => [30004, EventKind::CURATION_SET_ARTICLES];
        yield 'CURATION_SET_VIDEO' => [30005, EventKind::CURATION_SET_VIDEO];
        yield 'CURATION_SET_PICTURES' => [30006, EventKind::CURATION_SET_PICTURES];
        yield 'KIND_MUTE_SET' => [30007, EventKind::KIND_MUTE_SET];
        yield 'BADGE_SET' => [30008, EventKind::BADGE_SET];
        yield 'BADGE_DEFINITION' => [30009, EventKind::BADGE_DEFINITION];
        yield 'INTEREST_SET' => [30015, EventKind::INTEREST_SET];
        yield 'LONGFORM_CONTENT_DRAFT' => [30024, EventKind::LONGFORM_CONTENT_DRAFT];
        yield 'EMOJI_SET' => [30030, EventKind::EMOJI_SET];
        yield 'RELEASE_ARTIFACT_SET' => [30063, EventKind::RELEASE_ARTIFACT_SET];
        yield 'RELAY_DISCOVERY' => [30166, EventKind::RELAY_DISCOVERY];
        yield 'APP_CURATION_SET' => [30267, EventKind::APP_CURATION_SET];
        yield 'INTERACTIVE_ROOM' => [30312, EventKind::INTERACTIVE_ROOM];
        yield 'CONFERENCE_EVENT' => [30313, EventKind::CONFERENCE_EVENT];
        yield 'USER_STATUS' => [30315, EventKind::USER_STATUS];
        yield 'SITE_MANIFEST' => [30630, EventKind::SITE_MANIFEST];
        yield 'WEB_PAGE' => [30631, EventKind::WEB_PAGE];
        yield 'WEB_PAGE_DRAFT' => [30632, EventKind::WEB_PAGE_DRAFT];
        yield 'WIKI_ARTICLE' => [30818, EventKind::WIKI_ARTICLE];
        yield 'WIKI_REDIRECT' => [30819, EventKind::WIKI_REDIRECT];
        yield 'DRAFT_EVENT' => [31234, EventKind::DRAFT_EVENT];
        yield 'CALENDAR_EVENT_DATE' => [31922, EventKind::CALENDAR_EVENT_DATE];
        yield 'CALENDAR_EVENT_TIME' => [31923, EventKind::CALENDAR_EVENT_TIME];
        yield 'CALENDAR' => [31924, EventKind::CALENDAR];
        yield 'CALENDAR_EVENT_RSVP' => [31925, EventKind::CALENDAR_EVENT_RSVP];
        yield 'HANDLER_RECOMMENDATION' => [31989, EventKind::HANDLER_RECOMMENDATION];
        yield 'HANDLER_INFORMATION' => [31990, EventKind::HANDLER_INFORMATION];
        yield 'VIDEO_ADDRESSABLE' => [34235, EventKind::VIDEO_ADDRESSABLE];
        yield 'SHORT_FORM_VIDEO_ADDRESSABLE' => [34236, EventKind::SHORT_FORM_VIDEO_ADDRESSABLE];
        yield 'COMMUNITY_DEFINITION' => [34550, EventKind::COMMUNITY_DEFINITION];
        yield 'CASHU_MINT_ANNOUNCEMENT' => [38172, EventKind::CASHU_MINT_ANNOUNCEMENT];
        yield 'FEDIMINT_ANNOUNCEMENT' => [38173, EventKind::FEDIMINT_ANNOUNCEMENT];
        yield 'STARTER_PACK' => [39089, EventKind::STARTER_PACK];
        yield 'MEDIA_STARTER_PACK' => [39092, EventKind::MEDIA_STARTER_PACK];
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
