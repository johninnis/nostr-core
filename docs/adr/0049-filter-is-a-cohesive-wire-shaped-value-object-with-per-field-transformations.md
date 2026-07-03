# 49. `Filter` is a single cohesive value object with a wide constructor and per-field transformations

## Status

Accepted

## Context

A NIP-01 `REQ` filter selects events by up to eight independent, all-optional criteria: ids, authors, kinds, tag filters, a `since` and an `until` bound, a `limit`, and a `search` string. The value object modelling it, `Filter`, therefore has an eight-parameter constructor, and it exposes several `withX()` transformations, each returning a new instance with one field replaced and every other field re-passed by name.

Two things about this read as smells at a glance. First, an eight-argument constructor trips the "more than three arguments is a design signal" heuristic and invites either splitting the class or bundling the fields into a nested "criteria" object. Second, the `withX()` methods look like duplication: each is a near-identical block that reconstructs the whole object, differing only in the one field it overrides, which invites collapsing them into a single `with(...)` helper that takes nullable overrides.

Both "corrections" are wrong here, and this record says why.

## Decision

Keep `Filter` as one value object with its wide constructor and one explicit `withX()` method per field that callers transform.

- **The eight fields are one concept, not unrelated arguments.** A filter is a single selector defined by the wire protocol; its fields only have meaning together, as the conjunction that decides whether an event matches. This is the case the argument-count heuristic exempts — a genuine, cohesive whole — not a bag of unrelated parameters bundled to dodge a count. Splitting `Filter`, or nesting its fields inside a `Criteria` sub-object, would fragment one wire object across several types and force every caller to reassemble it, buying nothing.
- **`null` is a meaningful, load-bearing value.** A `null` field means "this criterion is absent", and that is distinct from an empty collection. A single `with(?A $a = null, ?B $b = null, ...)` override helper cannot express "clear this field" versus "leave it unchanged" — both arrive as `null` — so it could not implement `withUntil(null)` correctly. The per-field methods exist precisely because a nullable-override helper is not expressible.
- **The repeated block is a constructor call, not shared behaviour.** Every invariant a filter must hold — the `limit` range, `since` not after `until`, the per-field value caps — is enforced in one place, the constructor. Each `withX()` reconstructs the value so those invariants are re-checked; the repetition is the call to the single source of truth, not logic duplicated away from it. Factoring the calls into a helper would insert indirection over the constructor without consolidating any real logic.

## Consequences

- `Filter` keeps a constructor wider than the usual limit; this is recorded here so it is not "fixed" by decomposition or by a parameter object.
- Do not collapse the `withX()` methods into a nullable-override `with(...)`: it cannot distinguish clearing a field from leaving it, and would silently break `null`-clearing transformations. A test that clears an optional bound back to `null` pins this.
- A new optional filter field is added as a constructor parameter and, when callers need to derive it, its own `withX()` method — the same shape, not a new mechanism.
- The moment the language offers a first-class immutable copy-with-changes construct that preserves `readonly` and distinguishes "unset" from "set to null", the `withX()` methods become its call sites; until then, explicit reconstruction is the only sound idiom.
