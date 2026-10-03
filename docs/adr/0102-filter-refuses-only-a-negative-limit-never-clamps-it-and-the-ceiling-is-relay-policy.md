# 102. `Filter` refuses only a negative `limit`, never clamps it, and the ceiling is relay policy

## Status

Accepted

Supersedes ADR-0043. Carried forward unchanged: `null` is no limit, `0` is a valid explicit limit kept distinct from `null`, an invalid limit is refused rather than clamped, and matching ignores `limit`. Revised: the library no longer caps `limit` at 5000, and the rule lives in `Filter`'s one parser (ADR-0077), not in the `Filter::isValidLimit` and throwing constructor ADR-0043 named.

## Context

NIP-01 writes `"limit": <maximum number of events relays SHOULD return in the initial query>` and sets no maximum. NIP-11 gives the relay its own figure: "`max_limit`: the relay server will clamp each filter's `limit` value to this number". It is advertised and applied by the relay, never by the filter.

ADR-0043 refused a `limit` above 5000, "a self-imposed sanity ceiling ... in the same spirit as the per-field value cap on the array fields". ADR-0125 removed that value cap, and its reasoning applies to the limit as much as to the values: the figure bounded nothing a relay depends on, it sat above a relay configured lower and so did nothing for it, and it made a relay configured higher refuse requests it was willing to serve. Worse, NIP-11 tells a relay to clamp an over-large limit, not to refuse it; a parser that called `limit: 10000` malformed turned that into a refused `REQ`, so a relay built on this package could not follow NIP-11.

## Decision

- `Filter::tryFrom(…)` decides the `limit` with the filter's other invariants and returns `null` for a negative `limit`, the one value a "maximum number of events" cannot be. Any non-negative integer is kept as written. `Filter::from(…)` is its trusted twin and throws `InvalidArgumentException`; `withLimit()` reaches it through `from`.
- `Filter::tryFromArray` narrows the wire `limit` to an integer, returning `null` for any other type, and calls `tryFrom`.
- `null` and `0` stay distinct through construction, `getLimit()`, `toArray()` (which writes `limit` whenever it is not `null`) and `FilterHasher`.
- `matches()` and `canMatch()` ignore `limit`.
- How large a page a relay serves is relay policy: the relay configures it, advertises it as NIP-11 `max_limit`, and clamps a larger stated limit to it when it serves the query.

## Consequences

- A relay sees every well-formed `REQ` and `COUNT`, whatever its `limit`, and applies its own ceiling as NIP-11 describes.
- A `Filter` never clamps: it holds no result set to truncate, and a clamped filter would hash differently from the one the client sent.
- Do not reintroduce a `limit` ceiling constant in `Filter`, and do not collapse `0` and `null` with a truthiness check.
- Shared decisions: nostr-adrs ADR-0015 and ADR-0069.
