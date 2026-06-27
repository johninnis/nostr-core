# 31. `toJsonArray()` stays per collection leaf to keep each element's precise shape

## Status

Accepted

## Context

Eight typed-collection leaves expose `toJsonArray()`, and each body is the same single line — `mapItems(static fn (X $x): array => $x->toArray())` — varying only in the element type. That reads as textbook duplication: the obvious move is to hoist one `toJsonArray()` onto `TypedCollection` so every leaf inherits it.

The shapes are not actually the same, which is what blocks the hoist:

- `TagCollection::toJsonArray()` returns `list<list<string>>` (a `Tag` serialises to a positional `list<string>` such as `["e", id, relay]`).
- The other seven return `list<array<string, mixed>>` (an `Event`, `Filter`, `EventCoordinate`, reference, etc. serialises to a keyed map).

A single hoisted method can only declare one return type. The widest common type is `list<array>`, which discards the `list<string>` vs `array<string, mixed>` distinction the analyser currently enforces at every call site. Recovering precision would mean introducing an `ArrayRepresentable` interface, a second template-constrained intermediate base between `TypedCollection` and the seven map-shaped leaves, and an `implements` on each of the seven element classes — a whole inheritance layer and a new contract added to remove seven one-line methods, and a second use of inheritance beyond the single generic-substitution case the codebase otherwise holds itself to.

## Decision

Keep `toJsonArray()` defined on each leaf. The shared *mechanism* already lives once on the base (`mapItems` walks the items); what each leaf supplies is the element type and its `toArray()` call, exactly as each leaf supplies `elementType()`. The per-leaf method is the minimal type binding the language cannot hoist without erasing the element shape, not reachable duplicated behaviour.

## Consequences

- Every `toJsonArray()` keeps a precise, leaf-specific return type (`list<list<string>>` for tags, `list<array<string, mixed>>` for the rest), and the analyser checks each call site against it.
- The one-line bodies look duplicated but are not: they bind different element types to different return shapes through the one shared `mapItems` mechanism.
- Do not hoist `toJsonArray()` onto `TypedCollection` to "remove the duplication": a single signature forces `list<array>` and loses the per-leaf shape, and restoring it costs a new interface plus a second inheritance layer — more structure than the seven lines it would delete.
