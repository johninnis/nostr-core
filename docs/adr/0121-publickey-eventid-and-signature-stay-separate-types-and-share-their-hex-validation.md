# 121. `PublicKey`, `EventId` and `Signature` stay separate types, and share their hex validation through a collaborator

## Status

Accepted

Supersedes ADR-0004. Its decision — the three stay independent `final` types rather than leaves of a shared base — and its reasons are carried forward unchanged. What is revised is one statement of fact in its second reason: it said hex validation and conversion live in `HexCodec`, and conversion is now a call to sodium's constant-time codec that each type makes on its own canonical hex (ADR-0120). This record holds the complete current decision.

## Context

These three look alike — each wraps a fixed-length binary string and exposes `toHex` / `tryFromHex` / `equals` — so it is tempting to collapse them onto one `abstract readonly` base.

## Decision

Keep them as three independent `final` types. (Carried forward.)

1. **A shared base forfeits the type safety on `equals()`.** Pulled onto a base, `equals()` has to accept the base type (`equals(self $other)`), which makes `$publicKey->equals($eventId)` pass at PHPStan level 9 — exactly the identity confusion a crypto library must never allow silently. PHP's parameter contravariance then forbids narrowing that parameter back to the concrete type in each leaf, so `equals` must be redeclared per type anyway: the base saves nothing where it matters and removes a guarantee the analyser gives today. (Carried forward.)
2. **The shared logic is already factored out — into collaborators, not a parent.** Which hex spellings parse is decided once, in `HexCodec::tryCanonical`; bech32 lives once in `Bech32Codec`; and the conversion between hex and bytes is the language's own `sodium_bin2hex` / `sodium_hex2bin` (ADR-0120). What is left in each class is a thin, type-specific surface, not duplicated logic. Sharing through a collaborator is composition; a base class here would be inheritance for incidental syntactic resemblance. (Revised: the conversion is no longer a `HexCodec` method.)
3. **They are not the same concept.** `PublicKey` and `EventId` are 32-byte, bech32-encodable identities; `Signature` is a 64-byte opaque blob with no bech32 form. The resemblance is a coincidence of width, not a shared abstraction. (Carried forward.)

## Consequences

- `equals()` stays type-safe: comparing a `PublicKey` to an `EventId` is an analyser error, not a silent `false`.
- Where logic reuse is genuine it goes through a collaborator, not a parent: `PrivateKey` and `ConversationKey` both compose a `SecretKeyMaterial` value object, which is the single home for secret-key validation and memory zeroing.
- Do not collapse these onto a base "to remove duplication" — the duplication is already gone, and the base would cost the `equals()` guarantee.
