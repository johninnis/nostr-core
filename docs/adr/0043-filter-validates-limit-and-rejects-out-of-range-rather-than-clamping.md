# 43. `Filter` validates `limit` in `[0, 5000]` and rejects out-of-range rather than clamping

## Status

Accepted

## Context

In NIP-01 a filter's `limit` is the maximum number of stored events a relay should return for the
initial query. `Filter` is a query specification, not a relay store (ADR-0042): it describes a request,
it never executes one and owns no result set. That distinction decides how an out-of-range `limit` must
be handled.

A relay has a third option a value object does not. Faced with `limit: 1000000` a relay clamps the value
down to its own ceiling (commonly 500) and serves that many events, because it is permissive to clients
and holds the result set it is truncating. `Filter` holds no result set, so "clamp to my maximum" has
nothing to clamp — the only honest behaviours are to accept the value as stated or to reject it.

Two further values sit on the boundary and are easy to get wrong:

- `limit: 0` is a real subscription idiom: "return no stored events, stream new ones only." It is valid,
  and it must stay distinguishable from an absent `limit` ("no limit at all"). A naive `if ($limit)`
  guard conflates the two and silently drops `0` on serialisation.
- A negative `limit`, a non-integer, or an absurdly large value is malformed input arriving from the
  wire. Accepting it lets a wrong value flow inward and only misbehave later.

## Decision

`Filter::isValidLimit` accepts `null`, or an integer in `[0, 5000]`:

- `null` means **no limit** — the field is absent from the wire form and omitted by `toArray`.
- `0` is a valid explicit limit — "no stored events." `hasLimit()` returns `true` for it, and `toArray`
  guards on `null !== $limit` (not falsiness), so `limit: 0` round-trips rather than being dropped.
- `1`–`5000` are valid. `5000` is a self-imposed sanity ceiling on a single scalar field, not a protocol
  constant — NIP-01 defines no maximum — in the same spirit as the per-field value cap on the array
  fields.
- A negative value, a non-integer, or a value above `5000` is out of range. The constructor throws
  `InvalidArgumentException`; `fromArray` returns `null`. The out-of-range limit is an anticipated
  boundary outcome on the parsing path (ADR-0003, ADR-0033) and a programmer fault on the direct
  constructor path.

## Consequences

- A `Filter` that survives construction carries a `limit` every downstream consumer can trust without
  re-validating.
- `Filter` rejects inputs a relay would silently clamp. That is deliberate: a query-specification value
  object fails fast on malformed input rather than rewriting it, the same parser-strictness stance taken
  for `RelayUrl` (ADR-0010), `Signature` (ADR-0026), and `PrivateKey` (ADR-0029). Do not switch to
  clamping — it would silently mutate a caller's filter and make its hashed identity (`FilterHasher`,
  ADR-0020) depend on a hidden ceiling.
- `matches()` and `matchesEvent()` ignore `limit` entirely: it is a result-set cap, not a per-event
  predicate. `Filter` truncates nothing; applying the limit is the relay's or store's job. Do not add
  `limit` handling to the matching path.
- `0` and `null` are different filters, and stay different through construction, `hasLimit()`, `toArray`,
  and the hash. Do not collapse them with a truthiness check.
