# 41. NIP-49 adapters probe libsodium in `create()`, not the constructor

## Status

Accepted

## Context

NIP-49 scrypt derivation needs libsodium's `crypto_pwhash_scryptsalsa208sha256_ll`, reached through FFI;
there is no pure-PHP fallback (ADR-0039), so an instance without the native library can only throw when
asked to derive.

`Nip49Scrypt` used to run `FfiLibraryLoader::tryLoad(...)` inside its constructor, and `Nip49Cipher`
defaulted its scrypt collaborator to `new Nip49Scrypt()`. Merely writing `new Nip49Cipher()` therefore
`dlopen`ed a C library as a side effect of construction. That is a hidden dependency and a hidden
failure mode: a constructor is expected to be cheap and total, object graphs and DI containers
instantiate freely, and a unit test had no way to exercise the library-absent path without altering the
host environment.

The secp256k1 adapters (`Secp256k1Signer`, `Secp256k1Ecdh`) already solved this: their bare constructor
takes an injectable native handle and stays off the native path, while a static `create()` performs the
probe. NIP-49 was the lone inconsistency — a probing constructor with no injection seam.

## Decision

The NIP-49 adapters follow the same shape as the secp256k1 adapters.

- `Nip49Scrypt::__construct(?FFI $ffi)` takes the loaded handle (or `null`) and performs no I/O.
  `Nip49Scrypt::create()` runs the libsodium probe and passes the result in.
- `Nip49Cipher::__construct` defaults its collaborator to `new Nip49Scrypt(null)` — a non-probing,
  library-absent scrypt — so the bare constructor never `dlopen`s. `Nip49Cipher::create()` builds the
  cipher around `Nip49Scrypt::create()`.

Consumer code uses `Nip49Cipher::create()`. The bare constructor is for dependency injection and for
tests, exactly as it is for the secp256k1 adapters.

## Consequences

- No NIP-49 instance probes the system library as a construction side effect; the probe is an opt-in
  named-constructor step.
- The library-absent path is now unit-testable: `new Nip49Scrypt(null)` derives nothing and throws, with
  no host manipulation.
- `new Nip49Cipher()` no longer auto-detects libsodium; it yields a cipher whose scrypt has no FFI and so
  throws on `encrypt`/`decrypt`. This is consistent with ADR-0039 (NIP-49 has no fallback) and matches
  how `new Secp256k1Signer(null, ...)` stays off the native path. Reach for `Nip49Cipher::create()` in
  application code; do not "restore" a probing constructor.
