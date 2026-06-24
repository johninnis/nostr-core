# 24. Typed collections memoise a membership index

## Status

Accepted

## Context

`PublicKeyCollection::contains()` and `EventKindCollection::contains()` sit on a hot path. The relay's per-event read-access check (`RelayPolicy::canClientReceiveEvent` → `GuestFilterRules::allowsEvent`) calls `contains()` for every event delivered to every client, and once per `p` tag when matching an event against the configured tenant set. A linear `array_any` scan with a closure is O(N) and — measured against a hash lookup, for the common case of a *non*-member that scans the whole list — about 18× slower at one element and roughly 900× slower at a hundred, with the closure-call overhead dominating even at N=1.

ADR-0012 established that `Event` does **not** cache its computed id, to preserve its class-level `readonly` immutability. A reader who sees a collection caching a derived index will reach for that record and "correct" it back to a recompute-every-time scan.

## Decision

`PublicKeyCollection` and `EventKindCollection` hold a private, lazily-built membership index (`array<hex, true>` / `array<int, true>`) and answer `contains()` with an O(1) `isset`. The index is built on the first `contains()` call and reused thereafter.

This does not contradict ADR-0012, because the two cases give up different things:

- `Event` is a `final readonly class`. Memoising its id forces dropping `readonly` and the immutability guarantee the whole value-object design rests on — a load-bearing property surrendered to save a cheap, rarely-recomputed hash.
- A typed collection is a `final class`, **not** `final readonly` — only its `items` are `readonly`. A lazily-populated index forfeits no existing guarantee: the collection is already observably immutable (its elements never change after construction), and the index is a pure function of those elements that can never go stale. The memo is invisible — two collections with the same elements answer `contains()` identically whether or not either has built its index.

The optimisation is also load-bearing where the id cache was not: `contains()` is on a measured hot path with a large constant factor, not a rare, cheap recomputation on a path most events never take.

## Consequences

- `contains()` is O(1) after the first call. The relay's per-event tenant and kind membership checks stay fast without a separately-maintained primitive index: `RelayPolicy` drops its `tenantHexSet` and asks the tenant `PublicKeyCollection` directly.
- The index is per instance; `intersect`, `diff`, and `unique` return new collections whose index starts empty, which is correct because their elements differ.
- The `contains()` correctness tests are the guard: a broken or stale index fails them. There is no behavioural difference to assert beyond correctness, because the memo changes only timing.
- Do not "remove the cache to match ADR-0012". That record is about forfeiting a `readonly` guarantee on a value object, which this does not do — the collection keeps its observable immutability and gains the membership speed its hot-path callers require.
