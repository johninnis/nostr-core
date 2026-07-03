# 51. Named constructors follow the from/tryFrom split

## Status

Accepted

Supersedes ADR-0033 and ADR-0044.

## Context

A named constructor either trusts its argument or it does not, and the two cases have opposite failure contracts. Parsing an untrusted wire value can fail on data the caller did not control, so "this input was malformed" is an ordinary outcome the caller must handle. Asserting an invariant on a value the caller already owns cannot fail on well-formed input, so a failure there is a broken invariant — a fault, not an outcome.

The language already names this split: a backed enum's `from()` is total and throws on an unknown value, while `tryFrom()` parses and returns null. Our named constructors had drifted away from it. Two local decisions encoded the drift:

- ADR-0033 kept the failable parser under the `from*` name and let its return type carry the signal — `from*(): ?self` when a required field could be absent, `from*(): self` when every field was optional. So `LongformMetadata::fromArray(): ?self` and `ProfileMetadata::fromArray(): self` were both `from*`, distinguished only by the `?`.
- ADR-0044 introduced `fromWire(mixed): ?self` as the strict, raw-input collection parser, sitting beside lenient element-typed constructors, precisely because `from*` could not distinguish the two behaviours by name.

Both worked, but they made `from*` mean two incompatible things — sometimes a total assertion, sometimes a failable parse — so the reader could not tell a constructor's contract from its name. A nullable `from*` is a `tryFrom*` under the wrong name, and once `tryFrom*` exists, `fromWire` is just a `tryFrom*` that would not say what it parses.

## Decision

A named constructor's name states its contract.

- **`tryFrom<Input>` parses untrusted input.** It returns `?self` (or a `*Failure` union) and never throws; a malformed argument is reported as the null/failure value, which the analyser then forces the caller to handle. The name carries the input it parses — `tryFromArray`, `tryFromString`, `tryFromJson` — not the transport, so `fromWire` is retired: the raw strict parser that took a `mixed` wire value is `tryFromArray(mixed)`, still narrowing "is this even an array?" itself.
- **`from<Input>` is total, trusted construction.** It does not return nullable. It may throw to assert an invariant on a value that should already be valid. A constructor that reads only optional fields and therefore cannot fail is total and correctly stays `from*(): self` — that is the default, not a departure.

Where a class previously exposed both `fromWire(mixed)` and a `fromArray`/`fromString` that only pre-narrowed the same input, the two collapse into one `tryFrom<Input>(mixed)`: the honest untrusted type is `mixed`, and one parser that narrows once removes the wrapper.

## Consequences

- The contract is legible from the name alone: `tryFrom*` can reject its input, `from*` cannot return null and asserts instead. The `?self`-versus-`self` distinction ADR-0033 drew now lives in the `tryFrom`-versus-`from` prefix; the all-optional constructors it defended keep returning `self` and stay `from*`.
- `fromWire` no longer exists. Collections and `TagFilter` expose `tryFromArray(mixed): ?self`; `Event`, `Filter`, and `SubscriptionId` merged their narrow-and-delegate wrapper into a single `tryFromArray(mixed)` / `tryFromString(mixed)`, so a raw wire element is parsed through one method rather than two.
- This is a **breaking public-API change** across the surface: every nullable `from*` parser is renamed, and downstream consumers (relay, client, and application packages) update their call sites in lockstep.
- The rule is analyser-enforced per method: a nullable `from*` is flagged, and a `tryFrom*` that returns non-nullable or throws is flagged. A fence is kept only where a `tryFrom*` must translate a thrown library fault at its own boundary — the stale ADR-0033 and ADR-0044 fences, which existed only to satisfy the old naming, are removed.
