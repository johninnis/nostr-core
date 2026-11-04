# 145. `DeletionCoverage` is the one judgement of a deletion, and stores find requests by filter

## Status

Accepted

## Context

The package built deletion requests (`RumourFactory::createDeletion`) and read their targets (`TagCollection::getEventIds`, `::getCoordinates`), but left the judgement, which events a request deletes, to every caller. nostr-relay wrote it as `ActingKeys::canDelete` and a coordinate filter in `EventDeletionProcessor`, hubstr-relay wrote it in SQL, and two TypeScript apps wrote it twice more. Every copy applied a request only when the request arrived, except one, and so kept an event that arrived after the request that deleted it.

A relay has to apply a request to an event that arrives after it (nostr-adrs ADR-0126). It already holds its requests, as stored kind 5 events, so what it needs is a way to fetch the ones that could delete an arriving event and a judgement to apply to each.

The obvious further step is an in-memory index of requests here, for a party that cannot query a store as an event arrives. It is left out: which requests a party holds, where it loads them from and how long it keeps them are decisions about that party's state, not about the protocol, and a relay already holds them in its store.

A store that applies the rule in SQL cannot call a PHP function from inside its statement, so a single function cannot be the only statement of the rule there.

## Decision

- `DeletionCoverage::covers($deletion, $target)` is the package's one statement of nostr-adrs ADR-0124 with ADR-0093: static, pure and total, reading both events as given. The gift-wrap reach of an `e` tag is part of it, not an option, because every party applies it.
- `DeletionCoverage::deletedCoordinates($deletion)` gives a request's own valid coordinates, each once and without a relay hint, for a store that applies the request by coordinate.
- `DeletionCoverage::lookupFilters($target)` gives the NIP-01 filters that select every request that could delete `$target`. A store fetches with them and judges each request found with `covers`.
- Holding requests is the caller's. The package offers no index, cache or store of deletion requests.
- @innis/nostr-core holds the same judgement, coordinates and lookup filters (`deletionCovers`, `deletedCoordinates`, `buildDeletionLookupFilters`), and the judgement and the lookup filters in both run the shared corpus, `tests/Vectors/deletion.json`, copied byte for byte from @innis/nostr-core (nostr-adrs ADR-0127).

## Consequences

- A relay asks this package rather than restating the rule, and a store that keeps a SQL statement of it is held to the same corpus by nostr-relay's store contract.
- The lookup filters may select a request `covers` then refuses; never treat a request found as a request that deletes.
- `ActingKeys::canDelete` in nostr-relay stays, as the form of the rule a store applies to a set of ids by the deleter's keys (its ADR-0038); the corpus pins it to this judgement.
- Shared decisions: nostr-adrs ADR-0093, ADR-0123, ADR-0124, ADR-0126 and ADR-0127.
