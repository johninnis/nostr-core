# 134. `EventKind` names only the kinds a published standard defines

## Status

Accepted

## Context

Which event kinds belong in a core library is decided for both cores by a shared record. This record holds how this package applies it.

`EventKind` carried constants for kinds that only one application uses: hubstr-ssg's site manifest, web page and web page draft (30630 to 30632), which no NIP or other common standard defines. A constant on the core's kind type reads as a claim that the kind is part of Nostr, and every consumer of the core inherited names it has no use for.

## Decision

- `EventKind`'s named constants, and every builder and reader in this package, cover only kinds the shared record admits. The Marmot kinds (`MLS_KEY_PACKAGE` 443, `MLS_WELCOME` 444, `MLS_GROUP_MESSAGE` 445 and `KEY_PACKAGE_RELAYS` 10051) stay, because the NIP registry lists them.
- An application's own kind is a constant in that application, as hubstr-ssg's `Nip63EventKind` holds 30630 to 30632, and is built and read through `EventKind::fromInt`, which accepts any kind in range: the core refuses no kind for being unnamed.

## Consequences

- A consumer that used a removed constant names the kind itself; the event it builds is unchanged.
- Do not add a constant, builder or reader to this package for a kind the shared record does not admit.
- Shared decision: nostr-adrs ADR-0109.
