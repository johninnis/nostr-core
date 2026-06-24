<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
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
            'events' => $this->events->toJsonArray(),
            'pubkeys' => $this->pubkeys->toJsonArray(),
            'quotes' => $this->quotes->toJsonArray(),
            'addressable' => $this->addressable->toJsonArray(),
            'relays' => $this->relays->toJsonArray(),
            'challenges' => $this->challenges,
        ];
    }

    public static function fromArray(array $data): self
    {
        $challenges = is_array($data['challenges'] ?? null) ? $data['challenges'] : [];

        return new self(
            new EventReferenceCollection(self::parseList($data['events'] ?? null, EventReference::fromArray(...))),
            new PubkeyReferenceCollection(self::parseList($data['pubkeys'] ?? null, PubkeyReference::fromArray(...))),
            new EventReferenceCollection(self::parseList($data['quotes'] ?? null, EventReference::fromArray(...))),
            new EventCoordinateCollection(self::parseList($data['addressable'] ?? null, EventCoordinate::fromArray(...))),
            new RelayReferenceCollection(self::parseList($data['relays'] ?? null, RelayReference::fromArray(...))),
            array_values(array_filter($challenges, static fn (mixed $challenge): bool => is_string($challenge))),
        );
    }

    /**
     * @template T of object
     *
     * @param callable(array<mixed>): (T|null) $parse
     *
     * @return list<T>
     */
    private static function parseList(mixed $data, callable $parse): array
    {
        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item) => is_array($item) ? $parse($item) : null,
            $data,
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
