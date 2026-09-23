# 67. Which replaceable event survives is decided by `EventVersion`

## Status

Accepted

## Context

NIP-01 says that for replaceable and addressable kinds only the latest event is retained, and it defines latest precisely: the greater `created_at` wins, and when two events carry the same `created_at` the one with the lower id in lexical order is kept. Every party that holds such events must apply that rule identically, or a client's cached profile disagrees with the relay it came from.

This library classified the kinds (ADR-0047) and modelled the addressable identity as `EventCoordinate`, but the comparison itself existed only inside a SQLite write store in a relay application, written against raw row values. A client library caching follow lists or relay lists had no way to ask which of two events is current.

## Decision

`EventVersion` is a value object pairing a `Timestamp` with an `EventId`, built from an `Event` through `EventVersion::of()` or directly from the two values a store already holds. `supersedes(EventVersion $other)` answers the NIP-01 rule: true when this version's `created_at` is later, or when the timestamps are equal and this id sorts lower as bytes. A version never supersedes itself.

## Consequences

- A store compares an incoming event to the row it would replace by constructing the row's version from its stored timestamp and id, and asks one question. The tie-break can no longer be forgotten or reversed in one adapter.
- A client with two candidate events for the same coordinate resolves them with the same method the relay uses.
- The comparison is on the raw 32 id bytes, which is the lexical order of the hex form. Do not compare hex strings with locale-aware functions, and do not "simplify" the tie-break away: a store that keeps whichever arrived first diverges from every other store on the network.
