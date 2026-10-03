# 116. A plaintext that is not UTF-8 is refused by encryption and is a decryption failure

## Status

Accepted

## Context

NIP-44 fixes the plaintext's encoding on both sides. Encryption step 4 says "Content must be encoded from UTF-8 into byte array", and the reference decryption ends `return utf8_decode(unpadded)`. NIP-04 names no encoding in its prose, but its plaintext is the `content` of a kind-4 direct message, "anything a user wants to write", and its reference code encrypts `cipher.update(text, 'utf8', 'base64')`.

`Nip44Cipher::decrypt` and `Nip04Cipher::decrypt` returned the unpadded bytes as they were. A peer, or a sender's bug, could seal bytes that are not UTF-8 under a valid MAC or a valid CBC padding, and the caller received a PHP string that every later step treats as text: a JSON encoder refuses it, a renderer mangles it, and a NIP-17 rumour or a NIP-46 request inside it is read from bytes no conforming writer produced.

## Decision

- `Nip44Cipher::decrypt` and `Nip04Cipher::decrypt` check the plaintext with `mb_check_encoding($plaintext, 'UTF-8')` after the MAC and padding checks, zero it, and refuse it through each cipher's existing decryption failure: NIP-44's `EncryptionException`, with its own message, and NIP-04's single indistinguishable failure (ADR-0118). An operation opening a peer's ciphertext converts that throw to its returned failure as it does any other (ADR-0089).
- A leading byte order mark is valid UTF-8 and is kept: the plaintext is returned byte for byte, never stripped or normalised.
- `Nip44Cipher::encrypt` and `Nip04Cipher::encrypt` refuse a plaintext that is not UTF-8 with `EncryptionException`, before anything is sealed. NIP-44's step 4 says the content "must be encoded from UTF-8", and a cipher that sealed bytes its own `decrypt` refuses would hand its caller a payload no conforming reader opens (ADR-0128). The host's plaintext is a fault, not a peer's outcome, so it is thrown rather than returned.

## Consequences

- A gift wrap, a NIP-46 envelope or a direct message whose plaintext is not UTF-8 fails to open, exactly as one with a bad MAC does, and this package never writes one.
- A caller holding binary data encodes it (base64, hex) before encrypting it.
- The property tests draw their plaintexts from UTF-8 strings, since random bytes are not a plaintext NIP-44 can carry; the decryption refusal is tested on a payload sealed by hand.
- Shared decision: nostr-adrs ADR-0100.
