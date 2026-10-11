# 113. A relay message is read by position, trailing elements are ignored, and an `EOSE` carries its NIP-67 hint array

## Status

Accepted

## Context

Every relay-to-client message leaf once refused a payload with more elements than it knew: `["EOSE","s","x"]`, an `OK` with a fifth element, a `CLOSED` with a third. That is the wrong strictness for a protocol that grows by appending.

NIP-67 adds a completeness hint as a third element of `EOSE`, `["EOSE", <subscription_id>, [<hint>, ...]]`, and says why that is safe: "Clients that do not implement this NIP will ignore the extra element, as JSON arrays with trailing elements are accepted by conforming parsers and existing implementations index `EOSE` by position." It defines `"finish"`, `"more"` and `"auth"`, says "The array MAY carry multiple hints simultaneously, and MAY be empty", and "Implementations SHOULD accept unknown hint values without error and treat them as if absent." NIP-42 builds on it: "Relays MAY use the `"auth"` hint in an `EOSE` message (as defined in NIP-67)", and nostr-adrs ADR-0027 has a relay send it when it narrows a read.

## Decision

- `EoseMessage`, `OkMessage`, `ClosedMessage`, `NoticeMessage`, `Relay\AuthMessage`, `Relay\EventMessage` and `Relay\CountMessage` each require at least the elements they read, and ignore any after them. A message with too few elements, or a known element of the wrong type, is still `null`.
- `EoseMessage` reads its third element as NIP-67's hint array when it is a JSON list of strings, and holds it as `getHints()`, a list of strings in the order written, an unknown hint kept. A third element that is not a list of strings is ignored, as any other extension is, and the message has no hints.
- NIP-67's hints are the `EoseHint` vocabulary, `Finish`, `More` and `Auth`, as `ReasonPrefix` is the vocabulary of `OK` and `CLOSED`. A relay writes hints with `EoseMessage::hinting($subscriptionId, EoseHint::Auth)`, and a client reads one with `hasHint(EoseHint::Auth)`, so neither spells the word. `toArray()` and `toJson()` write the hint array as the third element when it holds a hint, and write `["EOSE", <subscription_id>]` when it holds none.

## Consequences

- An extension that appends to a relay message reaches a client built on this package as the message it already knew.
- A relay narrowing a read writes `["EOSE", <subscription_id>, ["auth"]]` through `hinting()`, and a client reads the `"auth"` hint with `hasHint()`.
- An unknown hint means nothing to this package and is kept, so a client that knows it can still read it.
- An `EOSE` read with an empty hint array is written back without one: the two say the same thing, since no hint is present in either.
- Client-to-relay messages are not changed by this record.
- Do not restore the exact-count check "for strictness": it breaks against every relay that implements NIP-67 or the NIP-42 hint. Do not make the hint array a condition of the `EOSE`.
- Shared decisions: nostr-adrs ADR-0027 and ADR-0091.
