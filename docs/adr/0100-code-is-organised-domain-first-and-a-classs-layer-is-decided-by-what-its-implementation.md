# 100. Code is organised domain-first, and a class's layer is decided by what its implementation depends on

## Status

Accepted

Supersedes ADR-0019 and ADR-0050, which decided one question between them: ADR-0050 refined ADR-0019's dependency rule without superseding it, so the current rule could only be read across both. Domain-first organisation, the cryptography carve-out, and the rule that a pure codec is a domain capability are carried forward unchanged. What is revised: ADR-0019 said wire encoding lives in infrastructure behind interfaces, which the package no longer does (the pure codecs live in `Domain/Service`, most with no interface); and ADR-0050 placed NIP-05 and NIP-11 retrieval in Infrastructure as external technology, where the classes that perform it now live in `Application/Service`, orchestrating the host's HTTP port. This record holds the complete current decision.

## Context

A Nostr library has to decide what its top-level structure mirrors. The protocol is specified as numbered NIPs, and the obvious structure groups code per NIP, each module carrying its own protocol parsing, wire encoding, transport and storage. But one entity — an event — is defined across many NIPs (NIP-01 gives it an id, signature and serialisation; NIP-09 makes some deletions; NIP-18 some reposts; NIP-40 adds expiry), so per-NIP grouping smears it across modules that each re-implement it and each reach for encoding and transport.

The domain layer also needs a rule for external dependencies. A Nostr identity is a secp256k1 keypair and an event id is a signature over a hash, so the elliptic-curve maths is constitutive of the domain objects. Separately, classes that turn one representation into another sit on both sides of the line: the bech32, hex, NIP-19, canonical JSON and NIP-98 header codecs, and the filter hasher are pure; signing, ECDH and the ciphers reach a native library or an extension; NIP-05 and NIP-11 retrieval reach the network. Filing them by the word "encoding" misplaces one group or the other.

## Decision

- **Organise around domain concepts** — events, identities, tags, messages, references — not NIP numbers. One `Event` entity handles creation, signing and verification for every kind; the NIP that defines a kind decides which validation rules and named constructors apply, not which class the behaviour lives in.
- **A class's layer is decided by what its implementation depends on, never by the word "encoding".** An implementation that is deterministic and reaches only the language core and its own computation — no third-party library beyond the cryptography below, no FFI, no extension beyond those listed next, no I/O, no clock, no randomness — is a domain capability: it lives in `Domain/Service`, and a domain value object may call it directly (an event computes its id through the JSON writer; a public key renders its `npub` through the bech32 codec). Whether it sits behind an interface follows ADR-0098.
- **The domain reads text through three bundled extensions, declared in `composer.json`:** `mbstring` (UTF-8 validation and character counts), `ctype` (`ctype_digit` in `DecimalIntegerParser`) and `filter` (`FILTER_VALIDATE_INT` in the same parser). They are deterministic string functions, not external technology, and a host that builds PHP without one cannot install the package rather than failing at runtime on untrusted input.
- **The domain permits one category of external dependency: cryptography** — the secp256k1 operations behind the signature and ECDH interfaces, and the value objects holding key material. The interfaces are domain contracts; the implementations that reach a native library or an extension (`Secp256k1Signer`, `Secp256k1Ecdh`, the NIP-04/NIP-44/NIP-49 ciphers) live in `Infrastructure/Crypto`, where the host can swap them. `GiftWrapper` reaches no native library itself; it lives beside them because its `create()` wires that default stack and the random envelope factory (ADR-0109).
- **Anything reaching the outside world goes through a port.** HTTP is `Application/Port/HttpServiceInterface`, implemented by the host; `Nip05Verifier` and `Nip11Fetcher` are application services orchestrating that port and the domain, so they live in `Application/Service` beside their interfaces. Clock and randomness are ports too, read directly only where ADR-0005 and ADR-0018 allow.
- **There is no `Encoding` bucket** in either layer.

## Consequences

- An event's behaviour lives in one place regardless of how many NIPs touch its kind; adding a NIP usually means new validation rules and named constructors, not a parallel copy of event handling.
- The domain is unit-testable in isolation, without codecs to stub, HTTP clients or storage.
- The cryptography carve-out is bounded. Reaching for any other third-party library from a domain class — an HTTP client, a JSON library, a logger — is a layer violation, even though the cryptographic libraries sit there as apparent precedent.
- A new codec is filed by one question: does its implementation touch external technology? If a pure one later gains a native acceleration path, it moves to Infrastructure behind a domain interface.
- Breaking (since ADR-0050): the message deserialiser left `Infrastructure/Encoding` (and has since been removed, ADR-0075); `Nip05Verifier` and `Nip11Fetcher` moved from `Infrastructure/Http` to `Application/Service`, and their interfaces from `Application/Port` to `Application/Service`; the `UserAgent` class, which held only the `User-Agent` string they send, is gone, and the string is `HttpServiceInterface::USER_AGENT` on the port the request goes through.
