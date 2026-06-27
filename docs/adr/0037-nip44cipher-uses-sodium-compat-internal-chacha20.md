# 37. `Nip44Cipher` uses sodium_compat's internal ChaCha20 because no public IETF stream cipher exists

## Status

Accepted

## Context

NIP-44 v2 encrypts with ChaCha20 in its IETF form: a 32-byte key, a 12-byte nonce, and a 32-bit block
counter. The cipher is used as a raw keystream — the plaintext is padded, then XOR-ed against the
stream (`ietfStreamXorIc`), with confidentiality and integrity provided separately by an HMAC-SHA256 MAC
over the nonce and ciphertext.

PHP's bundled `ext-sodium` exposes no function for this. Its stream-cipher surface is
`sodium_crypto_stream` (XSalsa20) and `sodium_crypto_stream_xchacha20` (XChaCha20, a 24-byte nonce) —
neither is ChaCha20-IETF, and there is no `crypto_stream_chacha20_ietf_xor` binding. The AEAD wrappers
(`sodium_crypto_aead_chacha20poly1305_ietf_*`) embed ChaCha20-IETF but force Poly1305 framing and a
combined tag, so they cannot produce the bare keystream NIP-44 specifies. The same gap exists in
`paragonie/sodium_compat`'s public `sodium_*` polyfill.

The keystream is reachable only through `ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc` — a class in
sodium_compat's `_Core_` namespace, which is its internal implementation surface rather than the
semver-guarded `sodium_*` API the project depends on. Binding to a vendor internal reads, correctly, as
a smell: a minor release of sodium_compat could move or rename it without a major bump.

The alternatives were weighed and rejected:

- **Hand-roll ChaCha20-IETF in pure PHP.** Re-implementing a stream cipher from scratch is more
  dangerous than reusing sodium_compat's audited, constant-time implementation; it would be net-new
  cryptographic code to maintain and review.
- **Require `ext-sodium` ≥ a version with a raw binding.** No version of `ext-sodium` exposes
  ChaCha20-IETF as a raw keystream; this option does not exist.
- **Use the ChaCha20-Poly1305 AEAD and discard the tag.** The AEAD computes its keystream with a
  different counter start (block 1, with block 0 reserved for the Poly1305 key) and cannot be coerced
  into the block-0 keystream NIP-44 XORs against. It produces the wrong bytes.

## Decision

Call `ParagonIE_Sodium_Core_ChaCha20::ietfStreamXorIc` directly, accepting the dependency on a
sodium_compat internal as the only way to obtain a spec-correct ChaCha20-IETF keystream in pure PHP.
Pin the choice with a one-line fence at each call site pointing here, and let the existing NIP-44
conformance vectors (`nip44.vectors.json`, asserted by `Nip44EncryptionComplianceTest`) act as the
test that fails loudly if a sodium_compat upgrade ever changes or removes the method.

## Consequences

- NIP-44 confidentiality rests on a class outside sodium_compat's public API contract. A sodium_compat
  upgrade is therefore a cryptographic-surface change for this package: it must be run against the
  conformance vectors before being accepted, not treated as a routine dependency bump.
- The vector suite is the safety net. If `ietfStreamXorIc` is renamed, relocated, or altered, the
  round-trip and known-answer tests fail rather than silently producing non-interoperable ciphertext.
- Do not "tidy" the call onto a `sodium_*` public function — none produces the bare ChaCha20-IETF
  keystream, so any such change would break interoperability with every other NIP-44 implementation.
- If a future `ext-sodium` or sodium_compat release adds a public raw ChaCha20-IETF binding, this
  decision should be superseded to move onto it.
