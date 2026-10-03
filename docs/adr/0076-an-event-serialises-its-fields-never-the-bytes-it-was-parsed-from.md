# 76. An event serialises its fields, never the bytes it was parsed from

## Status

Accepted

## Context

nostr-adrs ADR-0008 decides, for every implementation, that a parsed event is serialised from its fields and never from the input it was parsed from: the input is not what the signature verified, so re-emitting it forwards bytes nobody checked.

This package used to do exactly that. `Event::tryFromJson` kept the untrusted input string and `toJson()` returned it verbatim; a relay `EVENT` message spliced the same string onto the wire. The field also meant two things — raw input on one path, a canonical re-encoding on another (`withRawJson`) — and a client parser re-encoded every inbound event eagerly to populate it. ADR-0082 settled the same question for NIP-19 entities, which no longer hand back the string they were parsed from.

## Decision

`Event` implements nostr-adrs ADR-0008. It holds its rumour, id and signature and nothing else, and `toJson()` always encodes `toArray()` with the canonical event encoding. There is no stored input string, no `getRawJson()`, no `withRawJson()`, and no pre-serialised message path (ADR-0075).

## Consequences

- Breaking: `Event`'s constructor loses its fourth argument, and `getRawJson()` / `withRawJson()` are removed.
- A consumer that must keep the original bytes keeps the input string beside the event; the type does not carry it.
- Tests pin that an extra key is not re-emitted and that a pretty-printed input serialises canonically.
- Do not reintroduce a stored input string for speed.
- Shared decisions: nostr-adrs ADR-0008 and ADR-0009.
