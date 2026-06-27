# 33. A `from*` parser returns `?self` only when a required field can be absent

## Status

Accepted

## Context

Many value objects parse external data through a `from*` named constructor — `fromArray`, `fromJsonString`, `fromTagCollection`. Their return types differ, and at a glance the difference reads as inconsistency:

- `ProfileMetadata::fromArray(): self`, `QuoteAnalysis::fromArray(): self`, `EventReferences::fromArray(): self`, `ReplyChain::fromArray(): self` — non-nullable.
- `LongformMetadata::fromArray(): ?self`, `CommentMetadata::fromArray(): ?self`, `EventReference::fromArray(): ?self`, `ContentReference::fromArray(): ?self` — nullable.

A reviewer who expects "a parser of untrusted input returns `?self`" sees the non-null ones and is tempted to make the family uniform — either all `?self`, or all `self`.

The return type is not arbitrary: it encodes whether the parse has a failure mode.

- A `from*` returns `?self` because a *required* field can be missing or malformed, so "could not parse this input" is a real outcome the caller must handle — surfaced as `null`, not thrown. `LongformMetadata` requires an `identifier`; absent it, there is nothing to construct.
- A `from*` returns `self` because *every* field it reads is optional. Absent input maps to an absent field (null / empty / false), construction cannot fail, and a `?self` there would be a `null` the caller can never actually receive — a forced check that documents a failure mode that does not exist. `ProfileMetadata` is eight optional fields, so even `fromArray([])` yields a valid value.

## Decision

A `from*` parser's return type tracks its failure mode. Return `?self` exactly when the input can fail to parse — a required field can be absent or malformed — so the analyser forces the caller to handle the `null`. Return `self` when every field is optional and construction cannot fail.

Do not flatten the two to one shape: making the can't-fail parsers `?self` adds a `null` no caller can observe, and making the can-fail parsers `self` would have to invent a value for missing required data.

## Consequences

- The `?` on a `from*` is a precise signal: `?self` means "this can reject the input," `self` means "this always succeeds." A non-null `from*` is a deliberate statement that the type has no required fields, not a forgotten null.
- Adding a required field to a currently all-optional value object correctly flips its `from*` from `self` to `?self`. That is an intended consequence of the new failure mode, not churn to suppress.
- The analyser enforces a null-check only where a failure mode actually exists, so callers of the always-succeeds parsers are not burdened with dead guards.
