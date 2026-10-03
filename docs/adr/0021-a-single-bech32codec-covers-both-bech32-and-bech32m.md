# 21. A single `Bech32Codec` covers both bech32 and bech32m

## Status

Accepted

## Context

NIP-19 entities (`npub`, `nsec`, `note`, `nprofile`, `nevent`, `naddr`) are encoded with bech32. Other consumers of this library need bech32m — the BIP-350 variant — for their own human-readable-prefix strings (for example FROSTR's `bfgroup1…` / `bfshare1…` / `bfonboard1…`). Both must be supported.

Bech32 and bech32m are nearly the same algorithm. They share the same character set, the same human-readable-part expansion, the same 5-bit grouping, and the same polynomial. They differ in exactly one value: the constant the checksum polymod is XORed against (`1` for bech32, `0x2BC830A3` for bech32m). Everything else — encoding, decoding, checksum verification, the TLV layer above it — is identical.

The obvious structure is two codecs, one per variant, mirroring the two named specifications. That reads as honest separation, but it duplicates the entire algorithm to vary a single integer, and any fix to the shared maths (a hrp-validation bug, a length check) then has to be made twice and kept in sync.

## Decision

Provide one `Bech32Codec`. The variant is a parameter — a `Bech32Variant` enum whose backing value *is* the XOR constant — threaded into the one place the algorithms diverge (`createChecksum`, and the matching check on decode). Callers select the variant explicitly; it defaults to `Bech32Variant::Bech32` because the NIP-19 path is the common one.

## Consequences

- The shared algorithm exists once. A correctness fix to hrp expansion, the polymod, or the data-conversion layer applies to both variants automatically, with no second copy to forget.
- The single point of divergence is visible and minimal: `polymod(...) ^ $variant->value`. A reader can see exactly how bech32m differs from bech32 by following one value, rather than diffing two near-identical classes.
- Encoding `bfgroup1…`-style strings is not a separate, FROSTR-specific codec bolted on; it is the same codec with a different enum case, so non-NIP consumers reuse the audited implementation rather than carrying their own.
- A caller that wants the bech32m variant must pass it explicitly; forgetting the argument silently produces a bech32 checksum. Encoding the variant constant as the enum's backing value keeps the two in lockstep so a new variant cannot be added without its constant, but it does not protect a caller who selects the wrong case.
- This deliberately does not split into per-spec types. Anyone tempted to "separate the two algorithms for clarity" would be duplicating a whole codec to vary one integer.
