# 144. A Blossom authorisation header has its own encode and decode beside the NIP-98 pair, and its own length bound

## Status

Accepted

Supersedes ADR-0135. Carried forward unchanged: `NostrAuthHeaderCodec::encodeBlossom` and `decodeBlossom` are a second pair beside the NIP-98 `encode` and `decode`; a Blossom header is written in canonical unpadded base64url and read in that or in canonical padded standard base64, any other spelling refused as `BadBase64`; both decoders run one private pipeline and both encoders share one length check; the failure vocabulary stays `AuthHeaderDecodeFailure`; `decode` never reads base64url. Revised: a Blossom header has its own length bound, `MAX_BLOSSOM_HEADER_LENGTH`, 8000 characters, applied on writing and on reading (nostr-adrs ADR-0106), where ADR-0135 bounded it at NIP-98's `MAX_HEADER_LENGTH`, 4096 characters. This record holds the complete current decision.

## Context

`NostrAuthHeaderCodec` read and wrote the `Authorization: Nostr <credentials>` header as one wire format for two protocols, NIP-98 and Blossom (ADR-0063), its credentials in canonical padded standard base64 only (ADR-0115). BUD-11 now says a Blossom token "MUST be encoded as Base64 URL-safe without padding (Base64url, as used by JWTs)", while NIP-98 keeps standard base64. `decode` therefore refuses every BUD-11 client, and `encode` writes a Blossom token in a form BUD-11 no longer specifies. nostr-adrs ADR-0111 decides the wire rule: a Blossom header is written in canonical unpadded base64url and read in that or in canonical padded standard base64, the form every Blossom client wrote before BUD-11; NIP-98 is unchanged. BUD-11's own example decodes in its form, but its payload carries `//` comments, so it is not JSON and is refused as such.

A Blossom token grows with the blobs it names: each `x` tag adds about 97 characters to the encoded header, so under NIP-98's 4096 characters a token naming more than about 36 blobs did not fit, and a client deleting or authorising a batch had to split it. Common HTTP servers refuse a single header line past about 8 KiB. nostr-adrs ADR-0106 bounds a Blossom header at 8000 characters, within that line, which fits a token naming about 80 blobs; NIP-98's event is fixed in size by one URL, and its bound stays 4096. ADR-0135 bounded both headers at 4096 characters.

Three shapes were weighed for the second pair:

- A parameter on `decode` and `encode` naming the protocol. Every caller knows its protocol when it is written, so the parameter is a runtime choice no caller makes at run time, and a default would quietly pick one protocol for a caller that never said which.
- A separate `BlossomAuthHeaderCodec`. It would repeat the scheme check, the length bound, the JSON reading and the failure vocabulary, or need a third class holding them, for a difference of one step and one bound.
- Two more methods on the codec that already names the header family rather than a protocol.

## Decision

- `NostrAuthHeaderCodec::encodeBlossom(Event): ?string` writes the credentials as canonical unpadded base64url, and returns `null` past `MAX_BLOSSOM_HEADER_LENGTH`, 8000 characters, the bound `decodeBlossom` applies (ADR-0128).
- `NostrAuthHeaderCodec::decodeBlossom(string): Event|AuthHeaderDecodeFailure` refuses a header longer than `MAX_BLOSSOM_HEADER_LENGTH` as `TooLong`, reads canonical unpadded base64url or canonical padded standard base64, and refuses any other spelling as `BadBase64`.
- `decode` and `encode` stay the NIP-98 pair, bounded by `MAX_HEADER_LENGTH`, 4096 characters.
- Both decoders run one private pipeline (length, scheme token per ADR-0073, base64, JSON, event) given the base64 reader and the bound; both encoders share one length check given the bound. The canonical readers are `Base64Codec::tryDecodeCanonical` and `Base64Codec::tryDecodeCanonicalUnpaddedUrl`, each of which keeps only text that re-encodes to itself in its form; `Base64Codec::encodeUnpaddedUrl` writes the second.
- The failure vocabulary stays `AuthHeaderDecodeFailure`, already neutral between the two protocols (ADR-0063).

## Consequences

- A Blossom server calls `decodeBlossom`, and a Blossom client calls `encodeBlossom`. A Blossom server calling `decode` refuses every BUD-11 client, and every token longer than 4096 characters.
- A server in front of this package must accept an 8 KiB header line for Blossom batches to reach it. A batch of more than about 80 blobs is still written as several tokens.
- Do not fold the pairs into one method with a protocol argument, and do not make `decode` read base64url: a NIP-98 header is read only in its canonical padded form.
- Do not give the two schemes one bound: a NIP-98 event's size is fixed, while a Blossom token grows with the blobs it names.
- `tests/Vectors/blossom-auth-header.json` is byte-identical to @innis/nostr-core's copy; a change to one is a change to both.
- Shared decisions: nostr-adrs ADR-0106 and ADR-0111.
