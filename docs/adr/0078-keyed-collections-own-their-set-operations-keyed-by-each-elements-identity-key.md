# 78. Keyed collections own their set operations, keyed by each element's identity key

## Status

Accepted

Supersedes ADR-0024. The lazily memoised membership index, and the reason it does not contradict the per-read id of ADR-0046, are carried forward. This record revises how the index is keyed and where the set operations live. It holds the complete current decision.

## Context

`contains()` on a public-key or kind collection sits on a relay's per-event hot path: every delivered event is checked against a tenant set and a kind set. A linear scan with a closure is O(N) with a large constant, so `TypedCollection` held a lazily built `array<array-key, true>` index and a `containsByKey($key, $keyOf)` helper.

The helper took the key function as a callable, and five leaves (`PublicKeyCollection`, `EventIdCollection`, `EventKindCollection`, `HashtagCollection`, `RelayUrlCollection`) each re-declared a `keyOf`, `unique`, `contains`, `intersect` and `diff` that differed only in which accessor produced the key. Because the index was memoised per instance but keyed on nothing, a leaf that ever called the helper with a second key function would have been answered from the index built for the first — a wrong answer, not a crash, guarded only by a prose docblock.

## Decision

An element that can be held in a set declares its own identity through `IdentityKeyedInterface::identityKey(): int|string`: `PublicKey` and `EventId` return their hex, `EventKind` its integer, `Hashtag` and `RelayUrl` their canonical string, and `Event` its id's key.

`KeyedCollection<T of IdentityKeyedInterface>` is an abstract layer between `TypedCollection` and those leaves. It owns the memoised membership index and `contains(T)`, `unique()`, `intersect(self<T>)` and `diff(self<T>)`, all keyed by `identityKey()`. There is no key callable, so there is no second key to answer from. The leaves supply only their element type and their element-shaped constructors and projections (`fromHexValues`, `toHexes`, …).

Identity keys are unique only within one element type: an `EventId` and a `PublicKey` are both 64-character hex, so `EventIdCollection::contains($publicKey)` would answer true for a key whose hex matches a held id, and `intersect`/`diff` against a `PublicKeyCollection` would match by the same accident. The `T` in the signatures is a PHPStan generic, not a runtime check. So `contains` refuses an item that is not an instance of the collection's element type, and `intersect` and `diff` refuse an `$other` that is not an instance of `static`, the calling collection's own class, each with `InvalidArgumentException`. A cross-type call is a programming error, so it throws rather than returning false or an empty collection.

The index remains the one write after construction, permitted because a collection is not a `readonly` value object (its base is an `abstract class`) and the index is a pure function of elements that never change. That is why this does not contradict ADR-0046, which keeps `Rumour` from memoising its id: that would drop `readonly` from a value object; this does not.

## Consequences

- `contains()` is O(1) after the first call, with one mechanism rather than five copies.
- `EventIdCollection` and `EventCollection` gain `intersect` and `diff`. `EventCollection::contains` takes an `Event`, answered by its id.
- A new keyed collection is a leaf naming its element type; the element implements `identityKey()`.
- This is a second inheritance layer under `TypedCollection`. It qualifies on ADR-0097's test, as the first layer does: it keeps the base's mechanism unchanged and adds operations built on it (`contains`, `unique`, `intersect`, `diff` over the identity index), and a leaf in turn keeps this layer's mechanism unchanged, naming its element type, constrained to elements that carry an identity, and adding only operations built on it.
- Do not add a key callable back to the base, and do not re-declare `contains`/`unique`/`intersect`/`diff` on a leaf.
- The `contains()` tests are the guard: a broken or stale index fails them. Tests pin that `EventIdCollection::contains` given a `PublicKey` with a held id's hex, and `intersect`/`diff` given a `PublicKeyCollection` of the same hexes, throw.
- Do not remove the index to match ADR-0046. That record is about forfeiting `readonly` on a value object, which this does not do; the collection keeps its observable immutability and gains the membership speed its hot-path callers need. (Carried forward.)
