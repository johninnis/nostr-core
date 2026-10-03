# 114. NIP-49 floors what it encrypts at logN 16, and bounds what it decrypts at a library ceiling of 22 that limits scrypt memory

## Status

Accepted

Supersedes ADR-0030. Its decision is carried forward unchanged: encryption is floored at `logN` 16, decryption accepts `logN` from 1, and decryption is bounded above by a configurable `maxDecryptLogN` that defaults to 22 and cannot be set above it. What is revised is the reason for 22. ADR-0030 called it "the spec maximum" and said the default refuses no "spec-valid" `ncryptsec`. NIP-49 sets no maximum, so that premise was false, and this record states the real one.

## Context

NIP-49 says of the work factor: "LOG_N = Let the user or implementer choose one byte representing a power of 2 (e.g. 18 represents 262,144) which is used as the number of rounds for scrypt. Larger numbers take more time and more memory, and offer better protection". It follows with a table of memory costs that ends at 22 (4 GiB). The field is one byte, so an `ncryptsec` may carry any `logN` up to 255, and nothing in the NIP bounds it.

scrypt's memory cost doubles with each step of `logN`, and it is paid before the AEAD can reject a wrong key: the scrypt output is the AEAD key. Decrypting an `ncryptsec` therefore commits the host to whatever memory its author chose. At 22 that is 4 GiB; at 23 it is 8 GiB, more than most hosts can give one request; past that a single decryption can exhaust any machine. An `ncryptsec` is often attacker-supplied — pasted into a sign-in form, fetched from a remote signer — so a decoder with no ceiling is a memory-exhaustion endpoint.

The two directions face different risks. Encrypting mints a key someone may later steal, so a low `logN` makes it brute-forceable; decrypting reads keys minted anywhere, including by tools that chose a low value.

## Decision

- **Encryption floors `logN` at 16** and accepts up to 22. The library never mints a weak-KDF `ncryptsec`; a lower value is refused, not raised.
- **Decryption accepts `logN` from 1** so that a key minted at a low cost elsewhere still opens.
- **Decryption is bounded above by `maxDecryptLogN`**, configured on `Nip49WorkFactor`, defaulting to 22 and refused above 22. The ceiling is this library's limit, not NIP-49's: it is the largest work factor NIP-49's own table describes and the largest whose memory (4 GiB) a host can plausibly provide, and it bounds what an attacker-supplied `ncryptsec` can make one decryption allocate. A host that decrypts untrusted input lowers it further.
- **A refused work factor is its own fault.** `Nip49Cipher::decrypt` throws `Nip49WorkFactorRefusedException` for a `logN` outside the range it decrypts, before asking for the password, and `Nip49DecryptionFailedException` only once the AEAD or the key it yields is rejected. A wrong password can be retried; a refused work factor never opens however often it is tried, so a host must be able to tell them apart and must not report the second as the first.

## Consequences

- An `ncryptsec` minted at `logN` 23 or above by another implementation is valid NIP-49 that this library refuses to decrypt. That is the price of the bound, accepted because the alternative is letting any input demand 8 GiB or more; a host that genuinely needs such keys cannot raise the ceiling here and decrypts them with a tool it trusts with that memory.
- Do not describe 22 as the NIP's maximum, and do not raise the cap "to match the spec": the spec has no cap to match.
- The asymmetry of the two minima (16 on encrypt, 1 on decrypt) is deliberate. Do not unify them: raising the decrypt floor breaks interoperability with weaker keys, and lowering the encrypt floor lets the library mint them. A fence on `Nip49WorkFactor` points here.
- Do not fold the work-factor refusal back into `Nip49DecryptionFailedException`, and do not make either extend the other: a catch for a wrong password must not swallow a key that can never open.
- Shared decision: nostr-adrs ADR-0021.
