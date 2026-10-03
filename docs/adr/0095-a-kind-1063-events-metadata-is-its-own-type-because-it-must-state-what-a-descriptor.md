# 95. A kind 1063 event's metadata is its own type, because it must state what a descriptor may leave out

## Status

Accepted

## Context

nostr-adrs ADR-0072 decides for every implementation that file metadata which may be a kind 1063 event is its own type stating a MIME type, an `x` and an `ox`; that a BUD-08 descriptor and a NIP-92 `imeta` tag keep what their own specifications allow; and how `url`, `m` and empty values are read and built. nostr-adrs ADR-0096 decides how `size`, like every decimal tag value, is read: only as canonical digits with no leading zero.

`FileMetadata` served all three readings here and required only `url`, so a kind 1063 event missing its hashes parsed, and `RumourFactory::createFileMetadata` built one. A nullable-field check in the factory, or a `requireComplete` flag on the parser, would leave a `FileMetadata` in hand that might or might not be publishable, and every caller would have to remember to ask.

## Decision

This package implements nostr-adrs ADR-0072 with these types:

- `FileEventMetadata` wraps a `FileMetadata` that states a MIME type and an `x` and `ox` that are SHA-256 lowercase hex. `tryFrom` returns `null` otherwise and `from` throws `InvalidArgumentException` for trusted input.
- `FileEventMetadata::tryFromEvent` is the parser of a kind 1063 event: it returns `null` for another kind or for metadata missing a field NIP-94 requires.
- `RumourFactory::createFileMetadata` takes a `FileEventMetadata`, so an incomplete event cannot be built.
- `FileMetadata::tryFromTagCollection` is the lenient reader for BUD-08; `tryFromImetaTag` and `toImetaTag` hold NIP-92's rule. `size` is read through `DecimalIntegerParser::tryParse` (nostr-adrs ADR-0096) and `m` through `MimeTypeParser::tryParse`. Each field is read as one claim (ADR-0092).

## Consequences

- Breaking: `createFileMetadata` takes `FileEventMetadata` instead of `FileMetadata`.
- Do not fold the two types into one with a completeness flag, or tighten `FileMetadata` to NIP-94's event rule.
- Shared decision: nostr-adrs ADR-0072.
- Shared decision: nostr-adrs ADR-0096, for the reading of `size`.
