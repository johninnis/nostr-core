# 135. A Blossom authorisation header has its own encode and decode beside the NIP-98 pair

## Status

Accepted

## Context

`NostrAuthHeaderCodec` read and wrote the `Authorization: Nostr <credentials>` header as one wire format for two protocols, NIP-98 and Blossom (ADR-0063), its credentials in canonical padded standard base64 only (ADR-0115). BUD-11 now says a Blossom token "MUST be encoded as Base64 URL-safe without padding (Base64url, as used by JWTs)", while NIP-98 keeps standard base64. `decode` therefore refuses every BUD-11 client, BUD-11's own example among them, and `encode` writes a Blossom token in a form BUD-11 no longer specifies. Shared ADR-0111 decides the wire rule: a Blossom header is written in canonical unpadded base64url and read in that or in canonical padded standard base64, the form every Blossom client wrote before BUD-11; NIP-98 is unchanged.

Three shapes were weighed:

- A parameter on `decode` and `encode` naming the protocol. Every caller knows its protocol when it is written, so the parameter is a runtime choice no caller makes at run time, and a default would quietly pick one protocol for a caller that never said which.
- A separate `BlossomAuthHeaderCodec`. It would repeat the scheme check, the length bound, the JSON reading and the failure vocabulary, or need a third class holding them, for a difference of one step.
- Two more methods on the codec that already names the header family rather than a protocol.

## Decision

- `NostrAuthHeaderCodec::encodeBlossom(Event): ?string` writes the credentials as canonical unpadded base64url, and returns `null` past `MAX_HEADER_LENGTH`, as `encode` does (ADR-0128).
- `NostrAuthHeaderCodec::decodeBlossom(string): Event|AuthHeaderDecodeFailure` reads canonical unpadded base64url, or canonical padded standard base64, and refuses any other spelling as `BadBase64`.
- `decode` and `encode` stay the NIP-98 pair, unchanged.
- Both decoders run one private pipeline (length, scheme token per ADR-0073, base64, JSON, event) given the base64 reader; both encoders share one length check. The canonical readers are `Base64Codec::tryDecodeCanonical` and `Base64Codec::tryDecodeCanonicalUnpaddedUrl`, each of which keeps only text that re-encodes to itself in its form; `Base64Codec::encodeUnpaddedUrl` writes the second.
- The failure vocabulary stays `AuthHeaderDecodeFailure`, already neutral between the two protocols (ADR-0063).

## Consequences

- A Blossom server calls `decodeBlossom`, and a Blossom client calls `encodeBlossom`. A Blossom server calling `decode` refuses every BUD-11 client.
- Do not fold the pairs into one method with a protocol argument, and do not make `decode` read base64url: a NIP-98 header is read only in its canonical padded form.
- `tests/Vectors/blossom-auth-header.json` is byte-identical to @innis/nostr-core's copy; a change to one is a change to both.
- Shared decision: nostr-adrs ADR-0111.
