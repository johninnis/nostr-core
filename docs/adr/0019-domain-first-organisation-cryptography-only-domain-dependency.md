# 19. Code is organised domain-first, and cryptography is the only external dependency in the domain layer

## Status

Accepted

## Context

A Nostr library has to decide what its top-level structure mirrors. The protocol is specified as a series of numbered improvement proposals (NIPs), and existing PHP implementations follow that shape: code is grouped per NIP, with each NIP module carrying its own mix of protocol parsing, wire encoding, transport, and storage. That structure is the obvious one — it matches the specification documents one-to-one, and a contributor implementing a new NIP knows exactly where the new directory goes.

It also has a cost. A single conceptual entity — an event — is defined incrementally across many NIPs: NIP-01 gives it an id, signature, and serialisation; NIP-09 makes some events deletions; NIP-18 makes some reposts; NIP-40 adds expiry. Under per-NIP grouping, the one entity is smeared across many modules, each of which re-implements creation, signing, and verification for "its" kinds, and each of which reaches independently for encoding and transport. The layers blur: protocol rules, JSON handling, and HTTP all live together inside a NIP folder, so nothing in the domain is isolable or unit-testable without dragging infrastructure along.

Separately, the domain layer needs a rule about external dependencies. Pure layer separation would forbid every third-party library in the domain. But a Nostr identity *is* a secp256k1 keypair, and an event id *is* a signature over a hash; the elliptic-curve maths is not an infrastructure detail bolted onto the domain, it is constitutive of what the domain objects are.

## Decision

Organise the package around domain concepts — events, identities, tags, messages, references — not around NIP numbers. One `Event` entity handles creation, signing, and verification for every kind; the NIP that defines a kind decides which validation rules and which named constructors apply, not which class the behaviour lives in. Wire encoding (JSON, bech32), transport (HTTP), and storage concerns live in the infrastructure layer behind interfaces, never inside a domain object.

Within the domain layer, permit exactly one category of external dependency: cryptography (the secp256k1 elliptic-curve operations behind the signature and ECDH service interfaces, and the value objects that hold key material). Everything else a domain object needs — encoding, serialisation, I/O, clock, randomness — is reached through a port or supplied by a caller, never imported directly into a domain class.

## Consequences

- An event's behaviour lives in one place regardless of how many NIPs touch that kind; adding a NIP usually means new validation rules and named constructors, not a new parallel copy of event handling.
- The domain layer is unit-testable in isolation: a test constructs domain objects and asserts on them without standing up JSON codecs, HTTP clients, or storage.
- The cryptography carve-out is a deliberate, bounded exception, not an open door. Reaching for any other third-party library from a domain class — an HTTP client, a JSON encoder, a logger — is a layer violation to be pushed behind a port, even though the cryptographic libraries sit right there as apparent precedent.
- The structure does not map one-to-one onto the NIP documents, so implementing a NIP means knowing which existing domain concepts it extends rather than creating a new self-contained module. That lookup cost is the price of keeping each concept whole.
