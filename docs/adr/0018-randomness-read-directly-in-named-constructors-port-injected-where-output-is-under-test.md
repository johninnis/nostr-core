# 18. Random generation reads the entropy source directly in named constructors; `RandomBytesGeneratorInterface` is injected where the random output is under test

## Status

Accepted

## Context

Generating random bytes is a side effect, which the functional design pushes to the edges behind a port. Taken literally that would inject a `RandomBytesGeneratorInterface` into every named constructor that mints a fresh value — `SecretKeyMaterial::random()`, `PrivateKey::generate()`, `KeyPair::generate()`, `SubscriptionId::generate()` / `short()`, `Timestamp::randomised()` — including value-object construction in the Domain layer.

Those named constructors live in the Domain layer, which depends on nothing outside itself. `RandomBytesGeneratorInterface` is a port for an outside capability (the operating system's entropy source); threading it into Domain value objects would make the Domain depend on a port purely to parameterise construction that has no random-dependent behaviour to exercise.

This is the same shape already settled for reading wall-clock time. `Timestamp::now()` reads the clock directly in construction and glue, and `ClockInterface` is injected only where elapsed time is the thing being decided (the NIP-98 tolerance window). Randomness is the sibling side effect and is treated the same way, so the two stay symmetric rather than one being threaded through Domain construction and the other not.

## Decision

Read the entropy source directly in the named constructors that mint a fresh value, and inject `RandomBytesGeneratorInterface` only where the random output is the behaviour under test.

- A named constructor whose whole job is to produce a fresh random value — `SecretKeyMaterial::random()`, `PrivateKey::generate()`, `KeyPair::generate()`, `SubscriptionId::generate()` / `short()`, `Timestamp::randomised()` — reads the entropy source directly (`random_bytes` / `random_int`). There is no random-dependent decision to reproduce, so a test asserts only that successive calls differ.
- An operation whose correctness depends on a *specific* random value — a NIP-44 nonce, the BIP-340 auxiliary randomness — takes a `RandomBytesGeneratorInterface` so a test can supply a fixed value and reproduce a known answer byte-for-byte. These live in Infrastructure (`Nip44Cipher`, `Secp256k1Signer`, `Secp256k1Ecdh`, `Nip04Cipher`, `Nip49Cipher`) and inject the port; production defaults to `NativeRandomBytesGenerator`.
- A caller that needs a bespoke entropy source for key material (an HSM, a deterministic generator) brings its own bytes through the parsing constructors — `SecretKeyMaterial::fromBytes()`, `PrivateKey::fromHex()` — rather than through the random named constructor. The random constructor is the CSPRNG convenience default, not the only way to mint a key.

## Consequences

- The Domain layer stays free of the entropy port. Randomness, like time, enters Domain value objects through a direct read in a named constructor, and through an injected port only at the layers where the random output is asserted.
- Crypto operations with reproducible test vectors stay deterministic under test via the injected generator; key and id minting stay one-line named constructors with no wiring.
- A bounded or audited entropy requirement is met at the operation that consumes the randomness (encryption, signing) or by supplying the key bytes directly — the same place a caller overrides "now" only where elapsed time is under test.
- Do not thread `RandomBytesGeneratorInterface` through Domain value-object construction to "push randomness to the edges": it buys no test value where there is no random-dependent behaviour, and it would make the Domain depend on an outside-capability port. The clock and the entropy source are handled identically — direct read in the constructor, injected port where the output is under test.
