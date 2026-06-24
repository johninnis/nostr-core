<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use InvalidArgumentException;
use Override;
use Stringable;

// Deliberate: a value object, not a backed enum, because NIP-01 tag names are an open set any string can join — see ADR-0006
final readonly class TagType implements Stringable
{
    public const string EVENT = 'e';
    public const string PUBKEY = 'p';
    public const string NONCE = 'nonce';
    public const string SUBJECT = 'subject';
    public const string REFERENCE = 'r';
    public const string QUOTE = 'q';
    public const string CHALLENGE = 'challenge';
    public const string TITLE = 'title';
    public const string HASHTAG = 't';
    public const string GEOHASH = 'g';
    public const string ADDRESSABLE = 'a';
    public const string IDENTIFIER = 'd';
    public const string RELAY = 'relay';
    public const string DELEGATION = 'delegation';
    public const string DESCRIPTION = 'description';
    public const string BOLT11 = 'bolt11';
    public const string AMOUNT = 'amount';
    public const string SENDER_PUBKEY = 'P';
    public const string ROOT_EVENT = 'E';
    public const string ROOT_ADDRESS = 'A';
    public const string EXTERNAL_IDENTITY = 'I';
    public const string ROOT_KIND = 'K';
    public const string PARENT_KIND = 'k';
    public const string MINT = 'u';
    public const string PROOF = 'proof';
    public const string UNIT = 'unit';
    public const string METHOD = 'method';
    public const string PAYLOAD = 'payload';
    public const string EXPIRATION = 'expiration';
    public const string PROTECTED = '-';
    public const string URL = 'u';
    public const string IMAGE = 'image';
    public const string SUMMARY = 'summary';
    public const string STATUS = 'status';
    public const string STREAMING = 'streaming';
    public const string PUBLISHED_AT = 'published_at';
    public const string CONTEXT = 'context';
    public const string COMMENT = 'comment';

    public function __construct(private string $type)
    {
        if ('' === $this->type) {
            throw new InvalidArgumentException('Tag type cannot be empty');
        }
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type;
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    // Deliberate: convenience factories for commonly-built tags, alongside constants and fromString; do not collapse onto fromString — see ADR-0023
    public static function event(): self
    {
        return new self(self::EVENT);
    }

    public static function pubkey(): self
    {
        return new self(self::PUBKEY);
    }

    public static function hashtag(): self
    {
        return new self(self::HASHTAG);
    }

    public static function addressable(): self
    {
        return new self(self::ADDRESSABLE);
    }

    public static function identifier(): self
    {
        return new self(self::IDENTIFIER);
    }

    public static function description(): self
    {
        return new self(self::DESCRIPTION);
    }

    public static function bolt11(): self
    {
        return new self(self::BOLT11);
    }

    public static function amount(): self
    {
        return new self(self::AMOUNT);
    }

    public static function senderPubkey(): self
    {
        return new self(self::SENDER_PUBKEY);
    }

    public static function rootEvent(): self
    {
        return new self(self::ROOT_EVENT);
    }

    public static function rootAddress(): self
    {
        return new self(self::ROOT_ADDRESS);
    }

    public static function externalIdentity(): self
    {
        return new self(self::EXTERNAL_IDENTITY);
    }

    public static function rootKind(): self
    {
        return new self(self::ROOT_KIND);
    }

    public static function parentKind(): self
    {
        return new self(self::PARENT_KIND);
    }

    public static function mint(): self
    {
        return new self(self::MINT);
    }

    public static function proof(): self
    {
        return new self(self::PROOF);
    }

    public static function unit(): self
    {
        return new self(self::UNIT);
    }

    public static function method(): self
    {
        return new self(self::METHOD);
    }

    public static function payload(): self
    {
        return new self(self::PAYLOAD);
    }

    public static function expiration(): self
    {
        return new self(self::EXPIRATION);
    }

    public static function protected(): self
    {
        return new self(self::PROTECTED);
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
