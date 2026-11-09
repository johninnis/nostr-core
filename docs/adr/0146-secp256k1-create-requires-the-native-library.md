# 146. secp256k1 `create()` requires the native library, and the pure-PHP path is one a caller names

## Status

Accepted

Supersedes ADR-0101. Carried forward unchanged: both paths are kept, there is one public contract through `SignatureServiceInterface` and `EcdhServiceInterface`, and the paths are pinned as observationally identical by the conformance and parity suites. Revised: nothing falls back. `create()` refuses to start without the native library, the pure-PHP path is reached only by `createPurePhp()`, the constructors are private, and `backend()` and `Secp256k1Backend` are gone. This record holds the complete current decision.

## Context

Every Nostr signature is a BIP-340 Schnorr signature over secp256k1, and NIP-04/NIP-44 key agreement is a secp256k1 ECDH. PHP can compute both natively, binding `libsecp256k1` through FFI, which is fast, audited and constant-time but needs the `ffi` extension and the shared library on the host, or in pure PHP with `gmp` over `paragonie/ecc`, which needs only `gmp` but is slower and not constant-time. An attacker able to time a pure-PHP signer can in principle recover its key.

`create()` used to probe for the library and fall back without a word. So a host whose image rebuild dropped the library, whose php-fpm kept the default `ffi.enable=preload`, or whose distribution shipped a soname missing from the probe list, silently moved a long-lived signer onto the weaker path. `backend()` let a composition root assert against that, but the safe outcome depended on each host remembering to ask: a failure to load a security-relevant library read as success unless someone checked.

The alternatives were to keep the fallback and add a second, strict factory, which leaves the dangerous behaviour under the name everyone reaches for; or to delete the pure-PHP path, which drops hosts that can never run FFI and low-exposure clients for which the timing attack is out of reach.

## Decision

- **Keep both paths**, with one public contract. Callers depend on `SignatureServiceInterface` / `EcdhServiceInterface`, and no domain or application code can see or branch on the path.
- **`create()` means native.** It loads `libsecp256k1` or throws `CryptoException`; a library that loads but rejects its context randomisation is the same refusal.
- **`createPurePhp()` means pure PHP.** A host takes the path that is not constant-time only by writing that name.
- **The constructors are private**, so a null handle cannot arrive through dependency wiring.
- **There is no backend report.** An adapter is the path its factory named, so there is nothing left to ask.

## Consequences

- The probe list must hold every soname libsecp256k1 has published with the API used here, or `create()` refuses a host that has the library. A test names each one.
- A host without the native library fails at start-up, with a message naming the two remedies, rather than running on the weaker path for months undetected.
- A host with no FFI, or a client for which timing is out of reach, writes `createPurePhp()` once, in its composition root, where a reviewer can see it.
- Under a non-CLI SAPI, the default `ffi.enable=preload` now fails `create()`; the host sets `ffi.enable=true` or preloads this library.
- There are two implementations to keep in step; the conformance and parity suites are the guard, and a fix to one is checked against both.
- Do not add a factory that tries one path and then the other, and do not catch `create()`'s refusal to fall back in production code. Do not delete the pure-PHP path as dead code, or the native path to have one implementation.
- NIP-49 is unaffected: `Nip49Scrypt` has no fallback, and `derive()` already throws without the native library.
