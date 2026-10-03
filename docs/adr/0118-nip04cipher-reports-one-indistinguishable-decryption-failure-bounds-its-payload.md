# 118. `Nip04Cipher` reports one indistinguishable decryption failure, bounds its payload, and never writes a payload it would refuse

## Status

Accepted

Supersedes ADR-0058. Its single indistinguishable decryption failure and its 52-character floor are carried forward unchanged. What is revised is the reason for the 87472-character ceiling, which ADR-0058 tied to `Nip44Cipher`'s, and `encrypt`, which ADR-0058 left uncapped so that it could write a payload its own `decrypt` refuses. This record holds the complete current decision.

## Context

NIP-04 is AES-256-CBC over the raw ECDH shared secret with no MAC. The protocol has deprecated it and this package marks both interface methods `#[Deprecated]`, but it still ships the cipher, so how it behaves on bad input has to be answered.

`decrypt` once distinguished four rejection reasons by message: a missing `?iv=` separator, non-base64 ciphertext, non-base64 IV, and a wrong-length IV, plus a fifth for the OpenSSL call itself failing. Distinct, descriptive messages are the house default and are right almost everywhere. They are wrong here, for one reason: the fifth message is not a report about the payload's shape, it is a report about a cryptographic outcome. `openssl_decrypt` returns `false` when PKCS#7 unpadding fails, so that message tells a submitter of ciphertext whether their guess produced valid padding. Ranking it beside framing failures the submitter already controls invites a consumer to log or return them differentially, which publishes the distinction.

`Nip44Cipher` is safe from the same shape for a reason NIP-04 cannot borrow: it verifies an HMAC over `nonce‖ciphertext` before it unpads, so a tampered payload never reaches the padding step. NIP-04 has no MAC to put in front.

**What this record does not claim.** Collapsing the messages does not close the padding oracle. Without a MAC, valid padding means `openssl_decrypt` succeeds and returns garbage plaintext, while invalid padding throws. The oracle signal is "did decrypt throw at all", which no choice of message hides, and the timing of an early framing rejection differs from that of a full block-cipher pass. The oracle is a property of unauthenticated CBC and is closed only by not using NIP-04.

NIP-04 sets no length limit on either side. ADR-0058 took its ceiling from `Nip44Cipher`, whose `u16` length prefix then capped a payload at 87472 base64 characters, and justified it as one memory bound for both ciphers. NIP-44 has since gained an extended length prefix and `Nip44Cipher` a configurable maximum of its own (ADR-0117), so that justification no longer holds. ADR-0058 also imposed nothing on `encrypt`, so a plaintext of 65568 bytes or more encrypted to a payload that the same cipher's `decrypt` refused.

## Decision

- **One failure message.** Every `decrypt` rejection throws `EncryptionException` carrying the single `DECRYPTION_FAILED` message, `NIP-04 decryption failed`: a missing separator, bad base64 in either part, a wrong-length IV, a payload outside its bounds, an `openssl_decrypt` failure, and a plaintext that is not UTF-8 (ADR-0116). This is a deliberate exception to the descriptive-message default, scoped to this one method.
- **A floor of 52.** `decrypt` refuses a payload shorter than 52 characters before any base64 or cipher work: base64 of one 16-byte AES block, the four-character `?iv=`, and base64 of the 16-byte IV. It rejects obvious rubbish early and adds no security.
- **A ceiling of 87472, as this library's own bound.** `decrypt` refuses a payload longer than 87472 characters before any base64 or cipher work. NIP-04 gives no figure, so any ceiling is a library choice. This one is kept because it is the figure the library has applied since `decrypt` was first bounded, so no payload that opened before stops opening; NIP-04 carries direct-message text, for which 64 KiB is ample; and raising it would only admit larger frames from a protocol the library ships for legacy history. It is not derived from NIP-44 and does not move when NIP-44's bound does.
- **`encrypt` never writes what `decrypt` refuses.** `encrypt` refuses a plaintext over 65567 bytes with `EncryptionException` (`NIP-04 plaintext must be at most 65567 bytes`), its own descriptive message, since the failure is the host's input and not attacker-selected. 65567 is the largest plaintext whose payload fits 87472: PKCS#7 pads it to 65568 bytes, base64 of that is 87424 characters, and with `?iv=` and the 24-character IV the payload is 87452. A plaintext of 65568 pads to 65584 bytes, 87448 characters of base64, and a payload of 87476. The check runs before an IV is drawn.

## Consequences

- A consumer that surfaces `EncryptionException::getMessage()` from `decrypt` to a peer does not leak which stage rejected the payload. It still leaks the outcome, which for an unauthenticated cipher is the part that matters. Consumers exposing NIP-04 decryption to untrusted input must rate-limit it, treat success-versus-failure as the sensitive signal, and should migrate to NIP-44.
- Debugging a malformed NIP-04 payload is harder, since the exception does not say which part was at fault. That cost is accepted. Do not give the rejection paths distinct messages again.
- A NIP-04 payload from another implementation longer than 87472 characters does not open here. The two ciphers no longer share a bound, so a host sizing buffers for one sizes the other separately.
- A host that encrypted a NIP-04 plaintext over 65567 bytes now gets an exception instead of a payload no copy of this library could open.
- Tests pin that every distinguishable `decrypt` rejection produces the identical message, that a 65567-byte plaintext round-trips, and that a 65568-byte plaintext is refused.
- Shared decisions: nostr-adrs ADR-0001, for the failure being one thrown fault that an operation opening a peer's ciphertext converts to its returned outcome, and ADR-0018, which decides the one failure message, the 52-character floor, the 87472-character ceiling as the libraries' own figure rather than NIP-04's, and the 65567-byte encryption cap. This record adds what is particular to this package: `EncryptionException` and its four messages (the one decryption failure, and the encryption refusals of an oversized plaintext, a plaintext that is not UTF-8 and a failed `openssl_encrypt`), the `#[Deprecated]` interface methods, and the UTF-8 refusal of ADR-0116.
