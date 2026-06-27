# 30. NIP-49 floors what it encrypts at logN 16 but accepts weaker on decrypt, with a configurable decrypt ceiling

## Status

Accepted

## Context

NIP-49 wraps a private key under a password using scrypt with a caller-chosen `logN`. `logN` is the work factor, and it cuts both ways: too low (e.g. `1`) makes the password trivially brute-forceable, so a stolen `ncryptsec` is opened in moments; too high (`22` → roughly 4 GiB of scrypt memory) turns a single decryption into a memory-exhaustion vector. The cipher both *mints* `ncryptsec` (encrypt) and *consumes untrusted* `ncryptsec` (decrypt). A single `[1, 22]` range applied to both directions reads as the consistent choice, and a different minimum on encrypt versus decrypt reads as an oversight.

## Decision

The two directions take different bounds because they face different risks.

- **Encrypt floors `logN` at 16**, the ecosystem-standard default. The library never mints a weak-KDF `ncryptsec`, even if a caller passes a low value (a low value is rejected, not silently accepted). It still allows up to 22 for stronger protection.
- **Decrypt accepts `logN` from 1 upward** for interoperability — it must read an `ncryptsec` minted anywhere, including weaker ones produced by other tools — but bounds the *upper* end by a constructor-injected `maxDecryptLogN`. That ceiling defaults to the spec maximum (22) so no spec-valid `ncryptsec` is silently refused, and is itself capped at 22. A host that decrypts attacker-supplied `ncryptsec` lowers it to bound scrypt's memory cost.

## Consequences

- The library never produces a brute-forceable `ncryptsec`, while still reading any spec-valid one — the asymmetry buys both safety on what we create and interoperability on what we accept.
- The memory-exhaustion exposure on decrypt is controllable at the integration boundary rather than hard-coded: the default preserves full interoperability, and a memory-constrained or hostile-input deployment opts into a stricter ceiling.
- The asymmetry (encrypt minimum 16, decrypt minimum 1) is deliberate. Do not "unify" the two minima: raising the decrypt floor breaks interoperability with weaker `ncryptsec`, and lowering the encrypt floor lets the library mint weak keys. A fence at `ENCRYPT_LOG_N_MIN` points here.
