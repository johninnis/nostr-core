# 115. Peer base64 is read only in its canonical form

## Status

Accepted

## Context

NIP-44 defines its encoding: "`base64_encode(string)` and `base64_decode(bytes)` are Base64 ([RFC 4648](https://datatracker.ietf.org/doc/html/rfc4648), with padding)", and the payload is "Base64-encode (with padding) params using `concat(version, nonce, ciphertext, mac)`". NIP-04 says the `content` "MUST be equal to the base64-encoded, aes-256-cbc encrypted string ... appended by the base64-encoded initialization vector". The NIP-98 `Authorization` header carries a base64-encoded event.

PHP's `base64_decode($x, true)` is strict only about the alphabet. It skips whitespace, accepts a string with its padding left off, and ignores non-zero bits in the last character: `QUI`, `QUJ=` and `QU I=` all decode to `AB`, whose one encoding is `QUI=`. Each byte string therefore has many accepted spellings, and a peer's payload decoded to bytes its writer never wrote in that form. The NIP-98 header was already read exactly; the two ciphers were not.

## Decision

- `Base64Codec::tryDecodeCanonical` decodes and then re-encodes; it returns the bytes only when the re-encoding is the input, so the input was padded, carried zero trailing bits, and held nothing outside the alphabet. Anything else is `null`.
- `Nip04Cipher::decrypt` reads both the ciphertext and the IV through it, `Nip44Cipher::decrypt` reads the payload through it, and `NostrAuthHeaderCodec::decode` reads everything after the scheme through it. A refusal is each one's existing failure: NIP-04's single decryption failure (ADR-0118), NIP-44's invalid base64 payload, the header's `BadBase64`.

## Consequences

- A payload from a writer that leaves off padding or wraps lines is refused. That writer is not following RFC 4648 "with padding", which both NIPs name.
- There is one canonical-base64 reader; do not reintroduce a bare `base64_decode($x, true)` on a value a peer wrote.
- Shared decision: nostr-adrs ADR-0095.
