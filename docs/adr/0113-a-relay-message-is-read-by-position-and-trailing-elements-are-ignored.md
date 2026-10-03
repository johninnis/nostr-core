# 113. A relay message is read by position, and trailing elements are ignored

## Status

Accepted

## Context

Every relay-to-client message leaf refused a payload with more elements than it knew: `["EOSE","s","x"]`, an `OK` with a fifth element, a `CLOSED` with a third. That read as strictness, but it is the wrong strictness for a protocol that grows by appending.

NIP-67 adds a completeness hint as a third element of `EOSE`, and says why that is safe: "Clients that do not implement this NIP will ignore the extra element, as JSON arrays with trailing elements are accepted by conforming parsers and existing implementations index `EOSE` by position." NIP-42 builds on it: "Relays MAY use the `"auth"` hint in an `EOSE` message (as defined in NIP-67)". A client that refused `["EOSE","s",["finish"]]` would never see the end of stored events from a relay implementing either NIP, and would hang a paginating loop waiting for it.

## Decision

`EoseMessage`, `OkMessage`, `ClosedMessage`, `NoticeMessage`, `Relay\AuthMessage`, `Relay\EventMessage` and `Relay\CountMessage` each require at least the elements they read, and ignore any after them. A message with too few elements, or a known element of the wrong type, is still `null`.

## Consequences

- An extension that appends to a relay message reaches a client built on this package as the message it already knew; a client wanting the extension reads the raw array itself until the package models it.
- A parsed message re-serialises without the trailing elements. The package writes only what it models.
- Client-to-relay messages are not changed by this record.
- Do not restore the exact-count check "for strictness": it breaks against every relay that implements NIP-67 or the NIP-42 hint.
- Shared decision: nostr-adrs ADR-0091.
