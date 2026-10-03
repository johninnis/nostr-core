# 130. A content reference's entity ends at a boundary after `nostr:` as it does bare

## Status

Accepted

## Context

`ContentReferenceExtractor::extract` finds NIP-27 references in event content, as a NIP-21 `nostr:` URI or as a bare NIP-19 entity. Neither NIP says where an entity in content ends, and a bech32 string has no terminator.

Every bare pattern already ended at a boundary: the entity had to be followed by a character that is not an ASCII letter or digit, by a following `nostr:`, or by the end of the content. The `nostr:` URI pattern did not. Its `npub` and `note` alternatives took exactly the 58 data characters after the prefix and stopped, so `nostr:npub1<entity>xyz` read the `npub` and left `xyz` as text. That shortens a string that was never an entity to a valid reference to something else: a mistyped entity with characters appended, or a longer string that happens to begin with one, became a `p` or `q` tag. `nostr-core-ts` takes the whole run and references nothing, so the same content produced different tags in the two libraries.

## Decision

- Every NIP-19 pattern, bare or after `nostr:`, takes its prefix followed by one shared run: ASCII letters and digits up to the first character that is neither, or up to a following `nostr:`. No prefix names a fixed length, `npub` and `note` included, so a longer run is never cut short to an entity's length.
- `nostr:npub1<entity>xyz` and `nostr:note1<entity>xyz` are no reference; `nostr:npub1<entity>,` references the entity.
- The run is decoded whole, and a run that does not decode is no reference: the extractor yields nothing for it rather than a reference with no decoded entity. Every `ContentReference` therefore carries its decoded entity.

## Consequences

- The same content yields the same references, and so the same `p` and `q` tags, here and in `nostr-core-ts`.
- An `npub` or `note` run of the wrong length is decoded and refused rather than skipped by the pattern. Do not add a fixed-length pattern to save that decode: it reintroduces the shortened reference this record refuses.
- A caller never sees a NIP-19 reference whose decoded entity is absent, as in `nostr-core-ts`, which drops the run.
- Do not give one pattern a run of its own; a pattern that ends differently from the others is the divergence this record removes.
- Shared decision: nostr-adrs ADR-0107.
