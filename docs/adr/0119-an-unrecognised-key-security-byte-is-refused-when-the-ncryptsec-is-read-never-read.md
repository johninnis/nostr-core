# 119. An unrecognised key-security byte is refused when the `ncryptsec` is read, never read as "not tracked"

## Status

Accepted

Supersedes ADR-0009. Its decision — a key-security byte other than `0x00`, `0x01` or `0x02` is rejected and never mapped to the "not tracked" value — is carried forward unchanged and is the shared decision, nostr-adrs ADR-0022. What is revised is where the rejection happens: ADR-0009 rejected the byte mid-decrypt through a throwing `KeySecurityByte::fromByte`, and it is now refused when the `ncryptsec` is parsed. This record holds the complete current decision.

## Context

NIP-49 defines the key-security byte as `0x00` (the key is known to have been handled insecurely), `0x01` (it is not known to have been) or `0x02` (the client does not track this). Because the protocol has a "not tracked" value, mapping any other byte to it looks like the tolerant reading. The byte is authenticated as associated data by the AEAD, so if an out-of-range byte were normalised to `0x02` before being used as associated data, a tampered byte and the genuine `0x02` would produce the same associated data and a tampered payload would decrypt. "Not tracked" is a valid value; a corrupt byte is not.

ADR-0009 enforced this with `KeySecurityByte::fromByte`, which threw `InvalidArgumentException` for an unrecognised byte, and `Nip49Cipher::decrypt` caught that exception to report a decryption failure. That put the check in the wrong place twice over. `Ncryptsec` held the byte as a bare integer (`getKeySecurityByteRaw()`), so a value its own parser had accepted could still be one NIP-49 does not define; and `InvalidArgumentException` signals a programmer error on trusted input, so catching it to answer an untrusted payload turned an exception into control flow. `fromByte` was otherwise `KeySecurityByte::from()` with a different exception type.

## Decision

- **An unrecognised byte is rejected, never mapped to `KeySecurityByte::Untracked`.** (Carried forward.)
- **It is rejected when the `ncryptsec` is read.** `Ncryptsec::tryFromString` returns `null` for a payload whose key-security byte is not one of the three NIP-49 values, exactly as it does for a wrong length or version byte. An `Ncryptsec` that exists therefore carries a valid byte, which it exposes as a `KeySecurityByte` through `getKeySecurity()`; there is no raw-integer accessor.
- `KeySecurityByte` is a plain backed enum with no `fromByte`. Its cases are named for what NIP-49 says each byte means: `KnownInsecure` (`0x00`), `NotKnownInsecure` (`0x01`) and `Untracked` (`0x02`). `Nip49Cipher::decrypt` reads the byte from `getKeySecurity()` and uses it as associated data; it catches nothing to do so.

## Consequences

- A tampered byte that lands outside the three values is refused before any key derivation, and one rewritten to another valid value fails the AEAD, so a tampered payload never decrypts.
- The genuine "not tracked" value stays distinct from a corrupt one.
- Breaking: `KeySecurityByte::fromByte` and `Ncryptsec::getKeySecurityByteRaw()` are removed; `Ncryptsec::getKeySecurity()` returns the enum. The cases formerly named `ClientSideOnly`, `UsableUntrusted` and `Unknown` read the reverse of NIP-49 for `0x00` and `0x01` and are renamed `KnownInsecure`, `NotKnownInsecure` and `Untracked`.
- Do not add a fallback to `Untracked`, and do not move the check back into decryption behind a caught exception. Tests pin the parse-time refusal and that a byte rewritten to another known value does not decrypt.
- Shared decision: nostr-adrs ADR-0022.
