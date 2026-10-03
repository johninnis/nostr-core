# 120. Identity hex is converted by sodium's constant-time codec, and `HexCodec` only validates

## Status

Accepted

## Context

Public keys, event ids, signatures and secret keys arrive and leave as lowercase hex. `HexCodec` held three functions for them: `tryCanonical`, which decides whether a string is the one hex spelling of a value of a given width, `encode`, which returned `bin2hex($bytes)`, and `decode`, which returned `hex2bin($hex)` and threw when it answered `false`. ADR-0004 named `HexCodec` as the collaborator that keeps hex validation and conversion out of the three identity types.

Two things were wrong with the conversion half. `encode` added nothing to `bin2hex`: the analyser already infers that a non-empty input gives a non-empty output, so the method was a second name for a language function. And both conversions branched on the data: `hex2bin` and `bin2hex` are table lookups whose timing depends on the digits, and they converted secret keys (`SecretKeyMaterial::tryFromHex`, `PrivateKey::toHex`) as well as public values. The `sodium` extension, a hard requirement of this package, ships `sodium_bin2hex` and `sodium_hex2bin`, which produce the same lowercase output and run in constant time.

## Decision

- Hex is converted with `sodium_bin2hex` and `sodium_hex2bin`, called directly where a value converts itself (`PublicKey`, `EventId`, `Signature`, `PrivateKey`, `SecretKeyMaterial`). One codec serves secret and public values alike, so no caller decides which kind of value it holds before choosing a function.
- A secret scalar is compared in constant time too. `PrivateKey` checks 0 < k < n with `sodium_compare`, which reads its operands as little-endian numbers, on the reversed key bytes and curve order, never with `strcmp` on hex, which stops at the first differing digit.
- `HexCodec` keeps only `tryCanonical`, the rule for which spellings parse. It does not alias a conversion the language already provides.
- The identity types still share their logic through collaborators rather than a base class (ADR-0121): `HexCodec` holds the validation, and the conversion is a library call each type makes on its own canonical hex, which cannot fail.

## Consequences

- Secret key hex is read and written, and a secret scalar is range-checked, without a data-dependent branch.
- `HexCodec::encode` and `HexCodec::decode` are removed. A test pins their absence.
- Do not reintroduce a wrapper over `sodium_bin2hex` or `sodium_hex2bin`, and do not switch secret paths back to `bin2hex` / `hex2bin`.
