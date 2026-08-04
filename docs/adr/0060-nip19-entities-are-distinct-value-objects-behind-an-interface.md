# 60. NIP-19 entities are distinct value objects behind an interface, not one class with nullable getters

## Status

Accepted

## Context

NIP-19 defines five bech32 entities this package handles: `npub`, `note`, `nprofile`, `nevent` and
`naddr`. Decoding returned a single `DecodedNip19Entity` carrying a `Nip19EntityType` and six nullable
getters — `getPublicKey`, `getEventId`, `getIdentifier`, `getKind`, `getRelays` — of which each variant
populated two or three. Encoding returned a bare `string` from the codec.

Three problems followed from that one shape.

**Every consumer null-checked fields that could never be set.** Asking an `npub` for its kind, or a
`note` for its relays, was a legal call returning `null`, so no reader could tell an absent optional
field from a field the variant does not have.

**The tag conflated two variants.** `note` and `nevent` both reported `Nip19EntityType::Event`, while
`npub` and `nprofile` were kept apart — the enum classified by "what does this point at" for the event
pair and by "which entity is this" for the pubkey pair, and no reading makes both right. The bag is
what allowed it: with every field nullable on one class, a `note` and an `nevent` had identical shape,
so nothing forced the tag apart. There was no record justifying the collapse.

**Encoding was asymmetric with decoding.** Decode produced an object; encode produced a bare `string`
from the codec. A bech32 artefact crossed a public boundary as a primitive, while NIP-49's equivalent
(`Ncryptsec`) had been a value object all along.

## Decision

Each NIP-19 entity is its own `final readonly` value object — `Npub`, `Note`, `Nprofile`, `Nevent`,
`Naddr` — implementing `Nip19EntityInterface`, which declares `type(): Nip19EntityType` and
`toBech32(): string`. `DecodedNip19Entity` is deleted.

- **An interface, not an abstract base.** The variants share no mechanism a base could own: `type()`
  and `toBech32()` are per-leaf, and there is no self-typed static constructor of the kind that earns
  the message hierarchy its base (ADR-0048). A base here would be a marker, which is the disqualifying
  case — so this is composition, and the leaves are related by a contract rather than by inheritance.
- **The enum is the discriminant, and it gains a `Note` case.** Five entities, five cases. Dispatch is
  `match ($entity->type())`, which the analyser checks for exhaustiveness; `instanceof` reaches a
  leaf's typed fields. This is the analyser-checkable half of ADR-0048's pattern without the half that
  did not apply.
- **A leaf holds only what its variant has.** `Nevent` carries a nullable author and kind because
  NIP-19 makes them optional *for that entity*; `Naddr` carries an `EventCoordinate` because NIP-19
  makes identifier, author and kind mandatory, so a payload missing any of them decodes to `null`
  rather than to a partly-populated object.
- **The bare leaves own nothing.** `npub` and `note` are projections of `PublicKey` and `EventId`,
  which already encode and decode them. `Npub` and `Note` therefore declare no hrp and no bech32
  parsing; they delegate, and exist only so the interface is total over NIP-19 prefixes. The hrp is
  named once, as `PublicKey::BECH32_HRP` / `EventId::BECH32_HRP`.
- **Two entry points, answering different questions.** A leaf's `tryFromBech32` parses a string already
  known to be that entity; `Nip19Codec::decodeComplexEntity` resolves a string of unknown prefix. This
  is the same split ADR-0048 draws between `Message::tryFromJson` and the deserialiser, over a shared
  `tryFromPayload` step so the bech32 decode is not done twice.
- **The codec no longer encodes.** Minting goes through the entity's own named constructor (ADR-0059),
  so there is one way to produce a NIP-19 string.
- **Flattening lives where the wire shape needs it.** `ContentReference` keeps its flat accessors and
  `toArray` shape, deriving them by matching on the leaf it holds. That flattening is that type's
  concern, not something every entity must answer.

## Consequences

- Breaking: `decodeComplexEntity` returns `?Nip19EntityInterface`; `encodeAddressableEvent` is gone;
  `DecodedNip19Entity` is deleted. Minor version bump. The analyser finds every call site.
- `ContentReference::toArray()` now emits `decoded_type => 'note'` for a `note1`, where it previously
  emitted `'event'`. This is a deliberate fix to the conflation, not a compatibility break to be
  papered over — a consumer distinguishing the two could not do so before.
- Reconstructing a `ContentReference` from a stored row goes through the entity's named constructor, so
  a row missing a field its variant requires yields no entity instead of a partly-populated one. A row
  written before this change whose `decoded_type` was `event` for a bare `note` rebuilds as an
  `Nevent`, which is the closest faithful reading of what was stored.
- An "Address with no identifier" is now unrepresentable. A test that constructed one to assert
  "pubkey reference but not addressable" expresses that case with an `Nprofile` instead.
- **`toBech32()` is canonical output, not the string that was decoded.** A leaf built by
  `tryFromPayload` re-encodes from its fields, so decoding an entity written by another implementation
  and re-emitting it can produce a different — semantically identical — bech32 string. Two things
  differ: TLV records are re-emitted in this package's order (`special`, relays, `author`, `kind`),
  and **unrecognised TLV types are dropped**. NIP-19 requires a decoder to ignore unknown TLVs, which
  settles how to *interpret* them; discarding them when re-emitting goes further, and it means a future
  NIP adding a record type has its data destroyed by anything that decodes and re-renders. The
  practical effect is that a pasted `nevent1…` a consumer round-trips may not match the string the user
  supplied, so equality must be compared on the decoded entity, never on the text.
- **Do not "fix" that by storing and returning the input string.** Returning the bytes the entity was
  parsed from would hand back an untrusted string as if the type vouched for it, and would let
  `toBech32()` emit something that no longer matches the entity's own fields. A consumer that must
  preserve the original text verbatim should keep that text alongside the entity — which is what
  `ContentReference` already does with `getRawText()`. If lossless pass-through of unknown TLVs is ever
  required, it belongs in a leaf that retains the undecoded records, decided in its own record.
- **Do not collapse the leaves back onto one class with a type field**, and do not give
  `Nip19EntityInterface` methods only some leaves can answer — that reintroduces exactly the nullable
  surface this record removes.
- **Do not give `Npub` or `Note` their own hrp or bech32 parsing.** They are adapters; `PublicKey` and
  `EventId` own that, and restating it would put one wire tag in two places.
