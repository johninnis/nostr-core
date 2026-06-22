# 9. `KeySecurityByte::fromByte` throws on an unrecognised byte

## Status

Accepted

## Context

`KeySecurityByte` has an `Unknown` (`0x02`) case, so an out-of-range byte looks like it should map to `Unknown` rather than throw. Under NIP-49 the key-security byte is authenticated as associated data by the AEAD, and `fromByte` is only ever called mid-decrypt.

## Decision

`KeySecurityByte::fromByte` throws on an unrecognised byte instead of falling back to `Unknown`.

Throwing on an out-of-range byte is precisely what rejects a *tampered* `ncryptsec`: if the byte were silently mapped to `Unknown`, flipping the stored byte to any other value would still yield the same associated data and the tampered payload would decrypt. `Unknown` is a valid spec value, distinct from a corrupt one — they must not be conflated.

## Consequences

- A tampered key-security byte is rejected at decrypt time rather than silently normalised.
- The valid `Unknown` spec value stays distinct from a corrupt byte.
- This regressed once; `Nip49CipherTest::testDecryptRejectsUnknownKeySecurityByte` guards it. Do not add a fallback-to-`Unknown` branch.
