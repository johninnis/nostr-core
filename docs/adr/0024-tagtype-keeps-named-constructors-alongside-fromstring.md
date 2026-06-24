# 24. `TagType` keeps convenience named constructors alongside `fromString` and constants

## Status

Accepted

## Context

`TagType` is the value object for a NIP-01 tag name, which is an open set — any string can be a tag name (see ADR-0006). It exposes three construction surfaces that all yield a `TagType`:

- **typed constants** for the well-known names (`TagType::EVENT = 'e'`, `TagType::PUBKEY = 'p'`, …), naming the wire vocabulary;
- **named constructors** for the tags applications build by hand (`TagType::event()`, `TagType::pubkey()`, `TagType::hashtag()`, `TagType::expiration()`, …);
- **`fromString(string)`**, the general constructor for an arbitrary name.

Side by side, three ways to construct one value reads like "more than one way to do a thing," and the named-constructor set deliberately covers only a subset of the constants — which reads like an arbitrary, half-finished API. Both observations invite the same "correction": delete the named constructors and route every construction through `fromString(TagType::CONST)`, leaving one path. That change looks like a simplification and a consistency win.

## Decision

Keep all three surfaces.

They are not competing implementations. Every named constructor and `fromString` funnels through the single private body of `__construct`, which holds the one and only validation (reject the empty string). The named constructors carry **zero logic** — each is `new self(self::CONST)` — so there is no second codepath that could drift from the first. This is not "two ways to get the same thing where one can rot"; it is one construction-and-validation path with discoverable shortcuts in front of it.

The three surfaces serve distinct, non-overlapping roles:

- **Constants** name the wire vocabulary in one place, so a tag name is never a bare string literal scattered through the code.
- **Named constructors** give an ergonomic, IDE-discoverable, typo-proof call for the tags most frequently constructed by hand — both in this package and in the downstream packages that depend on it. `TagType::pubkey()` cannot be mistyped the way a string can; `TagType::fromString(TagType::PUBKEY)` is strictly noisier for the same result.
- **`fromString`** is the escape hatch the open set requires: arbitrary and wire-sourced names (the relay protocol round-trips tags this library has never heard of) are parsed through it.

Collapsing onto `fromString`-only would rewrite every `TagType::pubkey()` to `TagType::fromString(TagType::PUBKEY)` — here and at every call site in every consumer — while eliminating no divergence, because there was never a second implementation to eliminate. It trades brevity and discoverability for churn and noise.

The named-constructor set is **intentionally partial**. It covers the tags applications assemble by hand; a rarely-built well-known tag is constructed with `fromString(TagType::CONST)`, and that is the expected pattern, not a gap to be "completed" into a factory method per constant. Growing the set to cover all thirty-plus constants would bloat the type to spare callers a constant reference they already have.

## Consequences

- The convenience constructors stay. A new one is warranted when a tag becomes commonly hand-built; it is not required for every constant.
- A unit that uses `TagType::identifier()` next to `TagType::fromString(TagType::TITLE)` is using the tiered API as intended — a built-in shortcut where one exists, the general form where it does not. This is not an inconsistency to unify.
- Validation lives once, in the constructor; the factories hold no logic and cannot diverge from it.
- The named constructors are part of the published surface and are used across the ecosystem's packages. Removing them is a breaking change to every consumer, not an internal refactor.
- `TagTypeTest` pins the factory methods. Do not remove them to "unify on `fromString`" — it churns every call site in this package and downstream and removes a discoverable, typo-proof API in exchange for no reduction in real duplication.
