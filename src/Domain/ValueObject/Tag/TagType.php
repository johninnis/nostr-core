<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Override;
use Stringable;

final readonly class TagType implements Stringable
{
    public const string EVENT = 'e';
    public const string PUBKEY = 'p';
    public const string REFERENCE = 'r';
    public const string QUOTE = 'q';
    public const string CHALLENGE = 'challenge';
    public const string TITLE = 'title';
    public const string HASHTAG = 't';
    public const string ADDRESSABLE = 'a';
    public const string IDENTIFIER = 'd';
    public const string RELAY = 'relay';
    public const string DESCRIPTION = 'description';
    public const string BOLT11 = 'bolt11';
    public const string AMOUNT = 'amount';
    public const string LNURL = 'lnurl';
    public const string ROOT_PUBKEY = 'P';
    public const string ROOT_EVENT = 'E';
    public const string ROOT_ADDRESS = 'A';
    public const string ROOT_EXTERNAL_CONTENT = 'I';
    public const string EXTERNAL_CONTENT = 'i';
    public const string ROOT_KIND = 'K';
    public const string PARENT_KIND = 'k';
    public const string PROOF = 'proof';
    public const string UNIT = 'unit';
    public const string METHOD = 'method';
    public const string PAYLOAD = 'payload';
    public const string EXPIRATION = 'expiration';
    public const string PROTECTED = '-';
    public const string SHA256 = 'x';
    public const string ORIGINAL_SHA256 = 'ox';
    public const string SERVER = 'server';
    public const string URL = 'u';
    public const string IMAGE = 'image';
    public const string SUMMARY = 'summary';
    public const string STATUS = 'status';
    public const string STREAMING = 'streaming';
    public const string PUBLISHED_AT = 'published_at';
    public const string CONTEXT = 'context';
    public const string COMMENT = 'comment';
    public const string IMETA = 'imeta';
    public const string FILE_URL = 'url';
    public const string MIME_TYPE = 'm';
    public const string SIZE = 'size';
    public const string DIMENSIONS = 'dim';
    public const string BLURHASH = 'blurhash';
    public const string THUMBNAIL = 'thumb';
    public const string ALT = 'alt';
    public const string FALLBACK = 'fallback';

    private function __construct(private string $type)
    {
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type;
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    // Deliberate: shortcuts for the tags built by hand, intentionally partial, beside the constants and fromString; do not collapse onto fromString or complete the set — see ADR-0129
    public static function event(): self
    {
        return self::fromString(self::EVENT);
    }

    public static function pubkey(): self
    {
        return self::fromString(self::PUBKEY);
    }

    public static function hashtag(): self
    {
        return self::fromString(self::HASHTAG);
    }

    public static function addressable(): self
    {
        return self::fromString(self::ADDRESSABLE);
    }

    public static function identifier(): self
    {
        return self::fromString(self::IDENTIFIER);
    }

    public static function description(): self
    {
        return self::fromString(self::DESCRIPTION);
    }

    public static function bolt11(): self
    {
        return self::fromString(self::BOLT11);
    }

    public static function amount(): self
    {
        return self::fromString(self::AMOUNT);
    }

    public static function lnurl(): self
    {
        return self::fromString(self::LNURL);
    }

    public static function rootEvent(): self
    {
        return self::fromString(self::ROOT_EVENT);
    }

    public static function rootExternalContent(): self
    {
        return self::fromString(self::ROOT_EXTERNAL_CONTENT);
    }

    public static function externalContent(): self
    {
        return self::fromString(self::EXTERNAL_CONTENT);
    }

    public static function rootKind(): self
    {
        return self::fromString(self::ROOT_KIND);
    }

    public static function parentKind(): self
    {
        return self::fromString(self::PARENT_KIND);
    }

    public static function proof(): self
    {
        return self::fromString(self::PROOF);
    }

    public static function unit(): self
    {
        return self::fromString(self::UNIT);
    }

    public static function method(): self
    {
        return self::fromString(self::METHOD);
    }

    public static function payload(): self
    {
        return self::fromString(self::PAYLOAD);
    }

    public static function expiration(): self
    {
        return self::fromString(self::EXPIRATION);
    }

    public static function protected(): self
    {
        return self::fromString(self::PROTECTED);
    }

    public static function sha256(): self
    {
        return self::fromString(self::SHA256);
    }

    public static function server(): self
    {
        return self::fromString(self::SERVER);
    }

    // Deliberate: NIP-01 says "Each tag is an array of one or more strings" and constrains no tag name, so the empty string is a tag name — see ADR-0129
    public static function tryFromString(mixed $type): ?self
    {
        return is_string($type) ? new self($type) : null;
    }

    public static function fromString(string $type): self
    {
        return new self($type);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
