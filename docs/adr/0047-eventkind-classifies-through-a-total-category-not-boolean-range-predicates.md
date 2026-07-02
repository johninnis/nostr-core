# 47. `EventKind` classifies through a total `category()`, not boolean range predicates

## Status

Accepted

## Context

An event kind has exactly one storage/replaceability behaviour, and a relay needs that behaviour to decide how to persist the event. There are four behaviours: not stored (ephemeral), stored and replaced per `(pubkey, kind)` (replaceable), stored and replaced per `(pubkey, kind, d-tag)` (addressable), and stored without replacement (regular). NIP-01 sorts kinds into these four by convention:

- replaceable — `n == 0 | n == 3 | 10000 <= n < 20000`
- ephemeral — `20000 <= n < 30000`
- addressable — `30000 <= n < 40000`
- regular — `1 | 2 | 4 <= n < 45 | 1000 <= n < 10000`

The replaceable, ephemeral, and addressable ranges are precise, but the regular range is a historical convention band, not a boundary of the behaviour: kinds outside every range (`45 <= n < 1000` and `n >= 40000`) are still real, registered event types — podcast episodes, MLS key packages, merge requests — and they are persisted, non-replaced events. Their behaviour is regular; only the convention band fails to name them.

`EventKind` modelled the four classes as four independent boolean predicates (`isRegular`, `isReplaceable`, `isEphemeral`, `isParameterisedReplaceable`) over hand-maintained ranges. Nothing structural keeps them faithful or mutually exclusive, and "which behaviour is this?" is answered by calling several predicates and inferring. A kind outside every range satisfies at most the drifted `isRegular` (which already returned true across `45 <= n < 1000`) and nothing at all above 40000 — so the partition is neither total nor trustworthy.

## Decision

`EventKind` exposes a single total classifier, `category(): EventKindCategory`, returning one case of a closed `EventKindCategory` enum: `Regular`, `Replaceable`, `Ephemeral`, `Addressable`. The four boolean predicates are removed — a caller asks the behaviour by comparing against a category case, and there is exactly one way to ask.

The classifier is total by treating **regular as the default behaviour**: the replaceable, ephemeral, and addressable ranges are tested explicitly, and every other kind — including the kinds outside NIP-01's regular convention band — is `Regular`. NIP-01 gives regular a bounded example range rather than an explicit "otherwise regular" clause, and calls the ranges "just conventions"; classifying the out-of-band kinds as regular is our deliberate reading of their actual storage behaviour, not a claim that the spec states it. There is no fifth behaviour to model, so there is no fifth case.

## Consequences

- Every kind maps to exactly one of four categories, disjoint and total by construction; the analyser can check a `match` over the result for exhaustiveness.
- A defined kind outside NIP-01's convention ranges (e.g. 62, 443, 818) classifies as `Regular` — matching how relays persist it — instead of satisfying no predicate. This is deliberate: the out-of-band bands default to regular.
- This is a deliberate breaking change to the `EventKind` API. The booleans are deleted, not kept as delegators, because keeping both a `category()` and four `is*` methods would be two ways to ask one question. Downstream callers migrate to `category() === EventKindCategory::<Case>`, and the package takes a minor-version bump.
- A test pins totality and disjointness across the range boundaries and the out-of-band kinds, and fails if the standalone `is*` range predicates are reintroduced.
