<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;

final readonly class ReplyChain
{
    public function __construct(
        private bool $isReply,
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
        return !$this->isReply;
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_reply' => $this->isReply,
            'is_root_post' => !$this->isReply,
            'root_event' => $this->rootEvent?->toArray(),
            'parent_event' => $this->parentEvent?->toArray(),
            'conversation_participants' => $this->conversationParticipants->toHexes(),
            'mentioned_events' => $this->mentionedEvents->toJsonArray(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['is_reply'] ?? false),
            isset($data['root_event']) && is_array($data['root_event']) ? EventReference::fromArray($data['root_event']) : null,
            isset($data['parent_event']) && is_array($data['parent_event']) ? EventReference::fromArray($data['parent_event']) : null,
            PublicKeyCollection::fromHexValues($data['conversation_participants'] ?? null),
            EventReferenceCollection::fromArrays($data['mentioned_events'] ?? null)
        );
    }
}
