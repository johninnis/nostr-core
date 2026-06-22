# 4. `PublicKey`, `EventId`, and `Signature` stay separate types, not a shared base

## Status

Accepted

## Context

These three look alike — each wraps a fixed-length binary string and exposes `toHex` / `fromHex` / `equals` — so it is tempting to collapse them onto one `abstract readonly` base.

## Decision

Keep them as three independent `final` types.

1. **A shared base forfeits the type safety on `equals()`.** Pulled onto a base, `equals()` has to accept the base type (`equals(self $other)`), which makes `$publicKey->equals($eventId)` pass at PHPStan level 9 — exactly the identity confusion a crypto library must never allow silently. PHP's parameter contravariance then forbids narrowing that parameter back to the concrete type in each leaf, so `equals` must be redeclared per type anyway: the base saves nothing where it matters and removes a guarantee the analyser gives today.
2. **The shared logic is already factored out — into a collaborator, not a parent.** Hex validation and conversion live once in `HexCodec`, bech32 once in `Bech32Codec`, and every identity type routes through them. What is left in each class is a thin, type-specific surface, not duplicated logic. Sharing through a collaborator is composition; a base class here would be inheritance for incidental syntactic resemblance.
3. **They are not the same concept.** `PublicKey` and `EventId` are 32-byte, bech32-encodable identities; `Signature` is a 64-byte opaque blob with no bech32 form. The resemblance is a coincidence of width, not a shared abstraction.

## Consequences

- `equals()` stays type-safe: comparing a `PublicKey` to an `EventId` is an analyser error, not a silent `false`.
- Where logic reuse is genuine it goes through a collaborator, not a parent: `PrivateKey` and `ConversationKey` both compose a `SecretKeyMaterial` value object, which is the single home for secret-key validation and memory zeroing.
- Do not collapse these onto a base "to remove duplication" — the duplication is already gone, and the base would cost the `equals()` guarantee.
