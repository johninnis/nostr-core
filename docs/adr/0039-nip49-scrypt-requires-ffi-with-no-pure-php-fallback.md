# 39. NIP-49 scrypt requires FFI and libsodium, with no pure-PHP fallback

## Status

Accepted

## Context

Everywhere else, this package treats native acceleration as optional. secp256k1 signing,
verification, public-key derivation, and NIP-44 ECDH all probe for `libsecp256k1` through FFI and fall
back to a pure-PHP implementation when it is absent, so the package signs and verifies on any host that
has `gmp` (see ADR-0025). NIP-44 likewise runs entirely in PHP. A reader who has internalised that
pattern will see `Nip49Scrypt` throw `CryptoException` when FFI is unavailable and read it as an
oversight — the one cryptographic operation that does not degrade gracefully.

It is not an oversight. NIP-49 derives its symmetric key with scrypt over caller-chosen `(N, r, p)`
parameters, and there is no pure-PHP route to that primitive that the package is willing to ship:

- `ext-sodium` exposes scrypt only through the encoded-string `crypto_pwhash_*` API, which fixes its own
  parameter encoding and does not accept the raw `(N, r, p)` plus salt that NIP-49 mandates. The raw
  primitive `crypto_pwhash_scryptsalsa208sha256_ll` exists in libsodium but is not surfaced by the PHP
  extension, so the only way to reach it is FFI — the same constraint that forced ADR-0037 to reach
  sodium_compat's internal ChaCha20 for NIP-44.
- A hand-rolled pure-PHP scrypt would be a memory-hard KDF implemented in interpreted code: too slow to
  be usable at the spec's work factors (logN 16-22), and an unaudited reimplementation of a primitive
  whose entire purpose is to resist brute force. Shipping it would trade a clear "unavailable" signal for
  a slow, weaker one.

So NIP-49 cannot follow the FFI-or-fallback pattern of ADR-0025. Its only honest options are "require FFI"
or "do not offer NIP-49 at all", and the package offers NIP-49.

## Decision

NIP-49 encryption and decryption require the `ffi` extension and a loadable `libsodium`. When FFI is
unavailable, `Nip49Scrypt::derive` throws `CryptoException` rather than falling back. NIP-49 is therefore
unavailable on a host that runs the rest of the package on its pure-PHP `gmp` path. This asymmetry with
ADR-0025 is intentional and is fenced at `Nip49Scrypt` so it is not "fixed" into a fallback.

## Consequences

- A `gmp`-only host can sign, verify, derive keys, and do NIP-44 — but calling NIP-49 throws. Consumers
  that need NIP-49 must install `ext-ffi` and libsodium; this is stated in the README requirements and
  the example header, and the throw carries the reason.
- The failure is a thrown fault, not a returned outcome: an absent native library is an environment
  fault of a multi-step crypto operation, consistent with the error model for the rest of the crypto
  layer, not an anticipated per-call outcome the caller is expected to branch on.
- If a future PHP release surfaces raw scrypt with explicit `(N, r, p)` through `ext-sodium`, this
  decision should be revisited: the FFI requirement could then drop to an acceleration detail like
  ADR-0025, and this record superseded.
