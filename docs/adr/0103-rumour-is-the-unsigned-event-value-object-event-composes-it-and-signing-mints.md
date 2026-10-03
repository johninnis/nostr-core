# 103. `Rumour` is the unsigned-event value object; `Event` composes it, and signing mints the entity

## Status

Accepted

Supersedes ADR-0045. The split — `Rumour` an immutable value holding the five core fields, `Event` the entity composing a rumour with a non-null id and signature, signing as the line between them — is carried forward unchanged. What is revised is the surface around it: ADR-0045 said `GiftWrapper` returns a `Rumour` from wrapping, that `Rumour::tryFromArray` returns `?self`, and that `Event::build` hands it an array; wrapping returns the gift-wrap `Event`, the parser returns `self|RumourParseFailure`, and `Event::build` does not exist. This record holds the complete current decision.

## Context

An unsigned event is a first-class protocol concept: the payload sealed inside a gift wrap, and the shape every event has before it is signed. A single `Event` type once covered both, carrying a nullable id and signature, an `isSigned()` flag, a `sign()` method, a `withTags()` that silently produced an unsigned copy, lenient parsing that accepted a missing id or signature, and runtime "must not be signed" guards — each existing only because one type modelled two things.

Splitting them raises two questions. Is the unsigned form an entity? It has a content-hash id, but an id is a value; an unsigned event is never stored, never referenced by id, and has no lifecycle — rumour, seal and gift wrap are three objects, not one changing state — so it is a value. And how do the two share the identical reads and predicates without duplicating them or inheriting for reuse?

## Decision

- **`Rumour`** is an immutable value object in `ValueObject/Protocol/` holding pubkey, `created_at`, kind, tags and content, the pure predicates (`isReply`, `isExpiredAt`, and the rest), `withTags()` and `withCreatedAt()`, and a `getId()` computing the content hash. It is built by `Rumour::draft` (ADR-0081).
- **`Event`** is the entity: a `Rumour` plus a non-null `EventId` and `Signature`, delegating the reads and predicates to its rumour and adding the id, the signature and `verify()`. `Rumour::sign()` mints an `Event`. Value in, entity out.
- **`Event`'s parsers require an id and a signature.** `Event::tryFromArray(mixed)` and `tryFromJson(string)` return `null` for an unsigned array; such an array is a `Rumour`.
- **A rumour states its id, and `Rumour::tryFromArray(array): self|RumourParseFailure` is its untrusted-input parser.** NIP-17: "Fields `id` and `created_at` are required." It returns `Malformed` for fields that do not form an unsigned event and for an array with no `id`, and `IdMismatch` when the array states an `id` that is not the one its fields hash to. A missing `id` is a missing required field like any other, so it is malformed, not a mismatch, and it is never computed in its place: a rumour that does not state its id gives a receiver nothing to check it against. It takes an already-decoded `array`: a rumour is never a bare wire element, only a seal's decrypted plaintext, which `GiftWrapper::unwrap` decodes first so it can refuse a signed payload before parsing, and reports a rumour with no `id` as `RumourMalformed`. There is no `mixed`-narrowing variant and no `tryFromJson`.
- **`Rumour::tryFromFields(array): ?self` reads the five fields an id is hashed from, and nothing else.** It is the one reader of those fields: `tryFromArray` reads through it before checking the `id`, and `Event::tryFromArray` reads an event's rumour through it, since an event's stated id is kept as signed and checked by verification, not at parse (ADR-0046). It ignores an `id` key, so it is not a rumour parser that skips the id check; a caller holding a NIP-59 rumour calls `tryFromArray`.
- **Gift wrapping takes a rumour and returns events.** `GiftWrapServiceInterface::wrapForRecipient(Rumour, PrivateKey, PublicKey): Event` returns the kind 1059 gift wrap, `wrapForChatRoom` an `EventCollection` of them (ADR-0096), and `unwrap(Event, PrivateKey): Rumour|GiftWrapUnwrapFailure` the rumour or the step that failed (ADR-0089).
- **One builder writes the five fields.** `Rumour::toArrayWithoutId()` writes pubkey, `created_at`, kind, tags and content; `getId()` hashes them, `Rumour::toArray()` puts the computed id in front of them, and `Event::toArray()` puts the stored id in front and the signature after. An event never hashes itself to serialise: its id is the one it was signed or parsed with.
- `Rumour`'s serialised form omits `sig` entirely, matching the NIP-59 rumour shape.

## Consequences

- `Event` has no optionality: no `isSigned()`, no `sign()`, no `calculateId()`, no `withTags()`, and `verify()` needs no null guard.
- `Event` forwards reads to its `Rumour`. This is composition, not a thin wrapper: `Event` adds identity. Do not flatten it back into one dual-purpose type.
- `Rumour::sign()` returns an `Event` while `Event` holds a `Rumour`; both are domain types, and the mutual reference mirrors the value-to-artifact relationship.
- A caller of `Rumour::tryFromArray` handles a failure the analyser makes it see, and tells a malformed rumour from one whose stated id lies.
- A rumour without an `id` is refused, not given the id its fields hash to; do not compute a missing id to be lenient, and do not route a gift wrap's rumour through `tryFromFields`.
- Shared decision: nostr-adrs ADR-0073.
