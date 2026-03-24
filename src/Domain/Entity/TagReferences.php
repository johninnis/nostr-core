<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Reference\PubkeyReference;
use Innis\Nostr\Core\Domain\ValueObject\Reference\RelayReference;
use InvalidArgumentException;

final readonly class TagReferences
{
    public function __construct(
        private array $events,
        private array $pubkeys,
        private array $quotes,
        private array $addressable,
        private array $relays,
        private array $challenges,
    ) {
        foreach ($this->events as $event) {
            if (!$event instanceof EventReference) {
                throw new InvalidArgumentException('All events must be EventReference instances');
            }
        }

        foreach ($this->pubkeys as $pubkey) {
            if (!$pubkey instanceof PubkeyReference) {
                throw new InvalidArgumentException('All pubkeys must be PubkeyReference instances');
            }
        }

        foreach ($this->quotes as $quote) {
            if (!$quote instanceof EventReference) {
                throw new InvalidArgumentException('All quotes must be EventReference instances');
            }
        }

        foreach ($this->addressable as $coordinate) {
            if (!$coordinate instanceof EventCoordinate) {
                throw new InvalidArgumentException('All addressable must be EventCoordinate instances');
            }
        }

        foreach ($this->relays as $relay) {
            if (!$relay instanceof RelayReference) {
                throw new InvalidArgumentException('All relays must be RelayReference instances');
            }
        }

        foreach ($this->challenges as $challenge) {
            if (!is_string($challenge)) {
                throw new InvalidArgumentException('All challenges must be strings');
            }
        }
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getPubkeys(): array
    {
        return $this->pubkeys;
    }

    public function getQuotes(): array
    {
        return $this->quotes;
    }

    public function getAddressable(): array
    {
        return $this->addressable;
    }

    public function getRelays(): array
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
                $this->events
            ),
            'pubkeys' => array_map(
                static fn (PubkeyReference $ref) => $ref->toArray(),
                $this->pubkeys
            ),
            'quotes' => array_map(
                static fn (EventReference $ref) => $ref->toArray(),
                $this->quotes
            ),
            'addressable' => array_map(
                static fn (EventCoordinate $coord) => $coord->toArray(),
                $this->addressable
            ),
            'relays' => array_map(
                static fn (RelayReference $ref) => $ref->toArray(),
                $this->relays
            ),
            'challenges' => $this->challenges,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                static fn (array $ref) => EventReference::fromArray($ref),
                $data['events'] ?? []
            ),
            array_map(
                static fn (array $ref) => PubkeyReference::fromArray($ref),
                $data['pubkeys'] ?? []
            ),
            array_map(
                static fn (array $ref) => EventReference::fromArray($ref),
                $data['quotes'] ?? []
            ),
            array_values(array_filter(array_map(
                static fn (array $ref) => EventCoordinate::fromArray($ref),
                $data['addressable'] ?? []
            ))),
            array_map(
                static fn (array $ref) => RelayReference::fromArray($ref),
                $data['relays'] ?? []
            ),
            $data['challenges'] ?? []
        );
    }

    public static function empty(): self
    {
        return new self([], [], [], [], [], []);
    }
}
