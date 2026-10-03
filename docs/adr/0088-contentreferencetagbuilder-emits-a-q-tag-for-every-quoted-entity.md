# 88. `ContentReferenceTagBuilder` emits a `q` tag for every quoted entity

## Status

Accepted

Supersedes ADR-0011. The refusal of an accompanying `e` mention is carried forward unchanged. What is revised: a quoted address was an `a` tag and is now a `q` tag, and every `q` tag now carries the entity's relay hint and names the author only for a regular event. The tag's shape is the shared decision, nostr-adrs ADR-0013; this record holds what is particular to this package.

## Context

NIP-18: "Mentions to NIP-21 entities like `nevent`, `note` and `naddr` on any event must be converted into `q` tags." nostr-adrs ADR-0013 decides for every implementation that a quote is one `q` tag with no `e` mention or `a` tag beside it, which slots it fills, and when it names the author.

ADR-0011 covered only the quoted event; this package wrote an `naddr` mention as `["a", <coordinate>]`, a tag NIP-22 reads as a comment's parent scope.

## Decision

`ContentReferenceTagBuilder` implements nostr-adrs ADR-0013. It writes each quoted entity as a `q` tag whose second element is the event id of a `note` or `nevent` or the coordinate of an `naddr`, whose third is the entity's first relay hint, and whose fourth is the author under the shared rule. `e` tags that are NIP-10 thread references come from `RumourFactory::createReply`, a different code path.

When the content names the same target more than once, the one `q` tag is written where it is last named (nostr-adrs ADR-0076) and carries, slot by slot, the first non-empty relay and author any of its mentions gave, so a bare `note` after an `nevent` keeps the `nevent`'s hints and the tag does not depend on the order of the mentions.

## Consequences

- Quoted events and addresses resolve and count through `q`, and neither can be read as a thread reference or a comment scope.
- Do not add an `e` mention or an `a` tag for a quote.
- Do not let a later mention of the same target replace the relay or author an earlier one gave.
- Shared decisions: nostr-adrs ADR-0013 and ADR-0076.
