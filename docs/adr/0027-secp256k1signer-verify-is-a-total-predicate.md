# 27. `Secp256k1Signer::verify` is a total predicate — it returns `false`, never throws

## Status

Accepted

## Context

`verify(PublicKey, string, Signature): bool` answers one question: is this signature valid for this
message under this key? Its inputs are already-validated value objects, so the *expected* outcomes are
exactly two — valid or invalid.

The implementation can nonetheless raise. The FFI path marshals buffers into `libsecp256k1` and a
binding-level failure surfaces as an `FFI\Exception` (which extends `Error`, not `Exception`). The
pure-PHP path runs GMP and elliptic-curve arithmetic that can throw on a pathological point or value.
Left unguarded, `verify` therefore has a third, implicit outcome: it throws.

The general rule in this library is that faults bubble — exceptions are never silently swallowed. A
blanket `catch` that turns any error into a boolean reads like exactly that anti-pattern, which is why
this needs a record rather than an unremarked `try/catch`.

## Decision

`verify` catches `Throwable` and returns `false`. Any internal failure means "this signature could
not be established as valid", which for a verification predicate *is* the answer: not valid.

This is a deliberate, bounded exception to "never swallow", justified by what `verify` is:

- **It is a total predicate over untrusted input.** A relay calls `verify` on every event it
  receives. A crafted event that drove the verifier into a throwing path would otherwise become a
  denial-of-service vector — one malformed signature taking down the process — when the correct,
  safe verdict for an input that cannot be positively verified is simply `false`.
- **There is no recoverable fault to propagate.** The two legitimate outcomes are valid/invalid;
  there is no third action a caller could take on "the verifier errored" other than treating it as
  not-valid. Returning `false` collapses the impossible-to-act-on case into the answer the caller
  already has to handle.
- **It does not mask a programmer error in a dangerous direction.** The inputs are typed value
  objects, so a thrown error is an internal/environmental failure, not a caller mistake; the
  worst case is that a genuine implementation bug shows up as "invalid signature" rather than as an
  exception — an acceptable trade for a method whose entire contract is a boolean.

This is **not** symmetric with `sign`. `sign` throws on a wrong-length message (ADR-0013) because
that is a programmer error on trusted input; `verify` consumes untrusted data whose every failure
mode is honestly reported as "not a valid signature".

## Consequences

- A relay or client verifying untrusted events never crashes on a malformed or hostile signature;
  the worst outcome is a `false`.
- A real defect in the FFI or pure-PHP verify path surfaces as "invalid signature" instead of an
  exception. The correctness suites (BIP-340 vectors, cross-engine property sweeps) are the guard
  against such a defect, not a propagated throw.
- Do not "let the exception bubble so callers can see the real error" — `verify` is a predicate, and
  a relay's per-event call site has no safe action on a thrown verifier other than rejecting the
  event, which `false` already does. The single sanctioned swallow lives here, behind this record.
