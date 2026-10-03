# 101. secp256k1 signing and ECDH keep a native FFI path and a pure-PHP fallback, and their adapters report which one they use

## Status

Accepted

Supersedes ADR-0025 and ADR-0057. ADR-0057 narrowed one sentence of ADR-0025's reasoning — "a caller cannot observe which path ran" — while saying it did not supersede it, so the current decision could only be read across both. Both decisions are carried forward unchanged; this record restates them as one and holds the complete current decision.

## Context

Every Nostr signature is a BIP-340 Schnorr signature over secp256k1, and NIP-04/NIP-44 key agreement is a secp256k1 ECDH. PHP can compute both natively, binding `libsecp256k1` through FFI — fast, audited and constant-time, but needing the `ffi` extension and the shared library on the host — or in pure PHP with `gmp` over `paragonie/ecc`, which needs only `gmp` but is slower and not constant-time. `Secp256k1Signer` and `Secp256k1Ecdh` carry both and choose at runtime. Two implementations selected by an `if` read like a duplicated code path that should collapse to one.

Because the pure-PHP path is not constant-time, an operator of a long-lived signer is told to confirm the native path is active. That instruction needs a way to ask the signer that was actually wired; probing `LibSecp256k1Ffi::tryLoad()` from consumer code builds a second context and answers whether the library could load now, not which path the wired instance takes.

## Decision

- **Keep both paths.** The native FFI path is preferred; the pure-PHP path is the fallback used only when the native library cannot be loaded.
  - There is one public contract. Callers depend on `SignatureServiceInterface` / `EcdhServiceInterface`; no domain or application code can see or branch on the backend.
  - The paths are observationally identical, mechanically pinned: the BIP-340 and NIP-44 conformance suites run against both backends, and an ECDH parity test asserts they agree.
  - Dropping either costs something real. Without the fallback, FFI plus a present `libsecp256k1` becomes a hard requirement; without the native path, hosts that can run it lose speed and the constant-time guarantee. `ext-gmp` is a hard `require` (and `paragonie/ecc` requires it regardless); `ext-ffi` is a `suggest`.
- **The adapters report their backend.** `Secp256k1Signer::backend()` and `Secp256k1Ecdh::backend()` return a `Secp256k1Backend` — `Native` or `PurePhp` — for that instance's constructor-injected handle, not a fresh probe.
  - Declared on the concrete adapters only, never on the service interfaces, so a caller holding the interface still cannot observe the backend.
  - `Secp256k1Backend` lives in `Infrastructure/Crypto` beside the adapters: by ADR-0100's dependency test it names external technology, and the domain must not be able to name it.
  - A total two-case enum, not an `isNative()` predicate.
  - It is deployment introspection — a startup assertion, a health check, a boot log line — never a dispatch input.

## Consequences

- The package signs and verifies on any host with `gmp`, and accelerates to the native library where available, with no change at any call site. A strict side-channel deployment ensures the native path is available and can fail fast at boot: `Secp256k1Backend::Native === $signer->backend() || throw …`.
- There are two implementations to keep in step; the conformance and parity suites are the guard, and a fix to one backend is checked against both.
- `backend()` reads the same field the dispatch branches on and sits beside it. That agreement cannot be pinned by a test, since the paths are observationally identical; the guard is proximity, so the one-line classification is deliberately duplicated in each adapter rather than extracted.
- Do not delete the pure-PHP path as "dead code" or the native path "to have one implementation". Do not branch cryptographic behaviour on `backend()`, lift it onto the service interfaces, or move `Secp256k1Backend` into `Domain/Enum`; a test pins its absence from the interfaces and its namespace.
- NIP-49 gets no equivalent: `Nip49Scrypt` has no fallback, and `derive()` throws on the first call without the native library, so its failure is already loud.
