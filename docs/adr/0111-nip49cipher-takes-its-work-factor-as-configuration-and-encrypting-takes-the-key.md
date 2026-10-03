# 111. `Nip49Cipher` takes its work factor as configuration, and encrypting takes the key, the password and the key-security byte

## Status

Accepted

Supersedes ADR-0055. Its points that the password stays a `Closure` and the key-security byte is set by the caller are carried forward. What is revised is the shape: ADR-0055 accepted four inputs on `encrypt` and on the derive step, and this record holds the complete current decision.

## Context

ADR-0055 recorded `Nip49Cipher::encrypt(key, password, logN, keySecurity)` and the internal derive-key-and-run step (password, salt, cost, consuming closure) as four distinct inputs each, and fenced both. Four inputs to one call usually mean the call carries something that belongs elsewhere, and a fence that exempts it leaves that thing where it is.

`logN` was never a per-key decision. It is the cost the host is willing to pay, and the cipher already held its other half: `maxDecryptLogN`, the ceiling it will read (ADR-0114). The two together are the scrypt work-factor policy: the cost it mints at, floored at 16, and the cost it accepts, from 1 up to a ceiling. The derive step's fourth input, the consuming closure, existed only to wipe the derived key after use.

## Decision

- `Nip49WorkFactor` (`Domain\ValueObject\Identity`) holds `encryptLogN` (default 16, from 16 to 22) and `maxDecryptLogN` (default 22, from 1 to 22), refusing either outside its range, and a `maxDecryptLogN` below `encryptLogN` (a cipher that would refuse its own ncryptsec), with `InvalidArgumentException`. `admitsForDecryption(logN)` answers the decrypt check.
- `Nip49Cipher` takes the scrypt adapter, the random-bytes source and a `Nip49WorkFactor`; `Nip49Cipher::create(workFactor, ?randomBytes)` probes for scrypt as before (ADR-0041).
- `Nip49EncryptionInterface::encrypt(privateKey, passwordProvider, keySecurity = KeySecurityByte::Untracked)` encrypts at the work factor's `encryptLogN`.
- The derive step takes the password source, the salt and the cost, and returns the derived key; `encrypt` and `decrypt` zero it in a `finally` once the AEAD call returns.

## Consequences

- A host that chose a cost per call chooses it per cipher: `Nip49Cipher::create(new Nip49WorkFactor(encryptLogN: 18))`.
- The floor and ceiling of ADR-0114 are enforced in one place, when the work factor is built.
- Neither `encrypt` nor the derive step carries a fence.
- Shared decision: nostr-adrs ADR-0021.
