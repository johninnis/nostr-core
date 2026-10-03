# 89. Anticipated outcomes are returned, faults are thrown, and peer ciphertext that cannot be opened is an outcome

## Status

Accepted

Supersedes ADR-0003. The split between returned outcomes and thrown faults, the parser rule and the validation-command exception are carried forward unchanged. What is revised: a multi-step cryptographic operation may throw its mid-operation failure only when the input is not ciphertext received from a peer. An operation that opens a peer's ciphertext converts the primitive's throw into a returned failure. This record holds the complete current decision.

## Context

Failure splits into two kinds, and they must be modelled differently. PHP has no checked exceptions, so a `throw` is invisible to PHPStan: a caller can silently forget to handle it. A nullable or `*Failure` return, by contrast, makes "you didn't handle the failure" a level-9 analyser error.

ADR-0003 let a multi-step cryptographic operation throw its mid-operation failure, and `GiftWrapper::unwrap` threw `GiftWrapException` for a wrap it could not open. A gift wrap arrives from anyone: a relay hands the recipient every kind-1059 event tagged to them, and a wrap addressed elsewhere, forged, or tampered with is an ordinary thing to receive. A caller reading its inbox that forgot the catch reported a stranger's garbage as a crash. `nostr-core-ts` returns a failure from `unwrapGiftWrap` for the same inputs.

The bare primitives are different. `Nip44Cipher::decrypt` and `Nip04Cipher::decrypt` are handed a payload and a key by code that has already decided to trust the pairing; a failure there is a fault of that operation, and ADR-0118 keeps NIP-04's one indistinguishable message.

## Decision

- **Anticipated domain outcomes**, a well-formed operation whose answer is "no" (unauthorised, too large, not found, malformed wire input, policy rejection, rate limited), are **returned** as a typed value: `?T` for a single failure mode, or a sealed family of `*Failure` value objects (or a backed enum when the failure carries no data) for several. They are never thrown.
- **A parser of untrusted input** (`Event::tryFromArray` / `tryFromJson`, `Filter::tryFromArray`, `ClientMessage::tryFromJson` / `RelayMessage::tryFromJson`, `Nip98Validator`) puts its failure in the return type and must not throw.
- **Faults**, broken invariants, programmer errors, infrastructure failures, and mid-operation crypto or serialisation errors, are **thrown**. `InvalidArgumentException` (native) covers argument validation of trusted internal values.
- **Two cases legitimately throw** rather than return: a validation command returning `void` (contract is "succeed or throw", paired with a boolean query when a non-throwing check is useful), and a multi-step crypto or serialisation operation whose mid-operation failure is a fault of that operation.
- **Ciphertext received from a peer that cannot be opened is an anticipated outcome.** The NIP-04 and NIP-44 primitives still throw. The operation that hands peer ciphertext to a primitive catches that primitive's own exception (`EncryptionException`, and `EcdhException` for the peer's key) at the point the ciphertext enters, and returns its failure. `GiftWrapper::unwrap` returns `Rumour|GiftWrapUnwrapFailure`, naming the layer and step that failed.

## Consequences

- PHPStan level 9 forces every caller to handle the failure branch of a returned outcome, including a gift wrap that is not for it.
- Exceptions in this library signify genuine faults only; a `catch` is never load-bearing control flow for an anticipated "no". The one kind of catch that converts is at the edge where peer ciphertext enters, and it catches only the primitive's exception, so a genuine fault still propagates.
- `GiftWrapException` remains for a fault while wrapping: a rumour too large for a gift wrap to hold (ADR-0117). An event cannot fail to serialise, because its content and its tag values are UTF-8 by construction.
- Do not "simplify" a `?T` / `*Failure` return into a throw, and do not widen an edge catch to `Throwable`.
- Shared decisions: nostr-adrs ADR-0001 and ADR-0073.
