# 105. `RumourFactory` is built for one author, and keeps only the factories that build tags

## Status

Accepted

The rule that `Rumour::draft` is the one way to build an unsigned event (ADR-0081) and the NIP-09 rule `createDeletion` enforces (ADR-0106) are recorded apart from this one, because each can be revised without the others. This record holds the complete current decision for the factory's shape.

## Context

Once `Rumour::draft` is the one way to build an unsigned event (ADR-0081), a factory earns its place only by doing work beyond naming a kind: building tags. At v0.8.3 every factory was a static method taking the author first. Every draft has an author, so the argument was repeated on every call, and once a reply, a reaction or a private message builds its own tags it pushes those methods past three parameters; a fence or a parameter object would keep the count down without removing the argument that causes it.

Four factories at v0.8.3 (`createCustomKind`, `createFileMetadata`, `createHttpAuth` and `createLongformContent`) also took an optional `created_at` and the rest did not, so whether a caller could fix the instant depended on which kind it built; giving it to the others would have pushed `createReply` and `createReaction` past three parameters.

## Decision

`RumourFactory` keeps only the factories that build tags: `createTextNote`, `createRepost`, `createReply`, `createReaction`, `createAuth`, `createHttpAuth`, `createFileMetadata`, `createLongformContent`, `createDeletion`, `createPrivateMessage` and `createPrivateReaction`. Content is an `EventContent` everywhere it appears.

- **One author per factory.** `new RumourFactory(PublicKey $author)`; every draft it makes is that author's, and no method takes the author.
- **At most three parameters, and no `created_at`.** `createFileMetadata(metadata, ?caption)`, `createLongformContent(content, metadata)`, `createTextNote(content)`, `createReply(parent, content, ?hint)`, `createRepost(target, relay)`, `createReaction(target, ?reaction, ?relay)`, `createAuth(relayChallenge)`, `createHttpAuth(request)`, `createDeletion(target)`, `createPrivateMessage(receivers, content, ?replyTo)` and `createPrivateReaction(receivers, message, ?reaction)` (ADR-0096). Every draft is stamped with the current instant; a caller that needs a fixed one restamps the draft with `Rumour::withCreatedAt()`, as @innis/nostr-core callers spread a fixed `created_at` over a built event.
- **A builder takes the event it answers** (nostr-adrs ADR-0075). `createReply` takes no thread root: the root is read from the parent, as @innis/nostr-core `buildReply(content, parent, hint?)` reads it, so both write the same tags for the same parent and hint, and a reply's content is tagged as a note's is, after its thread tags (nostr-adrs ADR-0076).
- **A reaction names where its target can be found.** NIP-25: "The `e` tag SHOULD include a relay hint". `createReaction`'s optional relay is written on the `e` tag, on the `p` tag and on the `a` tag of an addressable target, as @innis/nostr-core `buildReaction(target, reaction?, relay?)` writes it; without one the `e` and `a` relay slots are empty and the `p` tag has none. Only an addressable target gets an `a` tag (nostr-adrs ADR-0077).

## Consequences

- Breaking: the factory methods are instance methods of a `RumourFactory` built for the author, where at v0.8.3 they were static methods that took the author first. `ReplyTagBuilder::buildTags(replyTo, ?root)` is removed in favour of `createReply`, so a reply can no longer be given a root other than the one its parent names.
- Breaking: `createHttpAuth`, `createFileMetadata` and `createLongformContent` no longer take a `created_at`; callers restamp with `withCreatedAt()`.
- Do not add an author or a `created_at` parameter back to a factory method, or a parameter object to bring a method under four parameters; build a factory for the other author, or restamp the draft.
- Shared decisions: nostr-adrs ADR-0075, ADR-0076 and ADR-0077.
