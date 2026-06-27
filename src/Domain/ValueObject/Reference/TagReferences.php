<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;

final readonly class TagReferences
{
    /**
     * @param list<string> $challenges
     */
    public function __construct(
        private EventReferenceCollection $events,
        private PubkeyReferenceCollection $pubkeys,
        private EventReferenceCollection $quotes,
        private EventCoordinateCollection $addressable,
        private RelayReferenceCollection $relays,
        private array $challenges,
    ) {
    }

    public function getEvents(): EventReferenceCollection
    {
        return $this->events;
    }

    public function getPubkeys(): PubkeyReferenceCollection
    {
        return $this->pubkeys;
    }

    public function getQuotes(): EventReferenceCollection
    {
        return $this->quotes;
    }

    public function getAddressable(): EventCoordinateCollection
    {
        return $this->addressable;
    }

    public function getRelays(): RelayReferenceCollection
    {
        return $this->relays;
    }

    /**
     * @return list<string>
     */
    public function getChallenges(): array
    {
        return $this->challenges;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'events' => $this->events->toJsonArray(),
            'pubkeys' => $this->pubkeys->toJsonArray(),
            'quotes' => $this->quotes->toJsonArray(),
            'addressable' => $this->addressable->toJsonArray(),
            'relays' => $this->relays->toJsonArray(),
            'challenges' => $this->challenges,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $challenges = is_array($data['challenges'] ?? null) ? $data['challenges'] : [];

        return new self(
            EventReferenceCollection::fromArrays($data['events'] ?? null),
            PubkeyReferenceCollection::fromArrays($data['pubkeys'] ?? null),
            EventReferenceCollection::fromArrays($data['quotes'] ?? null),
            EventCoordinateCollection::fromArrays($data['addressable'] ?? null),
            RelayReferenceCollection::fromArrays($data['relays'] ?? null),
            array_values(array_filter($challenges, static fn (mixed $challenge): bool => is_string($challenge))),
        );
    }

    public static function empty(): self
    {
        return new self(
            new EventReferenceCollection(),
            new PubkeyReferenceCollection(),
            new EventReferenceCollection(),
            new EventCoordinateCollection(),
            new RelayReferenceCollection(),
            []
        );
    }
}
