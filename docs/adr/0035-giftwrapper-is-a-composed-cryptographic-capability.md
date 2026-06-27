# 35. `GiftWrapper` is a composed cryptographic capability, and its envelope factory is a local injection seam

## Status

Accepted

## Context

NIP-17 gift-wrapping is a cryptographic operation: it seals a rumour and wraps the seal, each layer
encrypted under a NIP-44 conversation key derived through ECDH and signed. `GiftWrapper` performs that
operation. Unlike its neighbours in the crypto family — `Nip44Cipher`, `Nip04Cipher`, `Secp256k1Signer`,
`Secp256k1Ecdh`, which each reach a native library or `ext-sodium` directly — `GiftWrapper` touches no
primitive itself; it reaches encryption, signing, and ECDH only through injected domain-service
interfaces. Read against the layering rule that says a class with no direct external concern is
orchestration, it looks like it should sit in the application layer rather than among the crypto
implementations.

It composes other crypto capabilities, so three questions arise that read like smells:

1. **Why does it live with the cryptographic implementations rather than as an application service?**
   It implements `GiftWrapServiceInterface`, a cryptographic capability contract that sits beside
   `Nip44EncryptionInterface` and `SignatureServiceInterface`. Filing the contract as a crypto
   capability but its implementation as an application service would split one capability across two
   layers and make gift-wrapping the lone crypto operation not found with the others. NIP-17 is a
   cryptographic construction; that it is built from smaller cryptographic constructions does not make
   it orchestration of business workflow.

2. **Why is `GiftWrapEnvelopeFactoryInterface` defined alongside its implementation rather than in an
   inner layer?** Producing a gift wrap needs a fresh ephemeral key pair and two randomised timestamps;
   left to read entropy directly, the output is non-reproducible and the official test vectors cannot be
   asserted byte-for-byte. The factory is the seam that lets a test inject a fixed envelope to make the
   output deterministic — the same "inject the entropy source only where the output is under test"
   principle applied elsewhere for nonces. That seam has exactly one consumer, `GiftWrapper`, and never
   carries a dependency outward across a layer boundary: it is an intra-concern strategy interface, not
   an inversion point an inner layer owns. Its envelope (`GiftWrapEnvelope`) is likewise an internal
   detail of this one operation, not part of the shared domain vocabulary.

3. **Why does the constructor take four collaborators when more than three arguments is a design
   signal?** `GiftWrapper` is injected with NIP-44 encryption, a signature service, an ECDH service, and
   the envelope factory. The guideline that flags a fourth argument exists to catch a unit that has
   grown too many responsibilities, whose fix is to split it or to group genuinely cohesive arguments
   into a value object. Neither applies here: a NIP-17 gift wrap is irreducibly built from three
   distinct cryptographic primitives — encryption, signing, and ECDH — plus the entropy/time seam, and
   each is consumed independently on its own code path (the seal layer derives a key and signs; the wrap
   layer derives another key and signs; the factory supplies the ephemeral key pair and timestamps).
   They are four unrelated capabilities, not one cohesive concept, so bundling them into a parameter
   object would only dodge the count while hiding the real dependencies — the very smell the guideline
   warns against. Splitting the operation in two would fracture a single cryptographic construction
   across classes for no gain.

`GiftWrapper`, `GiftWrapEnvelope`, `GiftWrapEnvelopeFactoryInterface`, and `RandomGiftWrapEnvelopeFactory`
stay together in the crypto concern. `GiftWrapper` is treated as a composed cryptographic capability — a
NIP-17 construction built from lower-level crypto services — and lives with the other crypto
implementations, not as an application service. The envelope factory is a deliberately local injection
seam for deterministic output and is not elevated to a shared domain or application port: it has a single
consumer and abstracts only this operation's randomness and time. Its four-collaborator constructor is
accepted as it stands: the four are distinct, independently-consumed cryptographic capabilities, not a
cohesive group to fold into a parameter object, and the operation is not split to lower the count.

## Consequences

- A reviewer applying "no direct external concern ⇒ application orchestration" will read `GiftWrapper`
  as misplaced. It is not: it is a cryptographic operation kept with its kind, and a one-line fence
  points here.
- The envelope factory interface sits beside its implementation rather than in an inner layer. That is
  intentional for a single-consumer strategy seam; promoting it to a shared port would imply a
  reusability and an ownership it does not have.
- The constructor carries four arguments, past the point where the parameter-count guideline asks for a
  rethink. That is recorded here as deliberate, so a future reviewer does not "fix" it by inventing a
  cohesion-free parameter object or by splitting the seal and wrap layers into separate classes.
- If a second consumer ever needs gift-wrap envelopes, or the envelope becomes part of the shared
  vocabulary, the factory earns promotion to a proper port and this record is superseded.
