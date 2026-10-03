# 109. `GiftWrapper` takes a conversation cipher, a signer and its envelope factory

## Status

Accepted

Supersedes ADR-0035. Its placement — `GiftWrapper` is a composed cryptographic capability kept with the crypto implementations, and `GiftWrapEnvelopeFactoryInterface` is a local injection seam beside its one consumer — is carried forward unchanged. What is revised is the constructor: ADR-0035 accepted four collaborators, and this record holds the complete current decision.

## Context

ADR-0035 recorded `GiftWrapper`'s four collaborators — NIP-44 encryption, a signature service, an ECDH service and the envelope factory — as distinct capabilities that could not be split, and fenced the constructor. A class that needs four collaborators usually holds more than one job, and a fence that exempts the constructor keeps the second job unnamed instead of giving it a home.

Two of the four were never consumed apart. Every use of the ECDH service derived a NIP-44 conversation key between a private key and a peer's public key, and every use of the NIP-44 encryption ran under that key and then wiped it: once to seal, once to wrap, and once for each layer opened. That pairing is one responsibility, encrypting to and decrypting from a peer, and the gift wrap only orchestrates it.

## Decision

- `ConversationCipher` (`Domain\Service`) takes NIP-44 encryption and ECDH, and offers `encrypt(plaintext, ownKey, peer)` and `decrypt(ciphertext, ownKey, peer)`. Each derives the conversation key, runs the cipher, and zeroes the key. A failure stays the primitive's thrown fault.
- `ConversationCipher` implements `ConversationCipherInterface`, beside it in `Domain\Service`, and `GiftWrapper` takes that interface, a signature service and the envelope factory, so a test or a host substitutes the cipher without touching the gift wrap (ADR-0098). It converts a thrown decryption fault on the peer's ciphertext to a returned `GiftWrapUnwrapFailure` where the ciphertext enters, as before (ADR-0089).
- `GiftWrapper::create(encryption, signatureService, ecdh)` builds the cipher and the random envelope factory, so a host composing the default stack is unchanged.
- `GiftWrapper`, `GiftWrapEnvelope`, `GiftWrapEnvelopeFactoryInterface` and `RandomGiftWrapEnvelopeFactory` stay together in the crypto concern. (Carried forward.)

## Consequences

- A host that constructs `GiftWrapper` directly passes `new ConversationCipher($nip44, $ecdh)` where it passed the two.
- The constructor carries no fence, and a fifth capability is a sign to split again, not to fence.
- The conversation key's derive-use-wipe sequence exists once.
