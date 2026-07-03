# 50. Pure codecs are domain services; infrastructure holds only external-technology implementations

## Status

Accepted

## Context

The package contains two kinds of class that turn one representation into another behind a domain-service interface.

One kind is pure: the bech32/bech32m codec, the hex codec, the NIP-19 TLV codec, the canonical event/message JSON writer, the NIP-98 auth-header codec, and the filter hasher. Each is deterministic and depends on nothing but the language's standard library and its own arithmetic — no third-party package, no FFI, no I/O. Domain value objects rely on these directly: an event computes its own id through the JSON writer, a public key renders its own `npub` through the bech32 codec.

The other kind depends on external technology: Schnorr signing and ECDH (an optional native `libsecp256k1` reached via FFI, with a pure-PHP fallback), the NIP-44/NIP-04/NIP-49 ciphers (the sodium and OpenSSL extensions), and NIP-05/NIP-11 retrieval (an HTTP client). These expose the same style of domain-service interface, but their implementations reach outside the process.

The pure codecs and their interfaces live in `Domain/Service`. The technology-dependent implementations live in `Infrastructure`, grouped by concern. One class sits on the wrong side of that line: the JSON message deserialiser, which is pure — it decodes with the built-in JSON parser and constructs domain messages — yet lives alone in an `Infrastructure/Encoding` folder while its interface sits in `Domain/Service`. That split invites a rule of the form "encoding is an infrastructure concern", which would be wrong twice over: it would misplace the six pure codecs that domain objects legitimately call, and it is already contradicted by where those codecs live.

## Decision

A class's layer is decided by what its implementation **depends on**, never by the word "encoding".

- **A pure codec is a domain capability.** If an implementation is deterministic and reaches only the language core and its own computation — no third-party library, no FFI, no extension beyond the core, no I/O, no clock, no randomness — then its interface and its implementation both belong in `Domain/Service`, and a domain value object may call it directly. Turning bytes into a bech32 string, or serialising an event to its canonical JSON, is domain behaviour: it is constitutive of what the value object is, not an external concern bolted on.
- **An external-technology implementation is infrastructure.** If an implementation depends on an optional native library, a cryptographic or other non-core extension, an HTTP client, the filesystem, the clock, or the entropy source, then the interface remains a domain (or application) contract and the concrete class lives in `Infrastructure`, grouped by concern, where the host can swap it.
- **"Encoding" is not a layer.** There is no `Encoding` bucket that pure serialisation is filed under by name. The JSON message deserialiser depends on no external technology, so it is a domain capability and lives in `Domain/Service` beside its interface — exactly as the NIP-19 codec does — and the single-purpose `Infrastructure/Encoding` folder is retired.

This refines the domain-first organisation record (ADR-0019) rather than reversing it. That record forbids a domain *object* from performing transport or storage, or from reaching for a third-party encoder, logger, or HTTP client; its operative test is the dependency, not the activity. A pure codec depends on none of those, so classifying it as a domain capability is consistent with that record's carve-out permitting constitutive, dependency-free computation inside the domain.

## Consequences

- The JSON message deserialiser moves from `Infrastructure/Encoding` to `Domain/Service`, and the `Infrastructure/Encoding` folder is removed. This changes the class's namespace and is a breaking change for any consumer that references it by its fully-qualified name.
- A new codec is filed by one question — does its implementation touch external technology? — not by whether it "encodes" or "decodes". Pure ones join the other domain codecs; technology-dependent ones go to the matching `Infrastructure` concern.
- `Infrastructure` holds only implementations that genuinely reach outside the process (crypto, HTTP, time), so the layer boundary stays a reliable signal of where the external dependencies are.
- If a codec that looks pure later acquires an external-technology dependency (say, a native acceleration path), it moves to `Infrastructure` at that point, while its domain-service interface stays put.
