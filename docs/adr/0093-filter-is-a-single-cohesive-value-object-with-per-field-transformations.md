# 93. `Filter` is a single cohesive value object with per-field transformations

## Status

Accepted

Supersedes ADR-0049. The single cohesive value object and its per-field `withX()` transformations are carried forward unchanged; what a filter admits and how its wire fields are read are the shared decision below. It holds the complete current decision.

## Context

A NIP-01 `REQ` filter selects events by up to eight independent, all-optional criteria: ids, authors, kinds, tag filters, a `since` and an `until` bound, a `limit`, and a `search` string. The value object modelling it, `Filter`, therefore has an eight-parameter named constructor, and it exposes several `withX()` transformations, each returning a new instance with one field replaced and every other field re-passed by name.

The `withX()` methods look like duplication at a glance: each is a near-identical block that reconstructs the whole object, differing only in the one field it overrides, which invites collapsing them into a single `with(...)` helper that takes nullable overrides. That correction is wrong here.

## Decision

Keep `Filter` as one value object, built from its eight wire fields, with one explicit `withX()` method for each field callers transform: `withAuthors`, `withKinds`, `withSince`, `withUntil` and `withLimit`. A field no caller derives from an existing filter has no `withX()`.

- **The eight fields are one concept.** A filter is a single selector defined by the wire protocol; its fields only have meaning together, as the conjunction that decides whether an event matches, so nesting them inside a `Criteria` sub-object would fragment one wire object across several types. Its named constructors take those fields as they are, because the eight together are the filter and a parameter object holding them would be a second `Filter`; they need no fence.
- **`null` is a meaningful, load-bearing value.** A `null` field means "this criterion is absent", and that is distinct from an empty collection. A single nullable-override `with(...)` cannot express "clear this field" versus "leave it unchanged", so it could not implement `withUntil(null)` correctly. (Carried forward.)
- **The repeated block is a call to the one parser, not shared behaviour.** The invariants a filter itself holds — a non-negative `limit` and a UTF-8 `search` — are decided in `Filter::tryFrom`, which each `withX()` reaches through `Filter::from` (ADR-0077). The one-letter tag names and their values are decided once, in `TagFilter::tryFromValues`, before a `TagFilter` reaches the filter. How many values a field holds is relay policy, not an invariant (ADR-0125).

## Consequences

- Do not collapse the `withX()` methods into a nullable-override `with(...)`; a test that clears an optional bound back to `null` pins this. (Carried forward.)
- A new optional filter field is added as a named-constructor parameter and, only when callers need to derive it from an existing filter, its own `withX()` method. (Carried forward.)
- Shared decision: nostr-adrs ADR-0069.
