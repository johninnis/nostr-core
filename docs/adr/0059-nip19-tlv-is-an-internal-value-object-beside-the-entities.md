# 59. The NIP-19 TLV framing is an internal value object beside the entities, and enforces the uint8 length at construction

## Status

Accepted

## Context

NIP-19 specifies the framing exactly:

> `T` and `L` being 1 byte each (`uint8`, i.e. a number in the range of 0-255), and `V` being a
> sequence of bytes of the size indicated by `L`.

A value over 255 bytes has no representation. `pack('C', …)` does not reject an out-of-range integer —
it wraps modulo 256, so a 290-byte `d` tag declared a length of 34 and a decoder resumed parsing
*inside* the value. That is exploitable: a `d` tag is ordinary user content and nothing bounds it, so
an identifier can be crafted whose tail forms a valid `author` record, and the first `author`
encountered wins. A demonstration produced an `naddr` for a coordinate owned by `abab…ab` that decoded
to author `eeee…ee`. The spec compounds it by requiring unrecognised TLVs to be ignored rather than
error, so unknown-type padding is skipped by every conforming decoder. Only `special` is reachable:
`author` and `kind` are fixed-width, and `relay` is bounded by `RelayUrl`'s 200-character cap.

The obvious home for the rule is a stateless `Nip19TlvCodec` in `Domain/Service`, beside `Bech32Codec`
and `HexCodec`. Two things argue against it. Six public static methods in the shared service folder
read as a capability the package offers, when the only callers are three value objects in one
namespace — and every method there becomes something the package is committed to. And filing by
structural kind puts a stateless codec in `Service/`, so it could not simply be moved next to its
callers without putting a service in a value-object folder. That constraint is diagnostic: the type
holds a record list *and* its encoded bytes, which is data, so modelling it as a stateless codec is
what creates the tension.

## Decision

The framing is `Nip19Tlv`, a `final readonly` value object in `Domain/ValueObject/Nip19/`, marked
`@internal`, holding both the encoded bytes and the parsed records.

- **Both uint8 fields are enforced in `Nip19Tlv::tryFromRecords`**, the single point that writes them.
  It returns `null` if a record's value exceeds 255 bytes *or* its type falls outside 0–255. The spec
  makes `T` and `L` alike, and so does the guard: checking only the length would leave the adjacent
  argument of the same `pack('CC', …)` call able to wrap, and a wrapped type is worse than a truncated
  one — the record would be indexed in memory under the unwrapped value while the bytes carried the
  wrapped one, so the object would disagree with its own encoding. Every record goes through this, so a
  record type added later is covered without anyone remembering to.
- **Representability is a construction-time question.** `Nprofile::tryFromPublicKey`,
  `Nevent::tryFromEventId` and `Naddr::tryFromCoordinate` build the TLV, return `null` when it cannot
  be represented, and store the encoded bech32 string.
- **`toBech32(): string` is total.** The constructor is private and the encoded form is kept at
  construction, so holding an entity is proof its encoding exists. No nullable string appears on the
  encode path.
- **The codec does not encode.** `Nip19CodecInterface` has no encode method; minting goes through the
  entity's own named constructor, so there is one way to produce a NIP-19 string.
- **`Nip19Tlv` is internal.** It is `@internal` and lives beside its only callers. Being a value object
  rather than a stateless codec is what makes `ValueObject/Nip19/` the correct home under the
  file-by-structural-kind rule, rather than a folder chosen for encapsulation and justified afterwards.
- **The guard on records that cannot overflow today is kept.** Only `special` is reachable; the `relay`
  guard is defence in depth whose load-bearing bound lives in `RelayUrl`, and relaxing that cap makes
  this one live.

## Consequences

- An `naddr` this package emits is either decodable or absent. It cannot produce a string that passes
  bech32 validation and resolves — here or in any conforming implementation — to a different author
  than the one encoded.
- `Nip19Tlv` is not part of the supported surface: consumers must not depend on it, and an analyser
  configured to honour `@internal` will say so.
- Callers null-check a construction, once, rather than an encode call, and afterwards hold a value
  whose encoding is guaranteed.
- **Do not reintroduce a bare `pack('CC', …)`.** The wrap is silent and the result passes bech32
  checksum validation, so nothing downstream catches it. `NaddrTest` encodes the crafted 290-byte
  identifier from the Context and fails if construction returns anything but `null`.
- **Do not promote `Nip19Tlv` to public API or move it back to `Domain/Service`.** Its shape is driven
  by what the entities in its namespace need; making it public would freeze framing details that exist
  to serve them.
- Whether an *empty* `special` should be accepted — the spec permits it for normal replaceable events,
  while `EventCoordinate::tryFrom` rejects an empty identifier — remains unsettled and is not relied on
  here.
