# 77. A value with an invariant has one parser that owns it, and trusted construction delegates to it

## Status

Accepted

## Context

ADR-0051 settled the names: `tryFrom<Input>` parses untrusted input and returns `null`, `from<Input>` is trusted construction that may throw. It did not settle where the rule itself is written, and the package wrote it twice. A public constructor asserted the invariant and threw; the parser re-implemented the same predicates so that it could return `null` instead of letting the constructor throw.

The copies disagreed. `Tag`'s constructor checked that values were strings but not that they were UTF-8, so `new Tag($type, ["\xff"])` built a tag that later failed to encode, while `Tag::tryFromArray` refused it. `TagFilter::fromValues` accepted an empty tag name that `matches()` then threw on. `Filter`'s parser re-checked counts that its constructor had already bounded, leaving dead branches, and checked the encoding of `search` where the constructor did not. The same pattern ran through the message leaves, `EventCount`, `Nip86Request`, `SecretKeyMaterial`, `Timestamp`, `EventKind`, `TagType` and `ContentReference`.

## Decision

A value object whose data carries an invariant has a private constructor that asserts nothing. One `tryFrom…(): ?self` holds every predicate and is the only caller of the constructor. Trusted construction is `from…(): self`, written as `tryFrom…(…) ?? throw new InvalidArgumentException(…)`. A parser of a wider input (a wire array, a JSON string) narrows the input and then calls the same `tryFrom…`; it never restates a predicate.

Named shortcuts (`Tag::hashtag()`, `EventCount::exact()`) are trusted construction and reach the constructor through the same parser. `TagType` is the exception that has nothing to parse: any string is a tag name (ADR-0129), so `tryFromString` refuses only a value that is not a string, and `fromString` and its shortcuts take a string already.

`Filter` follows the same shape. ADR-0049's decision, carried forward by ADR-0093, is unchanged — one cohesive value object, not decomposed, with one `withX()` per field — but the single source of truth each `withX()` re-passes through is now `Filter::tryFrom(…)` (reached via `Filter::from(…)`), not a public constructor, and `tryFromArray` narrows each wire field and calls it rather than re-checking counts and encodings. The message leaves with an invariant (`Client\AuthMessage`, `NoticeMessage`, `FilterRequestMessage`) follow it too, their `tryFromPayload` reaching the same parser as their trusted twin.

A value whose fields are unconstrained (`CloseMessage`) keeps a public constructor: there is nothing for a parser to own. `OkMessage` and `ClosedMessage` are not of that kind: a refusal and a `CLOSED` must carry a NIP-01 reason prefix, so they are built only through named constructors that take one (ADR-0087). The event metadata values (`LongformMetadata`, `LiveEventMetadata`, `CommentMetadata`, `HighlightMetadata`) hold text that must be UTF-8, so each has a private constructor, `tryFrom`/`from`, and its tag and array parsers reach `tryFrom`.

## Consequences

- Each rule is written once, so a parser and a constructor cannot disagree about what a valid value is.
- `new X(…)` stops compiling for the affected types; callers use `X::from…(…)` or `X::tryFrom…(…)`.
- A trusted constructor's message names the whole rule rather than the one field that failed, because only the parser knows which predicate refused and it answers `null`.
- Where the parser and the trusted twin take the value's full field list (`Filter`'s eight wire fields, `ContentReference`'s five), they take it as constructing any value does: the fields are the value, so a parameter object would be the same value again under a second name, and an argument count that exposes a service doing too much finds nothing to split in a value's own fields. No fence, and no parameter object.
- Do not add a check to a private constructor, and do not re-test in a wider parser something the `tryFrom…` it calls already decides.
