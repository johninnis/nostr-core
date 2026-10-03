# 92. A single-valued tag is read as one claim, the `d` tag included

## Status

Accepted

## Context

Many tags name one value for their event: the `d` identifier of an addressable event, an article's `title`, `summary`, `image` and `published_at`, a live event's `status` and `streaming`, a highlight's `context`, `comment` and source `r`, a comment's `K` and `k` kinds, its `E` / `A` / `I` root and `e` / `a` / `i` parent, a NIP-10 `root` or `reply` marker, a nutzap's `unit` and recipient `p`, a zap receipt's recipient `p`, a NIP-94 field. No NIP forbids writing one twice. The readers answered with whichever came first (`TagCollection::getFirstValueByType`, `getFirstPubkeyByType`, `$values[0]`) or, in the reply chain, whichever came last.

Order is chosen by the author. A reader taking the first value and another taking the last show two different events from one, and an addressable event whose `d` tags disagree is stored under one coordinate by a relay that reads the first and looked up under another by a client that reads the last. Reading the first `d` is what relays commonly do, and matching them looks like the safe choice; it makes the answer depend on who wrote the tags in which order, which is the fault ADR-0079 (the zap receipt), ADR-0127 (NIP-42) and the NIP-98 validator already refuse for the tags they bind.

## Decision

- Every reader of a single-valued tag reads all of its occurrences. Occurrences carrying one value are one claim; occurrences carrying different values state no value. `TagCollection::getSoleValueByType` is the reader, `getSolePubkeyByType` reads a pubkey through it, and `getFirstValueByType` and `getFirstPubkeyByType` are removed, so no first-wins read is left to reach for.
- The reader answers all three cases in one call. `getSoleValueByType` returns a `SoleTagValue` whose `getState()` is a `SoleTagValueState` — `Absent`, `One` or `Disagreeing` — and whose `getValue()` is the one value, or `null` in the other two cases. A reader for which a disagreement is absence uses `getValue()`; a reader that must tell them apart (the NIP-98 validator reports a missing tag apart from a repeated one, a nutzap's absent `unit` defaults to `sat` where a disagreeing one refuses the nutzap, an absent `d` tag is the empty identifier where disagreeing ones are none) matches on `getState()`. No caller reads the tags a second time to recover what a nullable answer lost, and there is no second reader.
- `TagCollection::getIdentifier()` returns the empty string for an event with no `d` tag, as NIP-01 addresses it, and `null` when `d` tags disagree. Such an event has no coordinate: `EventCoordinate::tryFromEvent` returns `null`, `LongformMetadata` and `LiveEventMetadata` are read by `tryFromTagCollection` and return `null`, and `RumourFactory::createDeletion` names it by id.
- `EventValidator` refuses an addressable event whose `d` tags disagree, so a relay neither stores it under a coordinate nor keeps it as a regular event that can never be replaced.
- A field whose tags disagree is absent from the value that reads it (a title, a `published_at`, a NIP-94 field, a recipient), and a value that cannot stand without it is not built: a comment whose `K` or `k` disagree, a nutzap whose `unit` tags disagree, file metadata whose `url` tags disagree.
- A pointer is one claim when every occurrence names the same event, address or external id, whatever relay hints they carry. `ReplyChainAnalyser` reads a comment's roots and parents, and NIP-10 `root` and `reply` markers, this way.
- A highlight's source `r` URL is read by the rule in ADR-0107, which picks the candidates among the `r` tags and reads them as one claim through `SoleTagValue::fromValues`, the rule `getSoleValueByType` applies, since only some of the `r` tags are candidates.
- Not single-valued, and unchanged: NIP-10's deprecated positional `e` tags, a reaction's `e` tags, and `expiration`, which keeps its own rule (ADR-0071).
- This applies the shared rule (nostr-adrs ADR-0014) to every single-valued tag this package reads.

## Consequences

- No reader's answer depends on tag order, and every reader of one event sees one value or none.
- An addressable event with disagreeing `d` tags cannot be addressed, stored or replaced, and a relay using `EventValidator` refuses it.
- Breaking: `getFirstValueByType` and `getFirstPubkeyByType` are removed in favour of `getSoleValueByType`, new since v0.8.3, which returns a `SoleTagValue` (a caller that wants the value calls `getValue()`); `LongformMetadata::fromTagCollection` and `LiveEventMetadata::fromTagCollection` become `tryFromTagCollection` and return `?self`.
- Do not restore a first-wins or last-wins read for any of these tags, for the `d` tag least of all, to match a relay that does.
- Shared decisions: nostr-adrs ADR-0014 and ADR-0007.
