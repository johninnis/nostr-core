<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;

final readonly class ReplyChain
{
    private const string EVENT = 'event';
    private const string ADDRESS = 'address';
    private const string EXTERNAL = 'external';

    public function __construct(
        private ?EventKind $kind,
        private EventReference|EventCoordinate|ExternalContentId|null $root,
        private EventReference|EventCoordinate|ExternalContentId|null $parent,
        private PublicKeyCollection $conversationParticipants,
        private EventReferenceCollection $mentionedEvents,
    ) {
    }

    // Deliberate: derived, never stored, so a chain cannot claim a reply it has no root or parent for; the kind decides whether the event threads at all — see ADR-0085
    public function isReply(): bool
    {
        return $this->threads() && ($this->hasRoot() || $this->hasParent());
    }

    public function getKind(): ?EventKind
    {
        return $this->kind;
    }

    public function getRoot(): EventReference|EventCoordinate|ExternalContentId|null
    {
        return $this->root;
    }

    public function getParent(): EventReference|EventCoordinate|ExternalContentId|null
    {
        return $this->parent;
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
        return null !== $this->root;
    }

    public function hasParent(): bool
    {
        return null !== $this->parent;
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
            'kind' => $this->kind?->toInt(),
            'is_reply' => $this->isReply(),
            'root' => self::referenceToArray($this->root),
            'parent' => self::referenceToArray($this->parent),
            'conversation_participants' => $this->conversationParticipants->toHexes(),
            'mentioned_events' => $this->mentionedEvents->toJsonArray(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $kind = JsonWireFormat::intField($data, 'kind');

        return new self(
            null === $kind ? null : EventKind::tryFromInt($kind),
            self::referenceFromArray($data['root'] ?? null),
            self::referenceFromArray($data['parent'] ?? null),
            PublicKeyCollection::fromHexValues($data['conversation_participants'] ?? null),
            EventReferenceCollection::fromArrays($data['mentioned_events'] ?? null),
        );
    }

    private function threads(): bool
    {
        return null === $this->kind || $this->kind->is(EventKind::TEXT_NOTE) || $this->kind->is(EventKind::COMMENT);
    }

    /**
     * @return array{type: string, value: array<string, mixed>}|null
     */
    private static function referenceToArray(EventReference|EventCoordinate|ExternalContentId|null $reference): ?array
    {
        return match (true) {
            null === $reference => null,
            $reference instanceof EventReference => ['type' => self::EVENT, 'value' => $reference->toArray()],
            $reference instanceof EventCoordinate => ['type' => self::ADDRESS, 'value' => $reference->toArray()],
            default => ['type' => self::EXTERNAL, 'value' => $reference->toArray()],
        };
    }

    private static function referenceFromArray(mixed $data): EventReference|EventCoordinate|ExternalContentId|null
    {
        if (!is_array($data) || !is_array($data['value'] ?? null)) {
            return null;
        }

        return match ($data['type'] ?? null) {
            self::EVENT => EventReference::tryFromArray($data['value']),
            self::ADDRESS => EventCoordinate::tryFromArray($data['value']),
            self::EXTERNAL => ExternalContentId::tryFromArray($data['value']),
            default => null,
        };
    }
}
