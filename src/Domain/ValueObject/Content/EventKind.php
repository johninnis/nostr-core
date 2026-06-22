<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use InvalidArgumentException;
use Override;
use Stringable;

final readonly class EventKind implements Stringable
{
    public const int METADATA = 0;
    public const int TEXT_NOTE = 1;
    public const int RECOMMEND_SERVER = 2;
    public const int FOLLOW_LIST = 3;
    public const int ENCRYPTED_DIRECT_MESSAGE = 4;
    public const int EVENT_DELETION = 5;
    public const int REPOST = 6;
    public const int REACTION = 7;
    public const int BADGE_AWARD = 8;
    public const int CHAT_MESSAGE = 9;
    public const int THREAD = 11;
    public const int SEAL = 13;
    public const int PRIVATE_MESSAGE = 14;
    public const int FILE_MESSAGE = 15;
    public const int GENERIC_REPOST = 16;
    public const int WEBSITE_REACTION = 17;
    public const int PICTURE = 20;
    public const int VIDEO = 21;
    public const int SHORT_FORM_VIDEO = 22;
    public const int CHANNEL_CREATION = 40;
    public const int CHANNEL_METADATA = 41;
    public const int CHANNEL_MESSAGE = 42;
    public const int CHANNEL_HIDE_MESSAGE = 43;
    public const int CHANNEL_MUTE_USER = 44;
    public const int VANISH_REQUEST = 62;
    public const int MLS_KEY_PACKAGE = 443;
    public const int MLS_WELCOME = 444;
    public const int MLS_GROUP_MESSAGE = 445;
    public const int POLL_RESPONSE = 1018;
    public const int OPENTIMESTAMPS = 1040;
    public const int GIFT_WRAP = 1059;
    public const int FILE_METADATA = 1063;
    public const int POLL = 1068;
    public const int COMMENT = 1111;
    public const int LIVE_CHAT_MESSAGE = 1311;
    public const int REPORTING = 1984;
    public const int COMMUNITY_POST_APPROVAL = 4550;
    public const int CASHU_RESERVED_TOKENS = 7374;
    public const int CASHU_WALLET_TOKENS = 7375;
    public const int CASHU_WALLET_HISTORY = 7376;
    public const int ZAP_GOAL = 9041;
    public const int ZAP_REQUEST = 9734;
    public const int ZAP_RECEIPT = 9735;
    public const int NUTZAP = 9321;
    public const int HIGHLIGHT = 9802;
    public const int CLIENT_AUTH = 22242;
    public const int WALLET_REQUEST = 23194;
    public const int WALLET_RESPONSE = 23195;
    public const int NOSTR_CONNECT = 24133;
    public const int BLOSSOM_BLOB = 24242;
    public const int HTTP_AUTH = 27235;
    public const int REPLACEABLE_EVENT_MIN = 10000;
    public const int MUTE_LIST = 10000;
    public const int PIN_LIST = 10001;
    public const int RELAY_LIST = 10002;
    public const int BOOKMARK_LIST = 10003;
    public const int COMMUNITIES_LIST = 10004;
    public const int PUBLIC_CHATS_LIST = 10005;
    public const int BLOCKED_RELAYS_LIST = 10006;
    public const int SEARCH_RELAYS_LIST = 10007;
    public const int PROFILE_BADGES = 10008;
    public const int USER_GROUPS_LIST = 10009;
    public const int EXTERNAL_IDENTITIES = 10011;
    public const int RELAY_FEEDS_LIST = 10012;
    public const int PRIVATE_EVENT_RELAY_LIST = 10013;
    public const int INTERESTS_LIST = 10015;
    public const int GIT_AUTHORS_LIST = 10017;
    public const int GIT_REPOSITORIES_LIST = 10018;
    public const int NUTZAP_MINT_RECOMMENDATION = 10019;
    public const int MEDIA_FOLLOWS_LIST = 10020;
    public const int CUSTOM_EMOJI_LIST = 10030;
    public const int DM_RELAY_LIST = 10050;
    public const int KEY_PACKAGE_RELAYS = 10051;
    public const int BLOSSOM_SERVER_LIST = 10063;
    public const int GOOD_WIKI_AUTHORS_LIST = 10101;
    public const int GOOD_WIKI_RELAYS_LIST = 10102;
    public const int RELAY_MONITOR_ANNOUNCEMENT = 10166;
    public const int ROOM_PRESENCE = 10312;
    public const int WALLET_INFO = 13194;
    public const int CASHU_WALLET = 17375;
    public const int REPLACEABLE_EVENT_MAX = 19999;
    public const int EPHEMERAL_EVENT_MIN = 20000;
    public const int EPHEMERAL_EVENT_MAX = 29999;
    public const int PARAMETERISED_REPLACEABLE_MIN = 30000;
    public const int FOLLOW_SET = 30000;
    public const int RELAY_SET = 30002;
    public const int BOOKMARK_SET = 30003;
    public const int CURATION_SET_ARTICLES = 30004;
    public const int CURATION_SET_VIDEO = 30005;
    public const int CURATION_SET_PICTURES = 30006;
    public const int KIND_MUTE_SET = 30007;
    public const int BADGE_SET = 30008;
    public const int BADGE_DEFINITION = 30009;
    public const int INTEREST_SET = 30015;
    public const int LONGFORM_CONTENT = 30023;
    public const int LONGFORM_CONTENT_DRAFT = 30024;
    public const int EMOJI_SET = 30030;
    public const int RELEASE_ARTIFACT_SET = 30063;
    public const int APPLICATION_SPECIFIC_DATA = 30078;
    public const int RELAY_DISCOVERY = 30166;
    public const int APP_CURATION_SET = 30267;
    public const int LIVE_EVENT = 30311;
    public const int INTERACTIVE_ROOM = 30312;
    public const int CONFERENCE_EVENT = 30313;
    public const int USER_STATUS = 30315;
    public const int SITE_MANIFEST = 30630;
    public const int WEB_PAGE = 30631;
    public const int WEB_PAGE_DRAFT = 30632;
    public const int WIKI_ARTICLE = 30818;
    public const int WIKI_REDIRECT = 30819;
    public const int DRAFT_EVENT = 31234;
    public const int CALENDAR_EVENT_DATE = 31922;
    public const int CALENDAR_EVENT_TIME = 31923;
    public const int CALENDAR = 31924;
    public const int CALENDAR_EVENT_RSVP = 31925;
    public const int HANDLER_RECOMMENDATION = 31989;
    public const int HANDLER_INFORMATION = 31990;
    public const int VIDEO_ADDRESSABLE = 34235;
    public const int SHORT_FORM_VIDEO_ADDRESSABLE = 34236;
    public const int COMMUNITY_DEFINITION = 34550;
    public const int CASHU_MINT_ANNOUNCEMENT = 38172;
    public const int FEDIMINT_ANNOUNCEMENT = 38173;
    public const int STARTER_PACK = 39089;
    public const int MEDIA_STARTER_PACK = 39092;
    public const int PARAMETERISED_REPLACEABLE_MAX = 39999;

    public function __construct(private int $kind)
    {
        if (!self::isValid($kind)) {
            throw new InvalidArgumentException('Event kind must be between 0 and 65535');
        }
    }

    private static function isValid(int $kind): bool
    {
        return $kind >= 0 && $kind <= 65535;
    }

    public function toInt(): int
    {
        return $this->kind;
    }

    public function isRegular(): bool
    {
        return $this->kind < self::REPLACEABLE_EVENT_MIN
            && self::METADATA !== $this->kind
            && self::FOLLOW_LIST !== $this->kind;
    }

    public function isReplaceable(): bool
    {
        return self::METADATA === $this->kind
            || self::FOLLOW_LIST === $this->kind
            || ($this->kind >= self::REPLACEABLE_EVENT_MIN && $this->kind <= self::REPLACEABLE_EVENT_MAX);
    }

    public function isEphemeral(): bool
    {
        return $this->kind >= self::EPHEMERAL_EVENT_MIN && $this->kind <= self::EPHEMERAL_EVENT_MAX;
    }

    public function isParameterisedReplaceable(): bool
    {
        return $this->kind >= self::PARAMETERISED_REPLACEABLE_MIN && $this->kind <= self::PARAMETERISED_REPLACEABLE_MAX;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind;
    }

    public function is(int $kind): bool
    {
        return $this->kind === $kind;
    }

    public static function fromInt(int $kind): self
    {
        return new self($kind);
    }

    public static function tryFromInt(int $kind): ?self
    {
        return self::isValid($kind) ? new self($kind) : null;
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->kind;
    }
}
