# 128. An encoder refuses a string its decoder would refuse

## Status

Accepted

## Context

Three of this package's wire formats are constrained when read and were not constrained when written.

- NIP-19: "Bech32-formatted strings SHOULD be limited in size to 5000 characters." `Bech32Codec::decode` refuses a longer string, but `encode` wrote one of any length. An `nevent`, `nprofile` or `naddr` carrying enough relay hints encoded to a string its own `tryFromBech32` refused, and that other implementations, which read NIP-19's bound the same way, refuse too.
- BIP-173 gives the human-readable prefix 1 to 83 US-ASCII characters in the range 33 to 126, and a string must not mix upper and lower case. `Bech32Codec::decode` refuses a string outside those rules, but `encode` wrote any prefix it was given: an empty one, which `decode` cannot find a separator for, one with a space or a byte above ASCII, or an uppercase one beside the lowercase data characters it always writes.
- `Tag` refuses a string that is not UTF-8, but `FileMetadata` accepted one in any field and threw only when `toTags` or `toImetaTag` wrote it as a tag.
- `NostrAuthHeaderCodec::decode` refuses an `Authorization` header longer than 4096 characters, the bound every server built on this package applies to a NIP-98 or Blossom token. `encode` wrote one of any length, so a client could build a token a server reading it would refuse.

An encoder that writes what its reader refuses hands its caller a value that fails later, somewhere else, for a reason the value does not show. The length and the prefix are known when the string is built, so that is when to say no.

## Decision

- `Bech32Codec::encode` returns `null` when the string it would write is longer than 5000 characters, the bound `decode` applies.
- `Bech32Codec::encode` returns `null` for a prefix that is empty, longer than 83 characters, holds a character outside ASCII 33 to 126, or holds an uppercase letter, and `decode` refuses a prefix longer than 83 characters. An uppercase letter is refused because `encode` writes its data characters in lowercase, so an uppercase prefix would make the string mixed case.
- `Nevent::tryFromEventId`, `Nprofile::tryFromPublicKey` and `Naddr::tryFromCoordinate` return `null` when their encoding would be refused, as they do for a TLV record that cannot be framed (ADR-0104), so an entity this package holds always has an encoding every reader accepts.
- `NostrAuthHeaderCodec::encode` returns `null` when the header it would write is longer than 4096 characters, the bound `decode` applies.
- `FileMetadata::tryFrom` returns `null`, and `from` throws, for a field that is not UTF-8. A tag is UTF-8 strings, so `toTags` and `toImetaTag` would otherwise throw on metadata this package accepted.
- The fixed-size encodings (`npub`, `nsec`, `note`, `ncryptsec`) always fit. Each of their encoders throws `SerialisationException` if the codec ever refuses it, which would be a fault of this package.

## Consequences

- A string this package writes is one it reads.
- A caller encoding a variable-length value handles `null`; an `nevent` with too many relay hints is built with fewer.
- Do not give `encode` an unbounded twin for callers that are sure the value fits. The fixed-size encoders are those callers, and they say so where they call it.
- Shared decision: nostr-adrs ADR-0106.
