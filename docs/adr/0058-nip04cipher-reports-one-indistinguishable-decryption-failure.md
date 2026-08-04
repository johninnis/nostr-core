# 58. `Nip04Cipher` reports one indistinguishable decryption failure and bounds its payload

## Status

Accepted

## Context

NIP-04 is AES-256-CBC over the raw ECDH shared secret with no MAC. The protocol has deprecated it and
this package marks both interface methods `#[Deprecated]`, but it still ships the cipher, so the
question of how it should behave on bad input has to be answered.

`decrypt` previously distinguished four rejection reasons by message: a missing `?iv=` separator,
non-base64 ciphertext, non-base64 IV, and a wrong-length IV, plus a fifth for the OpenSSL call itself
failing. Distinct, descriptive messages are the house default and are right almost everywhere — a
caller debugging a malformed payload wants to know which part was malformed.

They are wrong here, for one reason: the fifth message is not a report about the payload's shape, it
is a report about a **cryptographic** outcome. `openssl_decrypt` returns `false` when PKCS#7 unpadding
fails, so that message tells a submitter of ciphertext whether their guess produced valid padding. The
other four tell them only about framing they already control. Ranking those two kinds of failure in
the same error vocabulary invites a consumer to log or return them differentially, which publishes the
distinction.

`Nip44Cipher` faces the same shape and is safe from it for a reason NIP-04 cannot borrow: it verifies
an HMAC over `nonce‖ciphertext` before it unpads, so a tampered payload is rejected at the MAC and the
padding step is never reached with attacker-chosen input. NIP-04 has no MAC to put in front.

**What this record does not claim.** Collapsing the messages does not close the padding oracle, and it
must not be read as doing so. Without a MAC, valid padding means `openssl_decrypt` *succeeds* and
returns garbage plaintext, while invalid padding throws. The oracle signal is therefore "did decrypt
throw at all", which no choice of message can hide, and the remaining timing difference between an
early framing rejection and a full block-cipher pass is observable too. The oracle is a property of
unauthenticated CBC. It is closed by not using NIP-04.

## Decision

`Nip04Cipher::decrypt` rejects every malformed or undecryptable payload with the single message
`NIP-04 decryption failed`, and bounds the payload before doing any work.

- **One failure message.** Every rejection path throws `EncryptionException` carrying the same
  `DECRYPTION_FAILED` constant, so the library boundary never ranks a cryptographic failure above a
  framing one. This is a deliberate, bounded exception to the descriptive-message default, scoped to
  this one method: the value of naming which byte was wrong is smaller than the cost of teaching a
  consumer that these failures are different things worth reporting differently.
- **Bounded payload.** `MIN_PAYLOAD_LENGTH` is 52 — the shortest structurally possible payload, being
  base64 of one 16-byte AES block, the four-byte separator, and base64 of the 16-byte IV. Its purpose
  is to reject obvious rubbish before the base64 and cipher work, not to add security. The ceiling
  `MAX_PAYLOAD_LENGTH` is 87472, the same figure `Nip44Cipher` uses; NIP-04 specifies no cap of its
  own, and giving the two ciphers one identical memory bound is worth more than deriving a second
  number nobody can check against a spec.
- **`encrypt` is unchanged.** Its failure is not attacker-selected and it keeps its own descriptive
  message. No minimum or maximum is imposed on the plaintext, so payloads a caller can encrypt today
  still encrypt tomorrow.

## Consequences

- A consumer that surfaces `EncryptionException::getMessage()` to a peer no longer leaks which stage
  rejected the payload. **It still leaks the outcome**, which for an unauthenticated cipher is the
  part that matters — see the non-claim above. Consumers exposing NIP-04 decryption to untrusted input
  must rate-limit it and treat success-versus-failure as the sensitive signal, and should migrate to
  NIP-44.
- Debugging a malformed NIP-04 payload is harder: the exception no longer says whether the separator,
  the base64, the IV length, or the padding was at fault. That cost is accepted, and it is the reason
  this is recorded rather than left to read as sloppiness. Do not "improve" the messages back into
  distinct ones.
- The two ciphers now bound their payload identically, so a relay or client sizing buffers for one
  sizes them for the other.
- A test pins that the distinguishable rejection paths — bad separator, bad base64, wrong IV length,
  undecryptable ciphertext — all produce the identical message, and fails if any of them is given its
  own wording again.
