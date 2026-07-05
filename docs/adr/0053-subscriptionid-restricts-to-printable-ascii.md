# 53. `SubscriptionId` restricts to printable ASCII, stricter than the wire grammar

## Status

Accepted

## Context

NIP-01 describes a subscription id as "an arbitrary, non-empty string of max length 64". Taken literally that admits spaces, control characters, and arbitrary Unicode. `SubscriptionId::tryFromString` is stricter: it accepts only printable ASCII (`\x21`–`\x7E`), so a space, a tab, a NUL, or any non-ASCII code point is rejected.

Because every relay-protocol message that carries a subscription id parses it through `SubscriptionId::tryFromString` (`REQ`, `CLOSE`, `EOSE`, `CLOSED`, `EVENT`, `COUNT`), this restriction is load-bearing: a frame whose subscription id falls outside the printable-ASCII range is dropped wholesale. Side by side with the spec's "arbitrary string" wording, that reads like an over-strict parser someone should loosen.

## Decision

`SubscriptionId` accepts a non-empty string of at most 64 characters drawn from printable ASCII (`/^[\x21-\x7E]+$/D`) and rejects anything else with a returned `null`.

The subscription id is a correlation handle chosen by the client and echoed by the relay; it is never content and carries no meaning beyond identity. Constraining it to a byte-clean, control-free, whitespace-free token removes an injection and framing surface (control characters and spaces in an identifier that is logged, echoed into other messages, and used as a map key) for no loss of real functionality: a client needs a unique handle, not an arbitrary Unicode string. The 64-character ceiling is the spec's; the character-set floor is a deliberate hardening on top of it.

## Consequences

- A subscription id containing whitespace, control bytes, or non-ASCII is refused at the boundary, so a relay built on this library will not open a subscription under such a handle. This is an intended interoperability trade against clients that would use exotic identifiers.
- The identifier is safe to use as a map key, to echo into `EOSE`/`CLOSED`/`EVENT` frames, and to log, without escaping concerns.
- Tests pin the printable-ASCII grammar and the length ceiling. Do not "loosen `SubscriptionId` to match the spec's arbitrary-string wording" — the restriction is deliberate hardening, and the value it excludes is functionally worthless.
