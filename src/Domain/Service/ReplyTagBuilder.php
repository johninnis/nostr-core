<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;

final class ReplyTagBuilder
{
    private function __construct()
    {
    }

    public static function buildTags(Event $replyTo, ?Event $root = null): TagCollection
    {
        $effectiveRoot = $root ?? $replyTo;

        $tags = [Tag::event($effectiveRoot->getId(), null, Nip10Marker::Root->value)];

        if (null !== $root && !$root->getId()->equals($replyTo->getId())) {
            $tags[] = Tag::event($replyTo->getId(), null, Nip10Marker::Reply->value);
        }

        $tags[] = Tag::pubkey($effectiveRoot->getPubkey());

        if (null !== $root && !$root->getPubkey()->equals($replyTo->getPubkey())) {
            $tags[] = Tag::pubkey($replyTo->getPubkey());
        }

        return new TagCollection($tags);
    }
}
