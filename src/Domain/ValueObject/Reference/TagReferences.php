<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinateCollection;
use InvalidArgumentException;

final readonly class TagReferences
{
    /**
     * @param list<mixed> $challenges
     */
    public function __construct(
        private EventReferenceCollection $events,
        private PubkeyReferenceCollection $pubkeys,
        private EventReferenceCollection $quotes,
        private EventCoordinateCollection $addressable,
        private RelayReferenceCollection $relays,
        private array $challenges,
    ) {
        if (!array_all($this->challenges, static fn (mixed $challenge): bool => is_string($challenge))) {
            throw new InvalidArgumentException('All challenges must be strings');
        }
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

    public function getChallenges(): array
    {
        return $this->challenges;
    }

    public function toArray(): array
    {
        return [
            'events' => array_map(
                static fn (EventReference $ref) => $ref->toArray(),
                $this->events->toArray()
            ),
            'pubkeys' => array_map(
                static fn (PubkeyReference $ref) => $ref->toArray(),
                $this->pubkeys->toArray()
            ),
            'quotes' => array_map(
                static fn (EventReference $ref) => $ref->toArray(),
                $this->quotes->toArray()
            ),
            'addressable' => array_map(
                static fn (EventCoordinate $coord) => $coord->toArray(),
                $this->addressable->toArray()
            ),
            'relays' => array_map(
                static fn (RelayReference $ref) => $ref->toArray(),
                $this->relays->toArray()
            ),
            'challenges' => $this->challenges,
        ];
    }

    public static function fromArray(array $data): self
    {
        $challenges = is_array($data['challenges'] ?? null) ? $data['challenges'] : [];

        return new self(
            new EventReferenceCollection(self::parseEventReferences($data['events'] ?? null)),
            new PubkeyReferenceCollection(array_values(array_filter(array_map(
                static fn (mixed $ref) => is_array($ref) ? PubkeyReference::fromArray($ref) : null,
                is_array($data['pubkeys'] ?? null) ? $data['pubkeys'] : [],
            )))),
            new EventReferenceCollection(self::parseEventReferences($data['quotes'] ?? null)),
            new EventCoordinateCollection(array_values(array_filter(array_map(
                static fn (mixed $ref) => is_array($ref) ? EventCoordinate::fromArray($ref) : null,
                is_array($data['addressable'] ?? null) ? $data['addressable'] : [],
            )))),
            new RelayReferenceCollection(array_values(array_filter(array_map(
                static fn (mixed $ref) => is_array($ref) ? RelayReference::fromArray($ref) : null,
                is_array($data['relays'] ?? null) ? $data['relays'] : [],
            )))),
            array_values(array_filter($challenges, static fn (mixed $challenge): bool => is_string($challenge))),
        );
    }

    /**
     * @return list<EventReference>
     */
    private static function parseEventReferences(mixed $references): array
    {
        if (!is_array($references)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $ref) => is_array($ref) ? EventReference::fromArray($ref) : null,
            $references,
        )));
    }

    public static function empty(): self
    {
        return new self(
            EventReferenceCollection::empty(),
            PubkeyReferenceCollection::empty(),
            EventReferenceCollection::empty(),
            EventCoordinateCollection::empty(),
            RelayReferenceCollection::empty(),
            []
        );
    }
}
