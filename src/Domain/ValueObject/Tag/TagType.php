<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use InvalidArgumentException;

final readonly class TagType
{
    public const EVENT = 'e';
    public const PUBKEY = 'p';
    public const NONCE = 'nonce';
    public const SUBJECT = 'subject';
    public const REFERENCE = 'r';
    public const TITLE = 'title';
    public const HASHTAG = 't';
    public const GEOHASH = 'g';
    public const IDENTIFIER = 'd';
    public const RELAY = 'relay';
    public const DELEGATION = 'delegation';
    public const DESCRIPTION = 'description';
    public const BOLT11 = 'bolt11';
    public const AMOUNT = 'amount';
    public const SENDER_PUBKEY = 'P';
    public const ROOT_EVENT = 'E';
    public const ROOT_ADDRESS = 'A';
    public const EXTERNAL_IDENTITY = 'I';
    public const ROOT_KIND = 'K';
    public const PARENT_KIND = 'k';
    public const MINT = 'u';
    public const PROOF = 'proof';
    public const UNIT = 'unit';

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

    public static function fromString(string $type): self
    {
        return new self($type);
    }

    public function __toString(): string
    {
        return $this->type;
    }
}
