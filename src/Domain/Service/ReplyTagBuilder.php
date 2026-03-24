<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;

final class ReplyTagBuilder
{
    public function build(Event $replyTo, ?Event $root = null): TagCollection
    {
        return $this->buildFromValues(
            $replyTo->getId(),
            $replyTo->getPubkey(),
            $root?->getId(),
            $root?->getPubkey()
        );
    }

    public function buildFromValues(
        EventId $replyToId,
        PublicKey $replyToAuthor,
        ?EventId $rootId = null,
        ?PublicKey $rootAuthor = null,
    ): TagCollection {
        $tags = [];

        $effectiveRootId = $rootId ?? $replyToId;
        $effectiveRootAuthor = $rootAuthor ?? $replyToAuthor;

        $tags[] = Tag::event($effectiveRootId->toHex(), null, 'root');

        if (null !== $rootId && !$rootId->equals($replyToId)) {
            $tags[] = Tag::event($replyToId->toHex(), null, 'reply');
        }

        $tags[] = Tag::pubkey($effectiveRootAuthor->toHex());

        if (null !== $rootAuthor && !$rootAuthor->equals($replyToAuthor)) {
            $tags[] = Tag::pubkey($replyToAuthor->toHex());
        }

        return new TagCollection($tags);
    }
}
