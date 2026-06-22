# 7. `SubscriptionCollection` does not extend `TypedCollection`

## Status

Accepted

## Context

The codebase wraps each collection in a dedicated typed class, and `TypedCollection` is the shared base for them — the one place inheritance is used, standing in for the `list<T>` generic the language cannot express at runtime. `SubscriptionCollection` is a collection, so extending `TypedCollection` looks like the consistent move.

## Decision

`SubscriptionCollection` does not extend `TypedCollection`, because it is a structurally different data structure, and that inheritance is justified for one structure only.

The inheritance qualifies on a narrow test: a leaf supplies nothing but its element type and inherits the base's *entire* mechanism unchanged — the base is a complete, identical mechanism and each leaf adds zero behaviour. `TypedCollection` is exactly that for an ordered `list<T>`: append, iterate in insertion order, `toArray()` to a positional array, deduplicate by value, with each leaf supplying only `elementType()`.

`SubscriptionCollection` cannot be a leaf under that test, because almost none of the mechanism survives the change. It is a string-keyed map: `add`/`get`/`remove` by subscription id, iteration that yields the id as the key, and `toArray()` returning that keyed array. To sit under `TypedCollection` it would have to override the keying, the iterator, and `toArray()` — that is not instantiating the base's generic with a type, it is fighting the base's mechanism. Worse, it would break the `list<T>` contract the base's *other* leaves and their callers rely on (`toArray()` returning a positional array, `foreach` yielding integer indices). A keyed registry and an ordered list are two structures; sharing one base would couple them and weaken both.

## Consequences

- `SubscriptionCollection` keeps its keyed-map contract; `TypedCollection` stays a clean `list<T>` abstraction whose leaves instantiate it without override.
- The generics-substitution inheritance is held to its test — a leaf adds only its element type — rather than stretched to cover a structurally different collection.
- A test pins the keyed-map behaviour. Do not "unify" the two by making `SubscriptionCollection` extend `TypedCollection`.
