# 97. `SubscriptionCollection` is a keyed map, not a `TypedCollection` leaf

## Status

Accepted

Supersedes ADR-0007. Its decision — `SubscriptionCollection` does not extend `TypedCollection` — is carried forward unchanged. What is revised is the test it was argued from: ADR-0007 said a leaf "supplies nothing but its element type" and adds "zero behaviour", and the leaves now carry behaviour of their own. This record restates the decision on the test that holds today, and holds the complete current decision.

## Context

The package wraps each collection in a dedicated typed class, and `TypedCollection` is the shared base for them, standing in for the `list<T>` generic the language cannot express at runtime. `SubscriptionCollection` is a collection, so extending `TypedCollection` looks like the consistent move.

Since ADR-0007 the leaves have grown. `KeyedCollection` sits between `TypedCollection` and the leaves whose elements carry an identity, and owns their membership index and set operations (ADR-0078). The leaves add element-shaped constructors and projections — `PublicKeyCollection::fromHexValues` and `toHexes`, `HashtagCollection::fromStrings`, `TagCollection`'s typed readers. A leaf with methods of its own no longer fits "adds zero behaviour", so that cannot be why `SubscriptionCollection` stays out.

## Decision

`SubscriptionCollection` does not extend `TypedCollection`, because it is a different data structure and would have to override the base's mechanism rather than reuse it.

- **The test is whether a leaf keeps the base's mechanism unchanged.** `TypedCollection` owns an ordered `list<T>`: the validating constructor, insertion-order iteration with integer keys, `count`, `toArray()` returning a positional array, `merge`. A leaf names its element type and may add operations built on that mechanism — named constructors, projections, readers — but overrides none of it. Every current leaf passes.
- **`SubscriptionCollection` fails it.** It is a map with at most one subscription per id: `add` replaces a subscription already held under the same id in place, and `get`/`remove`/`withUpdatedState` address a subscription by its id. As a leaf it would override the validating constructor and the storage that `TypedCollection` keeps as an append-only list, which is changing the base's mechanism rather than building on it.
- **The id index is internal.** PHP turns an all-digit array key into an integer, so an index keyed by the id's string form cannot be handed out as a `list<string>` or a `string`-keyed iterator without lying about its type. `keys()` returns the `SubscriptionId` values, read from the subscriptions themselves, and iteration and `toArray()` yield the subscriptions as a `list` in insertion order.

## Consequences

- `SubscriptionCollection` keeps its one-per-id contract and exposes ids only as `SubscriptionId` values, never as array keys; `TypedCollection` stays a `list<T>` whose leaves never override it.
- A new operation on a leaf is fine when it is built on the base's mechanism; a leaf that needs to change how elements are stored, keyed or iterated is not a leaf.
- A test pins the keyed-map behaviour. Do not "unify" the two by making `SubscriptionCollection` extend `TypedCollection` or `KeyedCollection`.
