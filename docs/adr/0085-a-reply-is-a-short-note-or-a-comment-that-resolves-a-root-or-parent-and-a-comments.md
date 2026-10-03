# 85. A reply is a short note or a comment that resolves a root or parent, and a comment's scope is an event, an address or an external identifier

## Status

Accepted

Supersedes ADR-0072. Its rule — the kind decides whether an event threads, the tags decide whether it resolves a root or a parent — is carried forward unchanged and is now the shared decision, nostr-adrs ADR-0012. What is revised is how a comment's tags are read: ADR-0072's analyser read only `E`/`e`, so a comment on an addressable event or on an external identifier resolved nothing. This record holds what is particular to this package.

## Context

nostr-adrs ADR-0012 decides for every implementation what a reply is: a kind that threads (kind 1 or kind 1111) whose tags resolve a root or a parent, with NIP-10's markers and NIP-22's root and parent tags read as those NIPs define them.

NIP-22: "Comments MUST point to the root scope using uppercase tag names (e.g. `K`, `E`, `A` or `I`) and MUST point to the parent item with lowercase ones (e.g. `k`, `e`, `a` or `i`)." Its first example scopes a comment on a long-form article to `A` and `a`, and "`I` and `i` tags create scopes for hashtags, geohashes, URLs, and other external identifiers", whose values NIP-73 lists and which "MAY have a url hint as the second argument". This package's `ReplyChain` held only event-id references, so those comments had no root, no parent, and were not replies.

## Decision

`ReplyChainAnalyser` implements nostr-adrs ADR-0012 and is the one answer to "is this a reply"; `Rumour::isReply()` delegates to it.

- `ReplyChain` holds one root and one parent, each an `EventReference`, an `EventCoordinate` or an `ExternalContentId` (`getRoot()`, `getParent()`), and the kind it was read for. It holds the kind rather than a flag that the kind threads, and derives `isReply()` from both: the kind is a short note, a comment, or none given, and a root or a parent resolved.
- A comment's root is read from `A`, then `E`, then `I` paired with `K`, and its parent from `a`, then `e`, then `i` paired with `k`, each tag name as one claim (ADR-0092, nostr-adrs ADR-0014): a name naming no single target hands over to the next (nostr-adrs ADR-0085). An `A`/`a` value that is not a valid coordinate, or an `E`/`e` value that is not an event id, names nothing. `CommentMetadata`'s root scope is the kind of that one root.
- `ExternalContentId` is the NIP-73 identifier as a value, beside `EventCoordinate`: the non-empty tag value kept verbatim, its NIP-73 type (`getKind()`), and the url hint as an `?HttpUrl` that is dropped when it is not an http(s) URL. The hint is itself one claim (nostr-adrs ADR-0014): the hints the `I` (or `i`) tags carry are read in canonical form, those that are not web URLs dropped (nostr-adrs ADR-0090), and the hint is kept only when what remains is one URL, so the order of the tags never chooses it. NIP-22 says "Tags `K` and `k` MUST be present", and for external content they carry its NIP-73 type (its website example pairs `I` with `K` `web`, its podcast example with `K` `podcast:item:guid`) — so external content is named only by one non-empty `I` value together with one non-empty `K` value (for the parent, `i` with `k`), exactly as @innis/nostr-core's `ExternalContentRef` is; an `I` without its `K`, with an empty `K`, or with `K` tags that disagree resolves nothing, and neither does an empty `I`/`i` value or `I` tags that disagree. Two identifiers are equal by value alone.
- A caller that names no kind is answered on the tags alone as a NIP-10 thread.
- A reply to a comment copies the parent's root scope (`A`, `E`, `I`, `K`, `P`) only when the analyser resolves the parent's root and its `K` is one non-empty value. Otherwise the parent comment is itself the root, scoped as any other event is: `E` naming it, `K` 1111 and `P` its author. NIP-22: "Comments MUST point to the root scope", and a scope copied from a comment that names none would point nowhere.
- A copied `I` tag carries its hint in the canonical http(s) form, or no hint when the parent's was not a web URL; the builder never republishes a hint a reader would drop (nostr-adrs ADR-0090).
- NIP-22: "Comments MUST point to the authors when one is available (i.e. tagging a nostr event). `P` for the root scope". A copied scope that holds no `P` with a valid public key gains one for the root author when it is known: the pubkey of an `A` root's coordinate, or the one author the `E` tags naming the root event agree on. An external root has no author. When the parent is itself the root, its own author is the `P`.
- Every `e`, `E` and `q` tag is read through `EventReference::tryFromTag`, so the author a tag names has one reading wherever it is asked for: an `e` tag names it by its length, in a fifth element when there is one and otherwise in a fourth that is a public key (nostr-adrs ADR-0012); an `E` or `q` tag, which has no marker slot, names it in its fourth element. Only an `e` tag carries a NIP-10 marker.

## Consequences

- A comment naming both an address and an event as its root is rooted at the address, whatever order its tags come in; the event id is not part of the chain.
- A caller asks `getRoot() instanceof EventReference` where it needs an event id.
- Do not restore a kind check in `Rumour`, and do not widen the threading kinds without a NIP and the shared record.
- Shared decisions: nostr-adrs ADR-0012, ADR-0076 (the copied root scope and its `P`), ADR-0085 and ADR-0090.
