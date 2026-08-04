# 57. The secp256k1 adapters report their active backend, and the report stays off the domain service interfaces

## Status

Accepted

## Context

ADR-0025 keeps a native `libsecp256k1` FFI path and a pure-PHP fallback behind one contract, chosen at
runtime by `Secp256k1Signer::create()` / `Secp256k1Ecdh::create()` probing for the native library. The
two paths are observationally identical in output — byte-identical BIP-340 signatures and ECDH shared
secrets, pinned by the dual-backend conformance and parity suites — but they are **not** identical in
hardening: the pure-PHP path is not constant-time and cannot be made so, so a host that repeatedly
signs with a fixed key wants the native path and needs to know it has it.

`SECURITY.md` and `README.md` both instruct operators of a server-side or long-lived signer to
"confirm the native path is active before deploying". Until now there was no way to do that. `$ffi`
is a private constructor-injected field with no accessor, so the instruction named an action the API
did not offer. The nearest available check, calling `LibSecp256k1Ffi::tryLoad()` from consumer code,
answers a different question: it builds a second, independent context and reports whether the library
*could* load now, not which path the signer that was actually wired into the container will take. A
signer constructed as `new Secp256k1Signer(null, ...)` by a DI definition, or one whose host lost
`libsecp256k1` in an image rebuild, reports "native available" under that check while signing on the
fallback.

Silent degradation from a hardened path to a weaker one, documented but unobservable at runtime, is a
known way to lose years before anyone notices. The instruction in the security documentation has to be
executable.

The reason no accessor existed is real and has to be preserved. ADR-0025 argues that the two
implementations are not a duplicated code path precisely because the backend is invisible above the
adapter: callers depend on `SignatureServiceInterface` / `EcdhServiceInterface`, and no domain or
application code branches on which implementation is behind them. An accessor on those interfaces
would make the backend a fact the whole codebase can see and therefore condition on, and the first
`if (native) { ... } else { ... }` in a use case turns one contract back into two.

## Decision

`Secp256k1Signer` and `Secp256k1Ecdh` each expose `backend(): Secp256k1Backend`, returning one case of
a closed enum with exactly two cases, `Native` and `PurePhp`. The method reports the backend of *that
instance* — the constructor-injected handle, not a fresh probe.

Three constraints scope it:

- **It is declared on the concrete adapters only, never on `SignatureServiceInterface` or
  `EcdhServiceInterface`.** Those interfaces are domain contracts, and the backend is an
  infrastructure deployment fact; putting it on the interface would push knowledge of the native
  library into every consumer of the contract and invite branching on it. A caller that holds the
  interface still cannot observe the backend — the narrowing of ADR-0025 is exact, and applies only to
  a caller that already holds the concrete infrastructure adapter.
- **`Secp256k1Backend` lives in `Infrastructure/Crypto`, beside the adapters, not with the domain's
  enums.** The domain layer must not be able to name the backend — that is the point of keeping the
  report off the service interfaces, and a type in the domain whose two cases are "the audited native C
  library" and "the interpreted fallback" would hand it straight back. By ADR-0050's dependency test
  the enum is external technology, so it belongs in the layer that owns external technology. Within
  that layer it sits in the concern folder of the two adapters that return it: Infrastructure is
  grouped by the technology it wraps rather than by the shape of each class, and a two-case enum with
  exactly two callers earns no folder of its own.
- **It is a total classifier, not a boolean predicate.** Following ADR-0047, the question "which
  backend?" is answered by one enum with an exhaustive `match`, rather than an `isNative()` whose
  negation a reader has to infer. There is no third backend to model, so there are two cases.

This is **deployment introspection**: a startup assertion, a health-check field, a boot log line. It is
not a dispatch input. This record narrows one sentence of ADR-0025's reasoning — "a caller cannot
observe which path ran" now holds for callers of the service interfaces rather than for every caller —
and leaves that record's decision, that both paths are kept and chosen at runtime, unchanged. It does
not supersede it, in the same way ADR-0050 refined the dependency test of ADR-0019 without reversing
it.

## Consequences

- The instruction in `SECURITY.md` and `README.md` becomes executable. An operator of a relay, a
  NIP-46 bunker, or any long-lived signer can fail fast at boot —
  `Secp256k1Backend::Native === $signer->backend() || throw ...` — instead of discovering the
  non-constant-time path in a post-incident review. A host that loses the native library on a rebuild
  now trips an assertion rather than degrading quietly.
- Adding a method to the adapters that is absent from the interfaces they implement is deliberate. A
  consumer wanting the check must depend on the concrete `Secp256k1Signer` / `Secp256k1Ecdh` at the
  composition root, which is where deployment facts belong, rather than everywhere the signing
  contract is used.
- **Do not branch cryptographic behaviour on `backend()`.** Selecting an algorithm, skipping a
  validation, or varying an output by backend reintroduces the two-contracts problem ADR-0025 exists
  to prevent, and would break the guarantee that both paths are observationally identical. The
  sanctioned uses are asserting, reporting, and logging.
- **Do not "complete" the abstraction by lifting `backend()` onto `SignatureServiceInterface` or
  `EcdhServiceInterface`**, and do not move `Secp256k1Backend` into `Domain/Enum` to sit with the other
  enums. Both changes would hand the backend to the layers this record keeps it away from. A test pins
  the method's absence from the interfaces and the enum's namespace, and fails if either is undone.
- `backend()` reports the handle the instance was constructed with, so it inherits that instance's
  wiring: a bare constructor call with `null` reports `PurePhp` even on a host where the native library
  would load. That is the intended reading — the question is what this signer will do, not what the
  host could offer.
- **`backend()` reads the same field the dispatch branches on, and must stay adjacent to it.** A
  `backend()` that disagreed with the dispatch would be worse than no method at all, because an
  operator acts on it. That agreement cannot be pinned by a test: ADR-0025 requires the two paths to be
  observationally identical, so no assertion from outside the adapter can tell which one ran. The
  guard is proximity — `backend()` sits immediately above the dispatch it describes, reading the same
  `$this->ffi`. Do not extract the classification into a shared helper or a named constructor on the
  enum: the one-line ternary is duplicated across the two adapters deliberately, so that each stays
  next to the `null !== $this->ffi` branch it reports on rather than pointing at a definition
  elsewhere.
- **NIP-49 deliberately does not get an equivalent method.** `Nip49Scrypt::create()` also probes for a
  native library and also succeeds when it is absent, but ADR-0039 gives it no fallback: `derive()`
  throws `CryptoException` on the first call. That failure is loud and fail-closed, so an operator
  learns about it without introspection, and the hazard this record addresses — a working signer
  quietly running on the weaker path — does not exist there. Adding an availability probe to
  `Nip49Scrypt` to "complete the pattern" would answer a question nothing is silently getting wrong.
  If NIP-49 ever gains a degraded mode, that is when it needs this record's treatment.
