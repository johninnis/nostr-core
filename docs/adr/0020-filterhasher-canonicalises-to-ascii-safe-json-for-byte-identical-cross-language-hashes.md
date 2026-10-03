# 20. `FilterHasher` canonicalises to ASCII-safe JSON for byte-identical cross-language hashes

## Status

Accepted

## Context

`FilterHasher::hash` computes a stable identity for a NIP-01 `REQ` filter set, intended as a subscription dedup key. A sibling TypeScript implementation (`hashFilters`) must compute the same digest for the same logical filter set, because the two run in the same distributed system and a subscription opened by one side must be recognised as identical by the other. "Same digest" here means byte-for-byte equal hex, for every possible input — including `search` strings and tag-filter values that contain arbitrary Unicode.

Two gaps make naive JSON hashing diverge across the runtimes:

- **Escaping.** PHP's `json_encode` escapes `U+2028` and `U+2029` even under `JSON_UNESCAPED_UNICODE`, whereas JavaScript's `JSON.stringify` emits them verbatim. The same string therefore serialises to different bytes on each side.
- **Collation.** Canonicalisation sorts object keys and array elements so that reordered-but-equivalent filter sets collapse to one form. PHP strings are UTF-8 byte sequences; JavaScript strings are UTF-16 code-unit sequences. For astral characters (emoji and the like) a bytewise UTF-8 sort and a code-unit UTF-16 sort disagree, so the two runtimes would sort the same elements into different orders and hash different structures.

Patching each runtime to mimic the other's quirks (post-processing `JSON.stringify` output to escape line separators, or re-implementing UTF-16 collation in PHP) is possible but fragile: every escaping or collation edge case becomes a paired fix that must stay in lockstep across two languages forever.

## Decision

Make the canonical string pure ASCII before hashing, and let that single property close both gaps at once. Concretely: sort keys and array elements, then encode with `JSON_UNESCAPED_SLASHES` but *without* `JSON_UNESCAPED_UNICODE`, so every non-ASCII code unit is emitted as a lowercase `\uXXXX` escape (astral characters as UTF-16 surrogate pairs). The TypeScript side post-escapes its `JSON.stringify` output to match. The hash is the lowercase-hex SHA-256 of that canonical string.

With no raw non-ASCII bytes in the canonical form, bytewise, UTF-8-byte, UTF-16-code-unit, and code-point collation all coincide, so both runtimes sort identically; and because all non-ASCII (including `U+2028`/`U+2029`) is `\uXXXX`-escaped, the escaping difference disappears too. The parity is locked by shared conformance anchors — a fixed table of inputs and their expected digests — asserted in both test suites, so a regression on either side fails its own tests rather than silently desynchronising the two runtimes in production.

## Consequences

- Equivalent filter sets hash identically across PHP and TypeScript for every input, including non-ASCII and astral characters, without per-edge-case patches in either runtime.
- The canonical string is larger than a UTF-8 encoding would be (every non-ASCII character expands to a six-character escape), and it is not the most human-readable form. This is irrelevant: the canonical string exists only to be hashed, never to be stored or displayed.
- The conformance-anchor table is part of the contract. Changing the canonicalisation — different escaping, different sort, different JSON flags — changes every digest and breaks compatibility with already-stored dedup keys and with the TypeScript side, so it must be treated as a breaking change, not an internal refactor.
- The decision depends on `json_encode` *not* receiving `JSON_UNESCAPED_UNICODE` for this path. That flag is correct elsewhere (event ids require verbatim Unicode), so the two encodings must stay deliberately distinct rather than being unified onto one set of flags.
