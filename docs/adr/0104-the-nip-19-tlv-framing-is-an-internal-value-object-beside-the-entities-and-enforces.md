# 104. The NIP-19 TLV framing is an internal value object beside the entities, and enforces the uint8 fields at construction

## Status

Accepted

Supersedes ADR-0059. The `Nip19Tlv` value object, its uint8 guard on type and length, construction-time representability and the total `toBech32()` are carried forward unchanged. What is revised: ADR-0059 said `Nip19CodecInterface` has no encode method, and that interface no longer exists (ADR-0098); and the question it left open — whether an empty `special` is accepted — is settled by ADR-0082. This record holds the complete current decision.

## Context

NIP-19 specifies the framing exactly: "`T` and `L` being 1 byte each (`uint8`, i.e. a number in the range of 0-255), and `V` being a sequence of bytes of the size indicated by `L`."

A value over 255 bytes has no representation. `pack('C', …)` does not reject an out-of-range integer — it wraps modulo 256 — so a 290-byte `d` tag declared a length of 34 and a decoder resumed parsing inside the value. That is exploitable: a `d` tag is ordinary user content, so an identifier can be crafted whose tail forms a valid `author` record, and the first `author` encountered wins. A demonstration produced an `naddr` for a coordinate owned by `abab…ab` that decoded to author `eeee…ee`. NIP-19's instruction to ignore unrecognised TLVs means unknown-type padding is skipped by every conforming decoder. Only `special` is reachable: `author` and `kind` are fixed-width, and `relay` is bounded by `RelayUrl`'s 200-character cap.

A stateless `Nip19TlvCodec` in `Domain/Service` is the obvious home. But its only callers are three value objects in one namespace, every public method in the service folder reads as a capability the package offers, and the type holds a record list and its encoded bytes — data, not a stateless codec.

## Decision

The framing is `Nip19Tlv`, a `final readonly` value object in `Domain/ValueObject/Nip19/`, marked `@internal`, holding both the encoded bytes and the parsed records.

- **Both uint8 fields are enforced in `Nip19Tlv::tryFromRecords`**, the single point that writes them. It returns `null` if a record's value exceeds 255 bytes or its type falls outside 0–255; a wrapped type is as bad as a wrapped length, since the record would be indexed under one value while the bytes carried another. A record type added later is covered without anyone remembering to.
- **Representability is a construction-time question.** `Nprofile::tryFromPublicKey`, `Nevent::tryFromEventId` and `Naddr::tryFromCoordinate` build the TLV, return `null` when it cannot be represented, and store the encoded bech32 string.
- **`toBech32(): string` is total.** Holding an entity is proof its encoding exists.
- **Nothing else encodes.** `Nip19Codec` decodes only; minting goes through the entity's own named constructor, so there is one way to produce a NIP-19 string.
- **An empty `special` is the empty identifier**, for a replaceable kind or an addressable one (ADR-0082).
- **The guard on records that cannot overflow today is kept**: the `relay` guard is defence in depth whose load-bearing bound lives in `RelayUrl`.

## Consequences

- An `naddr` this package emits is either decodable or absent; it cannot resolve, here or in any conforming implementation, to a different author than the one encoded.
- `Nip19Tlv` is not part of the supported surface, and an analyser honouring `@internal` says so.
- Do not reintroduce a bare `pack('CC', …)`: the wrap is silent and passes bech32 checksum validation. `NaddrTest` encodes the crafted 290-byte identifier and fails if construction returns anything but `null`.
- Do not promote `Nip19Tlv` to public API or move it to `Domain/Service`.
