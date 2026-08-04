# 61. A `KeyPair` can only be minted with its public key derived from its private key

## Status

Accepted

## Context

`KeyPair` pairs a `PrivateKey` with a `PublicKey`. Its constructor was public and took both, so nothing
stopped a caller assembling a pair whose public key does not correspond to its private key.

That is not merely untidy. `Rumour::sign` guards the pairing it can see — it rejects a key pair whose
public key differs from the rumour's `pubkey` — but it cannot check the pair against *itself*. Given an
inconsistent pair, the guard passes, the event is stamped with the pair's public key, and the signature
is made with the pair's private key. The result is a well-formed `Event` whose signature does not
verify against its own `pubkey`. It was reproducible in three lines, and produced an event that
`Event::verify` rejects.

Nothing in production built such a pair — `KeyPair::generate` and `KeyPair::fromPrivateKey` both derive
— so this was a footgun rather than a live defect. But it made `KeyPair` the one value object in the
package whose constructor admits a state its own invariant forbids. `PrivateKey` rejects a scalar
outside the curve order, `Signature` rejects anything but a full 64 bytes, `EventCoordinate` rejects a
non-addressable kind, and the NIP-19 entities refuse to exist when they cannot be encoded. Every one of
those holds its invariant by keeping the constructor private and validating in a named constructor.
`KeyPair` did not.

Deriving inside a public constructor is not the alternative: derivation needs a
`SignatureServiceInterface`, and threading a service into a value object's constructor to validate it
would put a collaborator where the package keeps data.

## Decision

`KeyPair::__construct` is private. A pair is minted only through `generate(SignatureServiceInterface)`
or `fromPrivateKey(PrivateKey, SignatureServiceInterface)`, both of which derive the public key from the
private one. Consistency is therefore established at construction and cannot be bypassed.

## Consequences

- Holding a `KeyPair` is proof its public key derives from its private key, so `Rumour::sign` can trust
  the pair it is handed and only needs to check it against the rumour.
- Breaking: `new KeyPair($private, $public)` no longer compiles. Callers use `KeyPair::fromPrivateKey`.
  A caller that already has both keys pays one derivation; that cost buys the invariant, and it is the
  same trade every other value object here makes.
- **Do not restore a public constructor to avoid the derivation.** The saving is one elliptic-curve
  multiply and the cost is a pair that can lie about itself — which surfaces only as an event that
  silently fails verification somewhere downstream, far from the construction that caused it. A test
  pins the constructor's visibility and fails if it is made public again.
- Test fixtures that hard-code a matching `(private, public)` pair now derive and compare against the
  documented public key rather than asserting it. The fixture constants are checked instead of trusted,
  which is strictly better: a wrong constant now fails loudly at first use.
