# 13. `Secp256k1Signer::sign` rejects any message that is not exactly 32 bytes

## Status

Accepted

## Context

General BIP-340 signs an arbitrary-length message, so a signer that rejects anything but 32 bytes looks artificially restrictive. But every Nostr signature is over the 32-byte event id (`SHA-256` of the serialised event) — the variable-length message has already been hashed to a fixed digest before it reaches the signer.

## Decision

`Secp256k1Signer::sign` rejects any message that is not exactly 32 bytes. Enforcing 32 bytes makes a wrong-length argument a fail-fast programmer error (`InvalidArgumentException`) rather than silently routing it down the slower pure-PHP path — libsecp256k1's FFI binding exposes the 32-byte `schnorrsig_sign32` only.

`verify` stays length-agnostic — it is a pure query with no such divergence.

## Consequences

- A wrong-length message fails fast as a programmer error instead of silently taking a slower, divergent code path.
- The FFI and pure-PHP signing paths cannot diverge on message length.
- `verify` is deliberately not symmetric here; it remains length-agnostic. Do not "relax" `sign` to accept arbitrary lengths.
