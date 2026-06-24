# 25. secp256k1 signing and ECDH keep a native FFI path and a pure-PHP fallback

## Status

Accepted

## Context

Every Nostr signature is a BIP-340 Schnorr signature over secp256k1, and NIP-04/NIP-44 key
agreement is a secp256k1 ECDH. Both can be computed two ways in PHP:

- **Natively**, by binding `libsecp256k1` through FFI (`schnorrsig_sign32`, `xonly_pubkey`,
  `ecdh`). This is fast and uses the audited, constant-time reference implementation, but it
  requires the `ffi` extension to be enabled and the `libsecp256k1` shared library to be present
  on the host.
- **In pure PHP**, by doing the elliptic-curve arithmetic with `gmp` (`Secp256k1Math` over the
  `paragonie/ecc` curve). This needs only the `gmp` extension, which is far more widely available
  than FFI-plus-a-native-library, but it is slower and is not constant-time.

`Secp256k1Signer` and `Secp256k1Ecdh` therefore each carry both implementations and choose between
them at runtime: `tryLoad()` attempts to bind the native library, and the service dispatches to the
native path when it is present and the pure-PHP path when it is not.

Two complete implementations of the same operation, selected by a runtime `if`, reads like a
duplicated code path — the kind of thing that should collapse to one. The question this record
settles is why it does not, and why the duplication is acceptable here.

## Decision

Keep both paths. The native FFI implementation is the preferred path; the pure-PHP implementation
is the fallback used only when the native library cannot be loaded.

Three things make this the right shape rather than a duplicated code path to be eliminated:

1. **There is one public contract, not two.** Callers depend on `SignatureServiceInterface` /
   `EcdhServiceInterface`. The choice of backend is an infrastructure deployment detail hidden
   entirely behind that interface — a caller cannot observe which path ran, and no domain or
   application code branches on it. The "one obvious way to do a thing" the caller sees is the
   interface; the two implementations behind it are an installation concern, the same way a
   repository may have an in-memory and a SQL implementation without that being "two ways to store".

2. **The two paths are required to be observationally identical, and that is mechanically pinned.**
   Both produce byte-identical BIP-340 signatures and ECDH shared secrets for the same inputs. This
   is not left to inspection: the BIP-340 and NIP-44 conformance suites run against *both* backends
   (the tests construct the service with the FFI binding and again with `null`, forcing the
   pure-PHP path), and a dedicated ECDH parity test asserts the two backends agree. A divergence
   fails the suite rather than silently shipping.

3. **Dropping either path costs something real.** Removing the pure-PHP fallback would turn `ffi`
   plus a present `libsecp256k1` into a hard installation requirement, excluding the many hosts that
   have `gmp` but not FFI (shared hosting, hardened/`disable_functions` environments, platforms
   without the native library). Removing the native path would forfeit the speed and the
   constant-time guarantee of the reference implementation on the hosts that *can* run it. Supporting
   both is the deliberate reach of the package, declared in `composer.json` (`ffi` and `gmp` are
   suggested, not required).

## Consequences

- The package signs and verifies on any host with `gmp`, and transparently accelerates to the
  native library where FFI and `libsecp256k1` are available — with no change at any call site.
- There are two implementations of the same arithmetic to keep in step. The conformance and parity
  suites are the guard: a fix or regression on one path that is not mirrored on the other fails the
  tests. A correctness fix to one backend must be checked against both.
- The pure-PHP fallback is **not constant-time** and is the less-hardened path. On a host that can
  run the native library it never executes; where it does run, the timing-side-channel exposure is
  the accepted cost of working without the native library at all. A deployment with a strict
  side-channel requirement should ensure the native path is available rather than relying on the
  fallback.
- Do not "remove the duplicate code path" by deleting the pure-PHP implementation — it is the
  fallback that lets the library run without FFI, not dead code. Do not delete the native path to
  "have one implementation" — it is the fast, constant-time default. The runtime dispatch in
  `Secp256k1Signer` and `Secp256k1Ecdh` is deliberate and is pinned by the dual-backend tests.
