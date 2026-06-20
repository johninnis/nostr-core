<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReferenceCollection;
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
        return new self(
            new EventReferenceCollection(array_map(
                static fn (array $ref) => EventReference::fromArray($ref),
                $data['events'] ?? []
            )),
            new PubkeyReferenceCollection(array_map(
                static fn (array $ref) => PubkeyReference::fromArray($ref),
                $data['pubkeys'] ?? []
            )),
            new EventReferenceCollection(array_map(
                static fn (array $ref) => EventReference::fromArray($ref),
                $data['quotes'] ?? []
            )),
            new EventCoordinateCollection(array_values(array_filter(array_map(
                static fn (array $ref) => EventCoordinate::fromArray($ref),
                $data['addressable'] ?? []
            )))),
            new RelayReferenceCollection(array_map(
                static fn (array $ref) => RelayReference::fromArray($ref),
                $data['relays'] ?? []
            )),
            $data['challenges'] ?? []
        );
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
