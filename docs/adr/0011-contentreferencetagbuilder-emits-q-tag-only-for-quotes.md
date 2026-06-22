# 11. `ContentReferenceTagBuilder` emits a `q` tag only for a quoted event

## Status

Accepted

## Context

A quoted NIP-21 entity (`note`, `nevent`, `naddr`) embedded in content needs a tag so clients can resolve and count the quote. An earlier version of this builder emitted **both** a `q` tag and an `e` mention (`["e", id, "", "mention"]`) for each quoted event, on the reasoning that older clients only read the NIP-10 `e` mention, so emitting both maximised the set of clients that resolved the quote.

The current specs have moved against that hedge:

- **NIP-18** now states the `q` tag's purpose explicitly: "The `q` tag ensures quote reposts are not pulled and included as replies in threads." Mentions to NIP-21 entities "must be converted into `q` tags."
- **NIP-10** no longer documents a `mention` marker at all — only `reply` and `root`. A bare `e` tag with an unrecognised marker risks being read as a thread reference, and under NIP-10's deprecated positional scheme a lone `e` tag *is* the reply target. Emitting the `e` mention therefore reintroduces the exact thread pollution the `q` tag exists to prevent.

## Decision

`ContentReferenceTagBuilder` emits a `q` tag only for a quoted event — no accompanying `e` mention. The `q` tag carries the event id and, where known, the author pubkey, per the NIP-18 shape `["q", "<id>", "<relay>", "<pubkey>"]`. An `e` tag is still emitted where it is a genuine NIP-10 thread reference (a reply's `root` / `reply`), which is a different code path; this decision concerns the quote path only.

## Consequences

- Quoted events resolve via the `q` tag (NIP-18) and cannot be pulled into a thread as replies by positional-scheme clients.
- Pre-`q` clients that only ever rendered quotes from an `e` mention no longer surface the quote — an acceptable loss against reintroducing thread pollution, and aligned with the current spec intent.
- The TypeScript sibling (`nostr-core-ts`) makes the identical change for cross-language parity; both are pinned by tests asserting a quoted event produces a `q` tag and no `e` tag.
- Do not re-add the `e` mention for quotes "for older clients" — the spec now treats `q` as the sole quote mechanism.
