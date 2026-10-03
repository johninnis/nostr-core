# 87. A CLOSED carries its message, and a refusal always has a reason prefix

## Status

Accepted

Supersedes ADR-0065. The `ReasonPrefix` enum, its words, `format()`, `tryFromMessage()`, `isAuthRequired()` and reading the prefix from the text on demand are carried forward unchanged. What is revised: a CLOSED must carry its message, and a refusal whose message names no known prefix reads as `error` rather than as no prefix. The vocabulary and both rules are the shared decision, nostr-adrs ADR-0031; this record holds what is particular to this package.

## Context

nostr-adrs ADR-0031 decides for every implementation that the reason prefix is one vocabulary read on accepted and refused replies alike, that a refusal always has a reason (`error` when its head names none), that the message text is kept as sent with the reason derived from it, and that a `CLOSED` without its message is malformed.

ADR-0065 parsed a CLOSED without its message as an empty one, and answered `null` for the prefix of any refusal that named no known word, so a client branching on a refusal had a case the protocol says cannot happen.

## Decision

`ReasonPrefix` and the message classes implement nostr-adrs ADR-0031:

- `ClosedMessage` requires two payload elements and ignores any after them (ADR-0113); a CLOSED without its message is `null`.
- `ReasonPrefix::ofRefusal()` reads the known prefix at the head of the text and answers `Error` when there is none, the word is not in the enum, or the text is empty. `ClosedMessage::getReasonPrefix()` returns `ReasonPrefix`, and a refused `OkMessage::getReasonPrefix()` returns non-null.
- An accepted OK's `getReasonPrefix()` reads the head with `tryFromMessage()` and answers `null` when it names no known word.
- The reason is computed from the text on each call and never stored.
- A relay writes a refusal through the only constructors that make one: `OkMessage::refused(EventId, ReasonPrefix, detail)` and `ClosedMessage::closed(SubscriptionId, ReasonPrefix, detail)`, which write `prefix: detail` with `ReasonPrefix::format()`. An acceptance is `OkMessage::accepted(EventId, message = '')`, whose text NIP-01 leaves free. The constructors are private, so the library never writes a refusal or a CLOSED without a NIP-01 prefix; a peer's reply that lacks one is still read, as `Error`, by `tryFromArray`.

## Consequences

- `ClosedMessage::getReasonPrefix()` narrows from `?ReasonPrefix` to `ReasonPrefix`.
- Breaking: `OkMessage` and `ClosedMessage` lose their public constructors; callers use `accepted`, `refused` and `closed`.
- Do not add a word to the enum unless a NIP defines it. Do not add a per-prefix boolean beyond `isAuthRequired()`, which a resend loop asks directly.
- Shared decision: nostr-adrs ADR-0031.
