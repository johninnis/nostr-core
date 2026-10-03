# 122. The pure-PHP BIP-340 composition is this package's own, over `paragonie/ecc` arithmetic

## Status

Accepted

## Context

When `libsecp256k1` cannot be loaded, `Secp256k1Signer` signs, verifies and derives x-only public keys in PHP (ADR-0101). The elliptic-curve arithmetic for that path — the secp256k1 generator, point multiplication and addition, y-recovery — comes from `paragonie/ecc`, a hard dependency. `paragonie/ecc` also ships `Mdanter\Ecc\Crypto\Signature\SchnorrSigner`, a BIP-340 signer and verifier, so a reader finds a hand-written BIP-340 composition beside a dependency that appears to provide one, and the obvious move is to delete ours and call theirs.

Run against the BIP-340 vectors and read against this package's key-handling rules, `SchnorrSigner` does not fit:

- **It takes the secret key and the auxiliary randomness as hex strings.** Neither is wiped, and nothing lets a caller wipe the copies it makes. This package reads a secret only inside `SecretKeyMaterial::expose` and zeroes every intermediate buffer it derives from one (ADR-0015, ADR-0028).
- **It reads its message argument by content.** An empty value or one made only of hex digits is taken as hex; anything else is SHA-256 hashed before signing. A caller passing the 32 raw bytes of an event id signs a different message without any error.
- **Its verifier throws for two inputs BIP-340 answers `false` for**: a public key that is not on the curve (vector 5) and an `s` equal to the curve order (vector 13).

## Decision

- The pure-PHP path composes BIP-340 itself in `Secp256k1Signer` — tagged hashes, nonce derivation, the challenge, the even-y negations and the range checks on `r`, `s` and the key — over `paragonie/ecc`'s curve arithmetic, which it does use. `SchnorrSigner` is not used.
- The composition is pinned the way the native path is: the BIP-340 vector suite runs against both backends (ADR-0101).

## Consequences

- There is a BIP-340 composition to maintain in this package. The vector suite is its guard, and any change to it runs against both backends.
- Secret material on the fallback path is handled by the same rules as everywhere else in the package.
- Do not replace the composition with `SchnorrSigner` to "use the dependency". Revisit this record if `paragonie/ecc` gains a BIP-340 API that takes bytes, reads the message as given, wipes what it derives, and answers `false` for every invalid input; the arithmetic is already the dependency's.
