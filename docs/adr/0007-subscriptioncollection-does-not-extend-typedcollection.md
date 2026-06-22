# 7. `SubscriptionCollection` does not extend `TypedCollection`

## Status

Accepted

## Context

The codebase wraps collections in dedicated typed classes, and `TypedCollection` is the shared base for them. `SubscriptionCollection` is a collection, so extending `TypedCollection` looks like the consistent move.

## Decision

`SubscriptionCollection` does not extend `TypedCollection`. It is a string-keyed map — `add` / `get` / `remove` work by subscription id and iteration yields the id as the key — whereas `TypedCollection` models an ordered `list<T>`.

Forcing the map onto the list base would break its public contract (`toArray()` returning a keyed array, `foreach` exposing ids), which its tests assert. A keyed registry is a genuinely different data structure, not a fork of the list collection.

## Consequences

- `SubscriptionCollection` keeps its keyed-map contract; `TypedCollection` stays a clean `list<T>` abstraction.
- The `TypedCollection` inheritance (the one sanctioned generics-substitution case) is not stretched to cover a map.
- A test pins the keyed-map behaviour. Do not "unify" the two by making `SubscriptionCollection` extend `TypedCollection`.
