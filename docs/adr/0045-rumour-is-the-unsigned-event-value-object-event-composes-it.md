# 45. `Rumour` is the unsigned-event value object; `Event` composes it and signing mints the entity

## Status

Accepted

## Context

An unsigned event — a regular event of any kind with its signature removed — is a first-class concept in
the protocol: it is the payload sealed inside a gift wrap, and it is the shape every event has before it
is signed. Until now a single `Event` type did double duty for both the signed artifact and every
unsigned draft. To cover the unsigned case it carried a nullable id and a nullable signature, an
`isSigned()` flag, a `sign()` method, an `withTags()` that silently produced an unsigned copy, lenient
wire parsing that accepted a missing id/signature, and — wherever an unsigned payload was handled —
runtime "must not be signed" guards. Every one of those exists only because one type modelled two
things.

Splitting the unsigned form into its own type raises two questions:

1. **Is the unsigned form an entity or a value object?** It has a content-hash id, and the signed event
   is an entity, so it is tempting to make the unsigned form an entity too. But a content-hash id decides
   nothing — an id is a value, and content-addressing is orthogonal to the entity/value distinction. The
   deciding test is what the concept *is*: an unsigned event is never stored, never referenced by id, and
   has no lifecycle or state transitions. The rumour → seal → gift-wrap sequence is three distinct
   objects, not one object changing state. Defined entirely by its attributes, it is a value.

2. **How do the two types relate without duplicating behaviour?** The core reads (pubkey, created_at,
   kind, tags, content) and the pure kind/tag predicates (`isReply`, `isExpired`, and the rest) are
   identical for signed and unsigned events. Duplicating them across two types would be a DRY violation;
   pulling them onto a shared base class would be inheritance used for code reuse.

## Decision

The unsigned event is `Rumour`, an immutable value object holding the five core fields, the pure
predicates, wire parsing, `withTags()`, and a `getId()` that computes the content hash. It lives in
`ValueObject/Protocol/` beside the other protocol value types.

`Event` is the entity. It is composed of a `Rumour` plus a non-null `EventId` and a non-null
`Signature`, delegates the core reads and predicates to its rumour, and adds what makes it an artifact:
a guaranteed id, a signature, and `verify()`. `Rumour::sign()` mints an `Event`.

The line between the two is signing: **an unsigned event is data (a value); signing mints the
identity-bearing, storable, referenceable artifact (the entity).** Value in, entity out.

`Event`'s delegation forwards a handful of reads to its inner `Rumour`. This is composition, not a
banned thin wrapper: `Event` adds the id, the signature, verification, and the whole notion of identity,
so it is not a pass-through that merely reproduces the value it wraps.

## Consequences

- `Event` sheds all optionality: non-null id and signature, no `isSigned()`, no `sign()`, no
  `calculateId()`, no `withTags()`. `verify()` needs no null guard, and `tryFromArray`/`tryFromJson`
  require an id and a non-empty signature — an unsigned array no longer parses to an `Event` (parse it as
  a `Rumour`).
- The unsigned-event concept is now one type. The factory that builds event drafts returns `Rumour`; a
  caller signs to obtain an `Event`. `GiftWrapper` takes a `Rumour` and returns a `Rumour`, and the rule
  that a seal's decrypted payload must be unsigned is enforced at the decrypt boundary rather than by a
  flag on the event.
- `Event` forwards reads to its `Rumour`. Do not "flatten" this back into a single dual-purpose event to
  remove the forwarding — that reintroduces the nullable id/signature and the `isSigned()` branching this
  split exists to delete.
- `Rumour::sign()` returns an `Event` while `Event` holds a `Rumour`, so the two reference each other.
  Both are domain types and this mirrors the value↔artifact relationship; it is not a layering
  violation.
- `Rumour`'s only untrusted-input parser is `tryFromArray(array): ?self`; it deliberately takes an
  already-decoded `array` and has no `mixed`-accepting wire-narrowing variant and no `tryFromJson(string)`.
  A rumour is never a bare wire element — it exists only as a seal's decrypted plaintext, which
  `GiftWrapper` decodes to an `array` (so it can reject a signed payload before parsing) before calling
  `tryFromArray`, and `Event::build` likewise hands it an already-decoded `array`. With no `mixed`-
  narrowing boundary and no in-the-clear JSON rumour anywhere in the flow, those extra parser variants
  would be unused surface. It mirrors where a `mixed`-accepting `tryFromArray` and a `tryFromJson` appear
  elsewhere — on the types that are themselves wire fields (ADR-0051) — so their absence here is intended,
  not a gap. `Rumour`'s serialised form omits the `sig` field entirely (not an empty string), matching the
  NIP-59 rumour shape.
