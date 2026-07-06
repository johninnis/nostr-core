# 41. NIP-49 adapters probe libsodium in `create()`, not the constructor

## Status

Accepted

## Context

NIP-49 scrypt derivation needs libsodium's `crypto_pwhash_scryptsalsa208sha256_ll`, reached through FFI;
there is no pure-PHP fallback (ADR-0039), so an instance without the native library can only throw when
asked to derive.

A constructor that probes for that library would `dlopen` a C library as a side effect of construction —
a hidden dependency and a hidden failure mode. Constructors are expected to be cheap and total, object
graphs and DI containers instantiate freely, and a probing constructor leaves no seam for a unit test to
exercise the library-absent path without altering the host environment.

The secp256k1 adapters (`Secp256k1Signer`, `Secp256k1Ecdh`) set the pattern: the constructor takes a
*required* injectable native handle (`?LibSecp256k1Ffi`, no default) and performs no probe, while a static
`create()` performs the probe. Because the handle has no default, `new Secp256k1Signer()` does not compile;
staying off the native path is an explicit `new Secp256k1Signer(null, ...)`. The NIP-49 adapters follow the
same shape rather than inventing a second way to wire an optional native library.

## Decision

The NIP-49 adapters follow the same shape as the secp256k1 adapters.

- `Nip49Scrypt::__construct(?FFI $ffi)` takes the loaded handle (or `null`) and performs no I/O.
  `Nip49Scrypt::create()` runs the libsodium probe and passes the result in.
- `Nip49Cipher::__construct` takes its `Nip49Scrypt` collaborator as a *required* argument with no
  default, so the constructor never `dlopen`s and never silently manufactures a throwing instance.
  `Nip49Cipher::create()` builds the cipher around `Nip49Scrypt::create()`.

The collaborator is required, not defaulted, precisely because NIP-49 has no fallback (ADR-0039): unlike
the secp256k1 adapters, where a `null` handle selects a working pure-PHP path, a library-absent
`Nip49Scrypt` can only throw. A default that produced one would let `new Nip49Cipher()` compile into an
object that is guaranteed to fail on `encrypt`/`decrypt` — a footgun with no legitimate runtime use. Making
the argument required matches `Secp256k1Signer` exactly and pushes the mistake to a compile error.

Consumer code uses `Nip49Cipher::create()`. The bare constructor is for dependency injection and for
tests, exactly as it is for the secp256k1 adapters.

## Consequences

- No NIP-49 instance probes the system library as a construction side effect; the probe is an opt-in
  named-constructor step.
- The library-absent path is unit-testable: `new Nip49Scrypt(null)` derives nothing and throws, with no
  host manipulation.
- `new Nip49Cipher()` does not compile: the scrypt collaborator is required. Application code that means
  to build a working cipher must call `Nip49Cipher::create()`; code that means to inject a stub writes it
  explicitly (`new Nip49Cipher(new Nip49Scrypt(null))`), so a library-absent instance is never produced by
  accident. This matches `Secp256k1Signer`, whose native handle is likewise required rather than defaulted.
