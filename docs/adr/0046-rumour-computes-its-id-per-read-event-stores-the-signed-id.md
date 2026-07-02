# 46. `Rumour` computes its id per read; `Event` stores the id it was signed with

## Status

Accepted

## Context

A content hash identifies an event, and memoising that hash — compute once, store, return thereafter —
looks like an obvious optimisation. An earlier record settled this for the event type when a single type
modelled both signed and unsigned events: it must not cache the computed id, because a write-after-
construction memo field forfeits the class-level immutability the value design rests on.

The unsigned form is now a separate `Rumour` value object, and the signed `Event` always carries the id
it was signed with. The id computation therefore lives on `Rumour`, and `Event` no longer computes an id
lazily at all. The caching question moves with it and must be restated for both types.

## Decision

`Rumour::getId()` computes the SHA-256 over the serialised fields on each call and does not cache it.
`Event` stores the `EventId` established at signing (or parsed from the wire) as a constructor field and
returns it without rehashing.

- `Rumour` stays class-level immutable. A memo field would be a write-after-construction that drops that
  guarantee — the property that lets two rumours with the same fields be treated as interchangeable and
  reasoned about without asking whether a lazily-populated field has been touched. The hash is cheap, and
  only a rumour (a transient draft) ever recomputes.
- `Event` never rehashes on a read. It holds the id it was signed with, so `getId()` is a field read.
  `verify()` recomputes the rumour's id once to compare it against the stored id — that comparison is the
  substance of verification, not a cache miss.

## Consequences

- `Rumour::getId()` recomputes on every call; a caller reading it repeatedly in a hot path should hold
  the result in a local rather than expecting the type to memoise.
- `Event` carries its id, so the common path — events arriving signed off the wire — never rehashes on a
  read.
- Do not add a memo field to `Rumour` to save the hash: the immutability guarantee outweighs a cheap
  hash on the draft path.
- Supersedes ADR-0012, which recorded the same decision for the single dual-purpose event type that no
  longer exists.
