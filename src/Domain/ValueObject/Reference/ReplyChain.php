<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

final readonly class ReplyChain
{
    public function __construct(
        private bool $isReply,
        private bool $isRootPost,
        private ?EventReference $rootEvent,
        private ?EventReference $parentEvent,
        private PublicKeyCollection $conversationParticipants,
        private EventReferenceCollection $mentionedEvents,
    ) {
    }

    public function isReply(): bool
    {
        return $this->isReply;
    }

    public function isRootPost(): bool
    {
        return $this->isRootPost;
    }

    public function getRootEvent(): ?EventReference
    {
        return $this->rootEvent;
    }

    public function getParentEvent(): ?EventReference
    {
        return $this->parentEvent;
    }

    public function getConversationParticipants(): PublicKeyCollection
    {
        return $this->conversationParticipants;
    }

    public function getMentionedEvents(): EventReferenceCollection
    {
        return $this->mentionedEvents;
    }

    public function hasRoot(): bool
    {
        return null !== $this->rootEvent;
    }

    public function hasParent(): bool
    {
        return null !== $this->parentEvent;
    }

    public function getParticipantCount(): int
    {
        return $this->conversationParticipants->count();
    }

    public function getMentionedEventCount(): int
    {
        return $this->mentionedEvents->count();
    }

    public function toArray(): array
    {
        return [
            'is_reply' => $this->isReply,
            'is_root_post' => $this->isRootPost,
            'root_event' => $this->rootEvent?->toArray(),
            'parent_event' => $this->parentEvent?->toArray(),
            'conversation_participants' => $this->conversationParticipants->toHexes(),
            'mentioned_events' => $this->mentionedEvents->toJsonArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $participants = [];
        if (isset($data['conversation_participants']) && is_array($data['conversation_participants'])) {
            $participants = array_values(array_filter(array_map(
                static fn (mixed $hex) => is_string($hex) ? PublicKey::fromHex($hex) : null,
                $data['conversation_participants']
            )));
        }

        $mentionedEvents = [];
        if (isset($data['mentioned_events']) && is_array($data['mentioned_events'])) {
            $mentionedEvents = array_values(array_filter(array_map(
                static fn (mixed $eventData) => is_array($eventData) ? EventReference::fromArray($eventData) : null,
                $data['mentioned_events']
            )));
        }

        return new self(
            (bool) ($data['is_reply'] ?? false),
            (bool) ($data['is_root_post'] ?? false),
            isset($data['root_event']) && is_array($data['root_event']) ? EventReference::fromArray($data['root_event']) : null,
            isset($data['parent_event']) && is_array($data['parent_event']) ? EventReference::fromArray($data['parent_event']) : null,
            new PublicKeyCollection($participants),
            new EventReferenceCollection($mentionedEvents)
        );
    }
}
