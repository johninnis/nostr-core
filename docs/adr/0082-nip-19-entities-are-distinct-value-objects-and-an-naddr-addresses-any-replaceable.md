# 82. NIP-19 entities are distinct value objects, and an naddr addresses any replaceable or addressable event as NIP-19 and NIP-01 define

## Status

Accepted

Supersedes ADR-0060. That record decided that the NIP-19 entities are distinct value objects behind an interface, and that decision is carried forward here unchanged. It also stated that NIP-19 makes the `naddr` identifier mandatory, and — through `EventCoordinate` — a coordinate existed only for an addressable kind with a non-empty identifier. That part is revised: what a coordinate is, for every implementation, is the shared decision nostr-adrs ADR-0007, and a deletion by coordinate follows nostr-adrs ADR-0016. This record holds what is particular to this package, and settles the empty-`special` question ADR-0059 left open.

## Context

NIP-19 defines five bech32 entities this package handles: `npub`, `note`, `nprofile`, `nevent` and `naddr`. Decoding once returned a single `DecodedNip19Entity` carrying a `Nip19EntityType` and six nullable getters, of which each variant populated two or three. Every consumer null-checked fields that could never be set; the tag conflated `note` and `nevent` as `Nip19EntityType::Event`; and encoding returned a bare `string` from the codec while decoding returned an object. ADR-0060 replaced that shape; its reasoning stands.

ADR-0060 also read NIP-19 as making the `naddr` identifier mandatory, and `EventCoordinate::tryFrom` admitted only an addressable kind with a non-empty identifier. NIP-19 says of the `special` record "For normal replaceable events use an empty string", and "TLVs that are not recognized or supported should be ignored, rather than causing an error". nostr-adrs ADR-0007 states the rule that follows for every implementation.

## Decision

Each NIP-19 entity is its own `final readonly` value object — `Npub`, `Note`, `Nprofile`, `Nevent`, `Naddr` — implementing `Nip19EntityInterface`, which declares `type(): Nip19EntityType` and `toBech32(): string`. `DecodedNip19Entity` does not exist.

- **An interface, not an abstract base.** The variants share no mechanism a base could own: `type()` and `toBech32()` are per-leaf, and there is no self-typed static constructor of the kind that earns the message hierarchy its base. A base here would be a marker, so the leaves are related by a contract rather than by inheritance.
- **The enum is the discriminant, with a `Note` case.** Five entities, five cases. Dispatch is `match ($entity->type())`, which the analyser checks for exhaustiveness; `instanceof` reaches a leaf's typed fields.
- **A leaf holds only what its variant has.** `Nevent` carries a nullable author and kind because NIP-19 makes them optional for that entity. `Naddr` carries an `EventCoordinate`; its `author` and `kind` records are required, so a payload missing either decodes to `null` rather than to a partly-populated object.
- **`EventCoordinate::tryFrom` is the one constructor** every coordinate and `naddr` path goes through, and it implements nostr-adrs ADR-0007. `EventCoordinate::tryFromEvent` reads the identifier through `TagCollection::getIdentifier()` (ADR-0092). `Naddr` encodes an empty identifier as an empty `special` record and decodes one the same way.
- **Unknown TLVs are ignored on decode**, as NIP-19 requires.
- **A record NIP-19 gives one value is one claim** (`Nip19Tlv::sole`, nostr-adrs ADR-0014 and ADR-0094). Repeated with one value, it is read once; repeated with different values, it states nothing, so a required record (`special`, an `naddr`'s `author` and `kind`) leaves no entity and an `nevent`'s optional `author` or `kind` is absent. An optional record any copy of which is malformed refuses the `nevent`, because a malformed claim is corruption rather than absence. A `kind` outside NIP-01's 0–65535 is malformed, and so is a `special` record that is not UTF-8: `EventCoordinate::tryFrom` refuses a non-UTF-8 identifier, so such an `naddr` decodes to no entity. Relay hints are a list, not a claim, and follow nostr-adrs ADR-0084.
- **The bare leaves own nothing.** `npub` and `note` are projections of `PublicKey` and `EventId`, which already encode and decode them. `Npub` and `Note` declare no hrp and no bech32 parsing; they delegate, and exist only so the interface is total over NIP-19 prefixes.
- **Two entry points, answering different questions.** A leaf's `tryFromBech32` parses a string already known to be that entity; `Nip19Codec::decodeEntity` resolves a string of unknown prefix, over a shared `tryFromPayload` step so the bech32 decode is not done twice.
- **The codec does not encode.** Minting goes through the entity's own named constructor (ADR-0104), so there is one way to produce a NIP-19 string.
- **Flattening lives where the wire shape needs it.** `ContentReference` keeps its flat accessors and `toArray` shape, deriving them by matching on the leaf it holds.

## Consequences

- An "Address with no coordinate" remains unrepresentable; the pubkey-but-not-addressable case is an `Nprofile`.
- **`toBech32()` is canonical output, not the string that was decoded.** A leaf built by `tryFromPayload` re-encodes from its fields: TLV records are re-emitted in this package's order (`special`, relays, `author`, `kind`) and unrecognised TLV types are dropped. Equality must be compared on the decoded entity, never on the text.
- **Do not "fix" that by storing and returning the input string.** A consumer that must preserve the original text keeps it alongside the entity, as `ContentReference` does with `getRawText()`. Lossless pass-through of unknown TLVs, if ever required, is decided in its own record.
- **Do not collapse the leaves back onto one class with a type field**, and do not give `Nip19EntityInterface` methods only some leaves can answer.
- **Do not give `Npub` or `Note` their own hrp or bech32 parsing.**
- `EventCoordinateTest` and `NaddrTest` carry the vectors — kind 10002 with an empty `d` accepted and with a non-empty `d` rejected, kind 30023 with an empty and a non-empty `d` accepted, kind 1 rejected, a missing `author` or `kind` rejected, a non-UTF-8 identifier rejected, an unknown TLV ignored — and fail if the coordinate rule is narrowed again.
- Breaking: ADR-0060's `Nip19Codec::decodeComplexEntity` is `Nip19Codec::decodeEntity`, named for what it does: it decodes an entity of any prefix this package reads.
- Shared decisions: nostr-adrs ADR-0007 (the coordinate), ADR-0016 (a deletion by coordinate, which `RumourFactory::createDeletion` builds), ADR-0084 (relay hints) and ADR-0094 (repeated and malformed records).
