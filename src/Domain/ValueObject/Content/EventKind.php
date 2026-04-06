<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use InvalidArgumentException;

final readonly class EventKind
{
    public const METADATA = 0;
    public const TEXT_NOTE = 1;
    public const RECOMMEND_SERVER = 2;
    public const FOLLOW_LIST = 3;
    public const ENCRYPTED_DIRECT_MESSAGE = 4;
    public const EVENT_DELETION = 5;
    public const REPOST = 6;
    public const REACTION = 7;
    public const SEAL = 13;
    public const PRIVATE_MESSAGE = 14;
    public const GENERIC_REPOST = 16;
    public const VIDEO = 21;
    public const SHORT_FORM_VIDEO = 22;
    public const CHANNEL_CREATION = 40;
    public const CHANNEL_METADATA = 41;
    public const CHANNEL_MESSAGE = 42;
    public const CHANNEL_HIDE_MESSAGE = 43;
    public const CHANNEL_MUTE_USER = 44;
    public const MLS_KEY_PACKAGE = 443;
    public const MLS_WELCOME = 444;
    public const MLS_GROUP_MESSAGE = 445;
    public const GIFT_WRAP = 1059;
    public const COMMENT = 1111;
    public const ZAP_REQUEST = 9734;
    public const ZAP_RECEIPT = 9735;
    public const NUTZAP = 9321;
    public const HIGHLIGHT = 9802;
    public const CLIENT_AUTH = 22242;
    public const NOSTR_CONNECT = 24133;
    public const HTTP_AUTH = 27235;
    public const REPLACEABLE_EVENT_MIN = 10000;
    public const MUTE_LIST = 10000;
    public const PIN_LIST = 10001;
    public const RELAY_LIST = 10002;
    public const BOOKMARK_LIST = 10003;
    public const COMMUNITIES_LIST = 10004;
    public const PUBLIC_CHATS_LIST = 10005;
    public const BLOCKED_RELAYS_LIST = 10006;
    public const SEARCH_RELAYS_LIST = 10007;
    public const USER_GROUPS_LIST = 10009;
    public const RELAY_FEEDS_LIST = 10012;
    public const INTERESTS_LIST = 10015;
    public const GIT_AUTHORS_LIST = 10017;
    public const GIT_REPOSITORIES_LIST = 10018;
    public const MEDIA_FOLLOWS_LIST = 10020;
    public const CUSTOM_EMOJI_LIST = 10030;
    public const DM_RELAY_LIST = 10050;
    public const KEY_PACKAGE_RELAYS = 10051;
    public const GOOD_WIKI_AUTHORS_LIST = 10101;
    public const GOOD_WIKI_RELAYS_LIST = 10102;
    public const REPLACEABLE_EVENT_MAX = 19999;
    public const EPHEMERAL_EVENT_MIN = 20000;
    public const EPHEMERAL_EVENT_MAX = 29999;
    public const PARAMETERISED_REPLACEABLE_MIN = 30000;
    public const FOLLOW_SET = 30000;
    public const RELAY_SET = 30002;
    public const BOOKMARK_SET = 30003;
    public const CURATION_SET_ARTICLES = 30004;
    public const CURATION_SET_VIDEO = 30005;
    public const CURATION_SET_PICTURES = 30006;
    public const KIND_MUTE_SET = 30007;
    public const INTEREST_SET = 30015;
    public const LONGFORM_CONTENT = 30023;
    public const EMOJI_SET = 30030;
    public const RELEASE_ARTIFACT_SET = 30063;
    public const APPLICATION_SPECIFIC_DATA = 30078;
    public const APP_CURATION_SET = 30267;
    public const LIVE_EVENT = 30311;
    public const CALENDAR = 31924;
    public const STARTER_PACK = 39089;
    public const MEDIA_STARTER_PACK = 39092;
    public const PARAMETERISED_REPLACEABLE_MAX = 39999;

    public function __construct(private int $kind)
    {
        if ($this->kind < 0 || $this->kind > 65535) {
            throw new InvalidArgumentException('Event kind must be between 0 and 65535');
        }
    }

    public function toInt(): int
    {
        return $this->kind;
    }

    public function isRegular(): bool
    {
        return $this->kind < 10000;
    }

    public function isReplaceable(): bool
    {
        return $this->kind >= self::REPLACEABLE_EVENT_MIN && $this->kind <= self::REPLACEABLE_EVENT_MAX;
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

    public static function metadata(): self
    {
        return new self(self::METADATA);
    }

    public static function textNote(): self
    {
        return new self(self::TEXT_NOTE);
    }

    public static function followList(): self
    {
        return new self(self::FOLLOW_LIST);
    }

    public static function relayList(): self
    {
        return new self(self::RELAY_LIST);
    }

    public static function muteList(): self
    {
        return new self(self::MUTE_LIST);
    }

    public static function dmRelayList(): self
    {
        return new self(self::DM_RELAY_LIST);
    }

    public static function encryptedDirectMessage(): self
    {
        return new self(self::ENCRYPTED_DIRECT_MESSAGE);
    }

    public static function eventDeletion(): self
    {
        return new self(self::EVENT_DELETION);
    }

    public static function repost(): self
    {
        return new self(self::REPOST);
    }

    public static function reaction(): self
    {
        return new self(self::REACTION);
    }

    public static function comment(): self
    {
        return new self(self::COMMENT);
    }

    public static function longformContent(): self
    {
        return new self(self::LONGFORM_CONTENT);
    }

    public static function liveEvent(): self
    {
        return new self(self::LIVE_EVENT);
    }

    public static function video(): self
    {
        return new self(self::VIDEO);
    }

    public static function shortFormVideo(): self
    {
        return new self(self::SHORT_FORM_VIDEO);
    }

    public static function clientAuth(): self
    {
        return new self(self::CLIENT_AUTH);
    }

    public static function seal(): self
    {
        return new self(self::SEAL);
    }

    public static function privateMessage(): self
    {
        return new self(self::PRIVATE_MESSAGE);
    }

    public static function giftWrap(): self
    {
        return new self(self::GIFT_WRAP);
    }

    public static function nostrConnect(): self
    {
        return new self(self::NOSTR_CONNECT);
    }

    public static function httpAuth(): self
    {
        return new self(self::HTTP_AUTH);
    }

    public static function zapReceipt(): self
    {
        return new self(self::ZAP_RECEIPT);
    }

    public static function nutzap(): self
    {
        return new self(self::NUTZAP);
    }

    public static function fromInt(int $kind): self
    {
        return new self($kind);
    }

    public function __toString(): string
    {
        return (string) $this->kind;
    }
}
