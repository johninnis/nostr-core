<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Countable;
use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use stdClass;
use Stringable;

final readonly class Filter implements JsonSerializable, Stringable
{
    // Deliberate: the six ASCII whitespace characters written out, not \s, so both cores split a search into the same terms — see nostr-adrs ADR-0082
    private const string SEARCH_TERM_SEPARATOR = '/[\t\n\x{0B}\f\r ]+/u';
    private const string SEARCH_EXTENSION = '~^[a-z][a-z0-9_-]*:[^:/]+$~i';

    /** @var list<string>|null */
    private ?array $searchTerms;

    private function __construct(
        private ?EventIdCollection $ids,
        private ?PublicKeyCollection $authors,
        private ?EventKindCollection $kinds,
        private ?TagFilter $tags,
        private ?Timestamp $since,
        private ?Timestamp $until,
        private ?int $limit,
        private ?string $search,
    ) {
        $this->searchTerms = null === $this->search ? null : self::searchTermsOf($this->search);
    }

    // Deliberate: every invariant of the wire selector is decided here, and with* is not collapsed onto a nullable-override helper, because null means "field absent" — see ADR-0093
    public static function tryFrom(
        ?EventIdCollection $ids = null,
        ?PublicKeyCollection $authors = null,
        ?EventKindCollection $kinds = null,
        ?TagFilter $tags = null,
        ?Timestamp $since = null,
        ?Timestamp $until = null,
        ?int $limit = null,
        ?string $search = null,
    ): ?self {
        $valid = (null === $limit || $limit >= 0)
            && (null === $search || mb_check_encoding($search, 'UTF-8'));

        return $valid ? new self($ids, $authors, $kinds, $tags, $since, $until, $limit, $search) : null;
    }

    public static function from(
        ?EventIdCollection $ids = null,
        ?PublicKeyCollection $authors = null,
        ?EventKindCollection $kinds = null,
        ?TagFilter $tags = null,
        ?Timestamp $since = null,
        ?Timestamp $until = null,
        ?int $limit = null,
        ?string $search = null,
    ): self {
        return self::tryFrom($ids, $authors, $kinds, $tags, $since, $until, $limit, $search)
            ?? throw new InvalidArgumentException('A filter needs a non-negative limit and a UTF-8 search');
    }

    // Deliberate: an empty list or a since after its until matches nothing, never everything — see ADR-0094
    public function canMatch(): bool
    {
        return array_all([$this->ids, $this->authors, $this->kinds], static fn (?Countable $field): bool => null === $field || count($field) > 0)
            && ($this->tags?->canMatch() ?? true)
            && (null === $this->since || null === $this->until || !$this->since->isAfter($this->until));
    }

    public function matches(Event $event): bool
    {
        if (!$this->canMatch()) {
            return false;
        }

        if (null !== $this->ids && !$this->ids->contains($event->getId())) {
            return false;
        }

        if (null !== $this->authors && !$this->authors->contains($event->getPubkey())) {
            return false;
        }

        if (null !== $this->kinds && !$this->kinds->contains($event->getKind())) {
            return false;
        }

        if (null !== $this->tags && !$this->tags->matches($event->getTags())) {
            return false;
        }

        if (null !== $this->since && $event->getCreatedAt()->isBefore($this->since)) {
            return false;
        }

        if (null !== $this->until && $event->getCreatedAt()->isAfter($this->until)) {
            return false;
        }

        if (null !== $this->searchTerms && !$this->matchesSearch($event)) {
            return false;
        }

        return true;
    }

    public function getIds(): ?EventIdCollection
    {
        return $this->ids;
    }

    public function getAuthors(): ?PublicKeyCollection
    {
        return $this->authors;
    }

    public function getKinds(): ?EventKindCollection
    {
        return $this->kinds;
    }

    public function getTags(): ?TagFilter
    {
        return $this->tags;
    }

    public function getSince(): ?Timestamp
    {
        return $this->since;
    }

    public function getUntil(): ?Timestamp
    {
        return $this->until;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function withAuthors(PublicKeyCollection $authors): self
    {
        return self::from(
            ids: $this->ids,
            authors: $authors,
            kinds: $this->kinds,
            tags: $this->tags,
            since: $this->since,
            until: $this->until,
            limit: $this->limit,
            search: $this->search,
        );
    }

    public function withKinds(EventKindCollection $kinds): self
    {
        return self::from(
            ids: $this->ids,
            authors: $this->authors,
            kinds: $kinds,
            tags: $this->tags,
            since: $this->since,
            until: $this->until,
            limit: $this->limit,
            search: $this->search,
        );
    }

    public function withSince(?Timestamp $since): self
    {
        return self::from(
            ids: $this->ids,
            authors: $this->authors,
            kinds: $this->kinds,
            tags: $this->tags,
            since: $since,
            until: $this->until,
            limit: $this->limit,
            search: $this->search,
        );
    }

    public function withUntil(?Timestamp $until): self
    {
        return self::from(
            ids: $this->ids,
            authors: $this->authors,
            kinds: $this->kinds,
            tags: $this->tags,
            since: $this->since,
            until: $until,
            limit: $this->limit,
            search: $this->search,
        );
    }

    public function withLimit(?int $limit): self
    {
        return self::from(
            ids: $this->ids,
            authors: $this->authors,
            kinds: $this->kinds,
            tags: $this->tags,
            since: $this->since,
            until: $this->until,
            limit: $limit,
            search: $this->search,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filter = [];

        if (null !== $this->ids) {
            $filter['ids'] = $this->ids->toHexes();
        }

        if (null !== $this->authors) {
            $filter['authors'] = $this->authors->toHexes();
        }

        if (null !== $this->kinds) {
            $filter['kinds'] = $this->kinds->toInts();
        }

        if (null !== $this->tags) {
            foreach ($this->tags->toArray() as $key => $values) {
                $filter[$key] = $values;
            }
        }

        if (null !== $this->since) {
            $filter['since'] = $this->since->toInt();
        }

        if (null !== $this->until) {
            $filter['until'] = $this->until->toInt();
        }

        if (null !== $this->limit) {
            $filter['limit'] = $this->limit;
        }

        if (null !== $this->search) {
            $filter['search'] = $this->search;
        }

        return $filter;
    }

    /**
     * @return array<string, mixed>|stdClass
     */
    #[Override]
    public function jsonSerialize(): array|stdClass
    {
        return $this->toArray() ?: new stdClass();
    }

    public static function tryFromJson(string $json): ?self
    {
        $data = JsonWireFormat::decode($json);

        return [] === $data ? null : self::tryFromArray($data);
    }

    // Deliberate: a non-empty list is refused, because a filter is a JSON object and a list would read as the empty filter, which matches every event — see ADR-0112
    public static function tryFromArray(mixed $value): ?self
    {
        $data = [] === $value ? [] : JsonWireFormat::objectFields($value);
        if (null === $data) {
            return null;
        }

        $tags = TagFilter::tryFromArray($data);
        $ids = self::tryParseField($data, 'ids', EventIdCollection::tryFromArray(...));
        $authors = self::tryParseField($data, 'authors', PublicKeyCollection::tryFromArray(...));
        $kinds = self::tryParseField($data, 'kinds', EventKindCollection::tryFromArray(...));
        $since = self::tryParseField($data, 'since', static fn (mixed $since): ?Timestamp => is_int($since) ? Timestamp::tryFromInt($since) : null);
        $until = self::tryParseField($data, 'until', static fn (mixed $until): ?Timestamp => is_int($until) ? Timestamp::tryFromInt($until) : null);
        $limit = self::tryParseField($data, 'limit', static fn (mixed $limit): ?int => is_int($limit) ? $limit : null);
        $search = self::tryParseField($data, 'search', static fn (mixed $search): ?string => is_string($search) ? $search : null);

        if (null === $tags || false === $ids || false === $authors || false === $kinds || false === $since || false === $until
            || false === $limit || false === $search
        ) {
            return null;
        }

        return self::tryFrom($ids, $authors, $kinds, $tags->isEmpty() ? null : $tags, $since, $until, $limit, $search);
    }

    /**
     * @template TField of object|int|string
     *
     * @param array<array-key, mixed>        $data
     * @param callable(mixed): (TField|null) $tryParse
     *
     * @return TField|false|null
     */
    private static function tryParseField(array $data, string $key, callable $tryParse): object|int|string|false|null
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        return $tryParse($data[$key]) ?? false;
    }

    /**
     * @return list<string>
     */
    // Deliberate: a key:value extension is ignored, as NIP-50 asks of extensions a matcher does not support — see nostr-adrs ADR-0082
    private static function searchTermsOf(string $search): array
    {
        $pieces = preg_split(self::SEARCH_TERM_SEPARATOR, mb_strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($pieces, static fn (string $piece): bool => 1 !== preg_match(self::SEARCH_EXTENSION, $piece)));
    }

    private function matchesSearch(Event $event): bool
    {
        $content = mb_strtolower((string) $event->getContent());

        return array_all($this->searchTerms ?? [], static fn (string $term): bool => str_contains($content, $term));
    }

    #[Override]
    public function __toString(): string
    {
        return JsonWireFormat::encode($this->jsonSerialize(), JsonWireFormat::MESSAGE);
    }
}
