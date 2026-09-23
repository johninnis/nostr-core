<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Collection\ChallengeCollection;
use Innis\Nostr\Core\Domain\Collection\EventCoordinateCollection;
use Innis\Nostr\Core\Domain\Collection\EventReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\PubkeyReferenceCollection;
use Innis\Nostr\Core\Domain\Collection\RelayReferenceCollection;

final readonly class TagReferences
{
    public function __construct(
        private EventReferenceCollection $events,
        private PubkeyReferenceCollection $pubkeys,
        private EventReferenceCollection $quotes,
        private EventCoordinateCollection $addressable,
        private RelayReferenceCollection $relays,
        private ChallengeCollection $challenges,
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

    public function getChallenges(): ChallengeCollection
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
            'challenges' => $this->challenges->toStrings(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            EventReferenceCollection::fromArrays($data['events'] ?? null),
            PubkeyReferenceCollection::fromArrays($data['pubkeys'] ?? null),
            EventReferenceCollection::fromArrays($data['quotes'] ?? null),
            EventCoordinateCollection::fromArrays($data['addressable'] ?? null),
            RelayReferenceCollection::fromArrays($data['relays'] ?? null),
            ChallengeCollection::fromStrings($data['challenges'] ?? null),
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
            new ChallengeCollection(),
        );
    }
}
