<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;

final class ReplyTagBuilder
{
    private function __construct()
    {
    }

    public static function build(Event $replyTo, ?Event $root = null): TagCollection
    {
        return self::buildFromValues(
            $replyTo->getId(),
            $replyTo->getPubkey(),
            $root?->getId(),
            $root?->getPubkey()
        );
    }

    public static function buildFromValues(
        EventId $replyToId,
        PublicKey $replyToAuthor,
        ?EventId $rootId = null,
        ?PublicKey $rootAuthor = null,
    ): TagCollection {
        $tags = [];

        $effectiveRootId = $rootId ?? $replyToId;
        $effectiveRootAuthor = $rootAuthor ?? $replyToAuthor;

        $tags[] = Tag::event($effectiveRootId->toHex(), null, Nip10Marker::Root->value);

        if (null !== $rootId && !$rootId->equals($replyToId)) {
            $tags[] = Tag::event($replyToId->toHex(), null, Nip10Marker::Reply->value);
        }

        $tags[] = Tag::pubkey($effectiveRootAuthor->toHex());

        if (null !== $rootAuthor && !$rootAuthor->equals($replyToAuthor)) {
            $tags[] = Tag::pubkey($replyToAuthor->toHex());
        }

        return new TagCollection($tags);
    }
}
