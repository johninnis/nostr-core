# 117. NIP-44 carries the extended length prefix, up to a configurable maximum that defaults to 256 KiB

## Status

Accepted

## Context

NIP-44 now allows a plaintext of "Minimum is 1 byte, maximum is 4,294,967,295 bytes". A length below 65536 keeps the two-byte `u16` prefix; "If length is 65536 or greater: prefix is 6 bytes (2 zero bytes + `u32`)", and "A zero value in the first 2 bytes signals the extended format". On decryption, `unpad` raises "invalid padding" when the extended prefix encodes a length below 65536. The padding calculation is unchanged and, as the NIP notes, "can return values up to 2^32", so it needs 64-bit arithmetic.

`Nip44Cipher` refused any plaintext above 65535 bytes and any payload above 87472 base64 characters, the largest a `u16` length could produce. The NIP leaves the memory question to the implementation: "Implementations SHOULD enforce their own maximum payload size based on platform and resource constraints, rejecting oversized payloads early in `decode_payload` (before base64 decoding)". No record of this library set a NIP-44 bound of its own; ADR-0058 (now ADR-0118) only gave NIP-04 the figure NIP-44 then used.

The same section's first decryption step says: "Check if first payload's character is `#`" … "instead of throwing `base64 is invalid`, implementations MUST indicate that the encryption version is not yet supported". `decrypt` reported such a payload as invalid base64.

## Decision

- `Nip44Cipher` writes the two-byte prefix below 65536 and the six-byte prefix (`0x00 0x00` then a big-endian `u32`) from 65536 up, for any plaintext from 1 byte to its configured maximum.
- Decryption reads a non-zero `u16` as the length; a zero `u16` makes it read the next four bytes as a `u32`, and a `u32` below 65536 is invalid padding, as is a zero length or one that runs past the padded plaintext. The padded length is then checked against the padding calculation with the prefix's own width.
- The maximum plaintext is a constructor argument, `maxPlaintextLength`, defaulting to `Nip44Cipher::DEFAULT_MAX_PLAINTEXT_LENGTH`, 262144 bytes (256 KiB). It may be set from 1 to the NIP's 4294967295; anything else is an `InvalidArgumentException`. The default covers every message a Nostr client exchanges and every vector the NIP publishes, extended-prefix ones included, while keeping one `decrypt` to a few megabytes of working memory. A host that must carry more passes a larger maximum.
- `encrypt` refuses a plaintext over the maximum with `EncryptionException`.
- `decrypt` refuses a payload longer than the ceiling before decoding it, as the NIP asks. The ceiling is derived from the maximum by the NIP's own formulas, not chosen separately: 4 × ⌈(1 + 32 + prefix + `calc_padded_len(max)` + 32) / 3⌉, with the prefix 2 below 65536 and 6 from it. `calc_padded_len` never decreases, so no plaintext within the maximum yields a longer payload. At the default it is 349620 characters.
- Plaintexts that share the maximum's padded length are not all within it, so after the padding is read `decrypt` also refuses a declared length over the maximum, and the maximum is exact on both sides.
- `decrypt` checks, in this order: the ceiling, the `#` flag, the floor, then base64 decoding. A payload over the ceiling is refused as out of bounds by its length alone, before any character of it is read, so an oversized payload starting with `#` is out of bounds. A payload within the ceiling whose first character is `#` is then refused as `Unsupported NIP-44 version: non-base64 encoding`, whatever its length, one under the floor included: a `#` payload is not base64, need not have a base64 payload's length, and NIP-44 requires it be reported as unsupported. Only then is a payload under 132 characters refused as out of bounds, and after decoding one under 99 bytes.
- NIP-04's ceiling is its own, recorded in ADR-0118.

## Consequences

- A payload of more than 65535 bytes from a current NIP-44 writer now opens, and one this library writes opens in any implementation of the extended format. An implementation still on the `u16`-only text refuses it.
- At the default, a payload from another implementation carrying more than 256 KiB of plaintext does not open. The host that needs it raises the maximum, and in doing so takes on the memory a larger `decrypt` holds.
- The official vector file (`269ed0f6…`) predates the extended format and still lists 65536, 100000 and 10000000 as invalid plaintext lengths. The compliance test asserts only the lengths the current NIP refuses, and checks the NIP's own extended-prefix vectors at 65535, 65536 and 65537 by SHA-256 checksum.
- The ceiling is tested at the default: a 262144-byte plaintext round-trips and its payload is exactly 349620 characters, a 262145-byte plaintext is refused, a 349620-character payload reaches the base64 decoder, a 349621-character one is refused before it, as is an over-ceiling payload that starts with `#`, while `#abc`, under the floor, is reported as an unsupported version, and a payload sealed under a raised maximum is refused by the default. A raised maximum opens a 70000-byte plaintext and the NIP's extended-prefix vectors; a lowered one refuses a plaintext over it that shares its padded length. The NIP's own 4294967295 is tested only as the bound on the configured maximum, since a plaintext that size takes four gigabytes.
- A gift wrap carries its rumour twice over: the seal's content is the rumour's NIP-44 payload, and the seal's JSON is then the wrap's plaintext, under the same maximum. At the default, a rumour serialising to 163840 bytes pads to 163840 (`calc_padded_len` works in 32768-byte chunks between 131072 and 262144), which gives a seal payload of 4 × ⌈(1 + 32 + 6 + 163840 + 32) / 3⌉ = 218548 characters, and the seal's JSON fits 262144 bytes with room for its fixed fields; one byte more pads to 196608, whose payload of 262240 characters alone exceeds the maximum. `GiftWrapper::MAX_RUMOUR_LENGTH` is therefore 163840 bytes, and `wrapForRecipient` and `wrapForChatRoom` each refuse a rumour that serialises to more with a `GiftWrapException` naming that limit, before they encrypt or sign anything, rather than failing on the second encryption after the seal is signed. The limit is the default cipher's; a `GiftWrapper` built over a cipher with a raised maximum still refuses above it. It is tested at 163840 bytes (wraps and unwraps) and 163841 (refused, with no encryption and no envelope drawn).
- Do not move the ceiling after a read of the payload's characters, nor the floor before the `#` flag; the `#abc` and oversized-`#` tests pin both.
- Shared decisions: nostr-adrs ADR-0102 (the NIP-44 bound and the order of the decryption checks) and ADR-0018 (NIP-04's ceiling is its own).
