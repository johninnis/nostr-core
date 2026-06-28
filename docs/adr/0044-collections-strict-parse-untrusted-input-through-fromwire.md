# 44. Collections strict-parse untrusted input through `fromWire(mixed)`, beside lenient element-typed constructors

## Status

Accepted

## Context

A typed collection that carries protocol data is built from external input two different ways, and the codebase needs a name for each.

- **Strict.** When the collection *is* a wire field — a `REQ` filter's `ids` / `authors` / `kinds`, or an event's `tags` — a single malformed element means the whole field is malformed. Parsing must reject the entire set and surface the failure (`?self`, `null` on any bad element), so the caller cannot act on a half-parsed filter.
- **Lenient.** When the collection is the result of best-effort extraction — pubkeys pulled from an event's tags, relay hints scraped from content — a bad element is dropped and the rest kept (`self`, never `null`). A partial collection is the correct outcome, not a rejection.

These are two behaviours over the *same* input shape, so a collection that offers both cannot give them one name. Three already do: `EventIdCollection`, `PublicKeyCollection`, and `EventKindCollection` each expose a lenient element-typed constructor (`fromHexValues` / `fromInts`) and a strict one.

A second force shapes the strict constructor's signature. Its input arrives straight from `json_decode` as an untyped value — `Filter::fromArray` reads `$data['ids']`, `Subscription::fromArray` reads `$data['filters']`, both `mixed`. Something must narrow "is this even an array?" before parsing. If the strict constructor took a typed `array`, every caller would repeat that `is_array()` guard before calling; the same check would be duplicated at each wire-field site.

Two things therefore read like a smell and invite a "correction":

1. **Two construction methods on one collection** looks like more than one way to build the same thing — a reviewer is tempted to collapse them to one.
2. **The strict constructor is named `fromWire(mixed)`, not `fromArray(array)`** — and `fromArray` is the idiom every entity and value object in the package uses (`Event::fromArray`, `Tag::fromArray`). Against that backdrop `fromWire` reads as an inconsistent outlier, and `mixed` reads as a weakly-typed signature that should be tightened to `array`.

This second pull was real: `TagCollection` — which strict-parses an event's `tags`, exactly the job the others call `fromWire` — was originally named `fromArray(array)` and made its sole caller (`Event::build`) narrow the input first. So the same operation existed under two names, the genuine inconsistency.

## Decision

The strict parser of untrusted wire input is `fromWire(mixed): ?self` on every collection that has one, and it accepts the raw decoded value and narrows itself. `TagCollection::fromArray` is renamed to `fromWire(mixed)` to match, and `Event::build` no longer guards `is_array($data['tags'])` — the collection owns that check now, the same way `Filter::fromArray` already hands `EventIdCollection::fromWire` an unnarrowed `mixed`.

The lenient constructors keep their element-shaped names (`fromHexValues`, `fromInts`, `fromStrings`, `fromArrays`), because they are a genuinely different operation, not the strict parser under another name:

- `fromWire` returns `?self` and rejects the whole set on any bad element (via `parseEachStrict`).
- `from<Shape>` returns `self` and drops bad elements (via `parseEach`).

`fromWire` is the right name for the strict one, and `fromArray(array)` is the wrong one, for two reasons that hold on their own:

1. **`mixed` is the honest type and removes duplicated narrowing.** The value really is of unknown type until parsed; `fromWire(mixed)` absorbs the "is it an array?" decision once, at the boundary, instead of forcing an `is_array()` guard at every call site that reads a raw JSON field. `fromArray(array)` cannot express this — it can only demand the caller has already narrowed.
2. **`fromArray` cannot distinguish strict from lenient.** A collection offering both behaviours over the same input needs two names; `fromWire` marks "the canonical, all-or-nothing protocol-input parser", which is exactly the strict one. Naming both `fromArray` is impossible, and naming the strict one `fromArray` beside a lenient `fromHexValues` would split the pair on a distinction the name does not carry.

A collection that has *only* a lenient constructor (`RelayUrlCollection::fromStrings`, the reference collections' `fromArrays`) has no `fromWire`, because it is never a wire field that must be rejected wholesale — it is always best-effort extraction. The absence of a strict parser there is intended, not a gap.

## Consequences

- Strict wire parsing has one name across the collections: `fromWire(mixed): ?self`. A reader looking for "parse this protocol field into a collection" finds the same method everywhere, and the lenient element-typed constructors are visibly a different operation by their return type.
- This is a **breaking public-API change**. `TagCollection::fromArray` no longer exists; callers use `TagCollection::fromWire`. Downstream consumers that call `TagCollection::fromArray` (relay, client, and application packages) must be updated in lockstep.
- `Event::build` drops its `is_array($data['tags'])` guard; a non-array `tags` now yields `null` from `fromWire` rather than from the field-type guard. The rejection outcome is unchanged — only its owner moved into the collection.
- The strict/lenient pair on `EventIdCollection`, `PublicKeyCollection`, and `EventKindCollection` carries a one-line fence pointing here, so the two methods are not "unified" into one and `fromWire` is not "tidied" back to `fromArray(array)`. Doing the latter would re-duplicate the `is_array` narrowing at every call site and still leave the lenient variant needing its own name.
- Tests pin both behaviours, including that `fromWire` returns `null` for a non-array argument — the case the widened `mixed` signature now has to handle and a typed `array` parameter never could.
