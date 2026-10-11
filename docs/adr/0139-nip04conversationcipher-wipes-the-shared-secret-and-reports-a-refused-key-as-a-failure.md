# 139. `Nip04ConversationCipher` wipes the shared secret and reports a refused peer key as NIP-04's own failure

## Status

Accepted

## Context

`ConversationCipher` encrypts to and decrypts from a peer under NIP-44: it derives the conversation key, runs the cipher and wipes the key (ADR-0109). NIP-04 has the same shape — the raw ECDH shared x between a private key and a peer's public key, used once by `Nip04Cipher` — but the package offered no counterpart, so every caller still speaking NIP-04 to a peer computed the shared secret, ran the cipher and had to remember to wipe the secret itself. innis/nostr-nip46 kept its own copy for exactly that, waiting for this package to ship one.

The key agreement can refuse the peer's key: an x-coordinate outside the field, one with no curve point, or a shared point at infinity, each an `EcdhException`. On decryption that key comes from whoever sent the payload. `Nip04Cipher::decrypt` reports every payload it cannot decrypt with the one message `NIP-04 decryption failed` and no cause (ADR-0118); letting the key agreement's own exception through would add a second, distinguishable failure for one more thing a submitter controls. @innis/nostr-core's `nip04Encrypt` and `nip04Decrypt` already report an off-curve peer key as their own NIP-04 error, and the two libraries are twins: the same input must meet the same failure in both, so the order of the checks matters as much as the checks. `nip04Encrypt` judges the plaintext, UTF-8 first and then its length, before the key agreement; `nip04Decrypt` judges the payload's length before it.

## Decision

- `Nip04ConversationCipher` (`Domain\Service`) implements `ConversationCipherInterface`, beside `ConversationCipher`. It takes `Nip04EncryptionInterface` and `EcdhServiceInterface`, computes the shared x between the own key and the peer, runs the cipher under it, and zeroes it in a `finally`, whether the cipher succeeds or throws.
- The input is judged before the key agreement, in @innis/nostr-core's order. `encrypt` refuses a plaintext that is not UTF-8, then one over 65567 bytes; `decrypt` refuses a payload outside 52 to 87472 characters with `NIP-04 decryption failed`. `Nip04InputValidator` holds these checks and `Nip04Cipher` applies the same ones, so the rule is written once.
- A peer key the key agreement refuses is the cipher's own failure. `decrypt` throws `EncryptionException` with `NIP-04 decryption failed` and no cause, exactly as for any payload it cannot decrypt. `encrypt` throws `EncryptionException` with `NIP-04 encryption failed`, the refusal kept as its cause.
- Every other failure stays the primitive's: `Nip04Cipher`'s own refusals pass through unchanged, and a fault such as zeroed key material propagates.
- Both methods carry `#[Deprecated]`, as `Nip04Cipher`'s do: NIP-04 is unauthenticated and deprecated, and this class exists for legacy peers.

## Consequences

- A caller handing a peer's NIP-04 payload to the cipher converts one exception type, `EncryptionException`, at the edge where the payload enters (ADR-0089), and a fault in its own machinery still propagates.
- The shared secret's derive-use-wipe sequence exists once, here.
- Every input meets the same failure as in @innis/nostr-core, in the same order: a plaintext refused for its encoding or length is reported as such even when the peer key is refused too, and an over-long payload is refused without a key agreement.
- The plaintext and payload-length checks run twice on this path, once here and once in `Nip04Cipher`, which keeps them for a caller using it directly. Both read one rule.
- Do not let the key agreement's exception escape `decrypt`: it would be a second, distinguishable failure. The NIP-44 derivation reports its refusal as NIP-44's own failure in the same way, with a descriptive message (ADR-0089).
