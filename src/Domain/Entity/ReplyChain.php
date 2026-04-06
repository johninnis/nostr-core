<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final readonly class ReplyChain
{
    public function __construct(
        private bool $isReply,
        private bool $isRootPost,
        private ?EventReference $rootEvent,
        private ?EventReference $parentEvent,
        private array $conversationParticipants,
        private array $mentionedEvents,
    ) {
        foreach ($this->conversationParticipants as $participant) {
            if (!$participant instanceof PublicKey) {
                throw new InvalidArgumentException('All conversation participants must be PublicKey instances');
            }
        }

        foreach ($this->mentionedEvents as $event) {
            if (!$event instanceof EventReference) {
                throw new InvalidArgumentException('All mentioned events must be EventReference instances');
            }
        }
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

    public function getConversationParticipants(): array
    {
        return $this->conversationParticipants;
    }

    public function getMentionedEvents(): array
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
        return count($this->conversationParticipants);
    }

    public function getMentionedEventCount(): int
    {
        return count($this->mentionedEvents);
    }

    public function toArray(): array
    {
        return [
            'is_reply' => $this->isReply,
            'is_root_post' => $this->isRootPost,
            'root_event' => $this->rootEvent?->toArray(),
            'parent_event' => $this->parentEvent?->toArray(),
            'conversation_participants' => array_map(
                static fn (PublicKey $key) => $key->toHex(),
                $this->conversationParticipants
            ),
            'mentioned_events' => array_map(
                static fn (EventReference $ref) => $ref->toArray(),
                $this->mentionedEvents
            ),
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
            $data['is_reply'],
            $data['is_root_post'],
            isset($data['root_event']) ? EventReference::fromArray($data['root_event']) : null,
            isset($data['parent_event']) ? EventReference::fromArray($data['parent_event']) : null,
            $participants,
            $mentionedEvents
        );
    }
}
