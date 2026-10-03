# 56. `PublicKey` accepts any 32-byte x-coordinate; curve-point validation is deferred to the operation that needs it

## Status

Accepted

## Context

A Nostr public key is the x-coordinate of a secp256k1 point, 32 bytes / 64 hex characters. Not every 32-byte value is a valid x-coordinate: it must be less than the field prime `p`, and it must have a corresponding y (the point must lie on the curve, not the twist). `PublicKey::tryFromHex` checks only that the input is 64 lowercase hex characters — it accepts values `>= p` and off-curve values that a reference secp256k1 library rejects at parse time.

This is the same shape ADR-0029 recorded for `PrivateKey`, where the decision went the other way: `PrivateKey` rejects a scalar outside `[1, n-1]` at the boundary, because the two signing backends disagree on out-of-range scalars and a wrong value would flow inward and misbehave later. The asymmetry — `PrivateKey` range-checks, `PublicKey` does not — needs its own record.

The reason they differ is what the check costs and where the value is consumed.

- A `PrivateKey`'s invariant is a **scalar range test** (`0 < d < n`): a fixed-width integer comparison against the curve order, pure arithmetic with no elliptic-curve maths, so it lives inside the Domain value object without pulling curve operations into the Domain layer.
- A `PublicKey`'s validity is **curve membership**: deciding whether an x has a y on the curve requires a modular square root — genuine elliptic-curve computation that belongs in the cryptography services (Infrastructure and the pure-PHP `Secp256k1Math`), not in a Domain value object whose only dependency is meant to be trivial.

Crucially, an invalid public key cannot cause a silent wrong result the way an out-of-range private key can. Every operation that consumes a `PublicKey` already validates the point: signature `verify` lifts the x and returns `false` if it is not a curve point, and ECDH parses the point and throws `EcdhException` if it is off-curve or out of field. The failure surfaces exactly where the curve maths lives, with no backend divergence and no fabricated result.

## Decision

`PublicKey::tryFromHex` (and the other `PublicKey` parsers) validate only the 32-byte / 64-hex shape and do not test curve membership. Curve-point validity is established by the operation that performs elliptic-curve maths on the key — `verify` returns `false` for a non-point, ECDH throws `EcdhException` — not by the value object.

## Consequences

- A `PublicKey` may hold a 32-byte value that is not a valid curve point. This never produces a wrong cryptographic result: verification fails closed (`false`) and ECDH rejects it (`EcdhException`) at the point where the curve is actually computed.
- The Domain value object stays free of elliptic-curve maths; curve validation lives with the cryptography services that own that computation.
- This is a deliberate asymmetry with `PrivateKey` (ADR-0029), which range-checks because its invariant is cheap integer arithmetic and an out-of-range scalar *would* diverge between backends. A public key's invariant is neither cheap nor divergent-when-skipped, so it is checked at the consuming operation instead.
- Do not add a curve-membership check to `PublicKey::tryFromHex` "for symmetry with `PrivateKey`" — it drags curve maths into the Domain layer to re-establish an invariant every consuming operation already enforces.
