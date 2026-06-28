# 29. `PrivateKey` rejects scalars outside `[1, n-1]`

## Status

Accepted

## Context

A secp256k1 private key is a scalar in `[1, n-1]`, where `n` is the curve order. From the wire or a caller it arrives as 32 bytes / 64 hex characters, and a well-formed 64-char hex string can encode `0` or any value `>= n`. Accepting any syntactically-valid hex looks like the lenient, obvious behaviour, and rejecting some 64-char hex reads like over-strict parsing.

The two signing backends disagree on such inputs, which is what makes acceptance unsafe:

- The native `libsecp256k1` path rejects an out-of-range scalar (`keypair_create` fails), so signing throws.
- The pure-PHP/GMP path silently reduces the scalar `mod n`. So `d = 0` maps to the point at infinity (yielding an all-zero public-key x and a "signature" under a degenerate key), and `d` and `d + n` produce byte-identical keys and signatures.

A caller-supplied out-of-range key therefore signs on a `gmp`-only host but throws on an FFI host, and on the pure-PHP path produces a degenerate or aliased key rather than a failure. The value flows inward and only misbehaves later, dependent on which backend is installed.

## Decision

`PrivateKey`'s parsing constructors (`fromHex`, `fromBytes`, `fromBech32`) return `null` for a scalar that is zero or `>= n`. `generate()` retries until it mints one in range — looping is astronomically rare, since the out-of-range fraction of 32-byte values is about `2^-128`. The range test is a fixed-width lowercase-hex comparison against the curve order, kept inside the Domain value object: it constrains the scalar without reaching into the Infrastructure elliptic-curve maths, so the invariant lives with the type that owns it.

## Consequences

- Both backends reject the same inputs. An out-of-range key is an anticipated `null` at the boundary, not a backend-dependent throw and not a silently-degenerate or aliased key flowing downstream.
- The check is a value-range test only (`0 < d < n`), not a curve-membership or point check — a private key is a scalar, so range is the whole invariant.
- This is the same parser-strictness stance taken for `RelayUrl` (ADR-0010) and `Signature` (ADR-0026): a parser of untrusted input refuses a well-formed-but-unacceptable value at the edge rather than letting it become a wrong result later. Do not "loosen" the constructors to accept any 64-char hex — it reintroduces the backend divergence and the degenerate-key path.
