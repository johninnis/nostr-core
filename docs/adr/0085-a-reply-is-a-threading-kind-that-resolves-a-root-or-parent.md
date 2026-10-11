# 85. A reply is a threading kind that resolves a root or parent, read the way its kind threads, and a comment's scope is an event, an address or an external identifier

## Status

Accepted

Supersedes ADR-0072. Its rule — the kind decides whether an event threads, the tags decide whether it resolves a root or a parent — is carried forward unchanged and is now the shared decision, nostr-adrs ADR-0012. What is revised is how a comment's tags are read: ADR-0072's analyser read only `E`/`e`, so a comment on an addressable event or on an external identifier resolved nothing; and which kinds thread, which ADR-0072 held to kinds 1 and 1111 and nostr-adrs ADR-0012 now widens to kinds 1244, 42, 1311 and 9. This record holds what is particular to this package.

## Context

nostr-adrs ADR-0012 decides for every implementation what a reply is: a kind that threads whose tags resolve a root or a parent. NIP-10 threads kind 1 by `e` tags with `root` and `reply` markers or by position; NIP-22 threads kind 1111 by its uppercase root scope and lowercase parent. Other NIPs thread their own kinds. NIP-A0: "To be used for replies, `kind: 1244` events MUST follow the structure of `NIP-22`." NIP-28 marks a kind 42's `e` tags with NIP-10's markers, and its `root` names the channel's kind 40, not a message. NIP-53: "An `e` tag denotes the direct parent message this post is replying to", of kind 1311, whose `a` tag names the live activity. NIP-C7: "A reply to a `kind 9` is an additional `kind 9` which quotes the parent using a `q` tag." This package threaded kind 1 and kind 1111 alone, so every one of these replies read as no reply.

NIP-22: "Comments MUST point to the root scope using uppercase tag names (e.g. `K`, `E`, `A` or `I`) and MUST point to the parent item with lowercase ones (e.g. `k`, `e`, `a` or `i`)." Its first example scopes a comment on a long-form article to `A` and `a`, and "`I` and `i` tags create scopes for hashtags, geohashes, URLs, and other external identifiers", whose values NIP-73 lists and which "MAY have a url hint as the second argument".

## Decision

`ReplyChainAnalyser` implements nostr-adrs ADR-0012 and is the one answer to "is this a reply"; `Rumour::isReply()` delegates to it.

- `ReplyChain` holds one root and one parent, each an `EventReference`, an `EventCoordinate` or an `ExternalContentId` (`getRoot()`, `getParent()`), and the kind it was read for. It holds the kind rather than a flag that the kind threads, and derives `isReply()` from both.
- The kinds that thread are 1, 1111, 1244, 42, 1311 and 9, and none given. `EventKind` names 1244 and 1222, NIP-A0's voice message kinds, as `VOICE_MESSAGE_COMMENT` and `VOICE_MESSAGE` (nostr-adrs ADR-0109).
- `ThreadingConvention::ofKind()` is the one place a kind's threading is decided: `ShortNote` for kind 1 and for no kind, `Comment` for kinds 1111 and 1244, `Channel` for kind 42, `LiveChat` for kind 1311, `Chat` for kind 9, and `null` for every kind that does not thread. `ReplyChain::isReply()`, `ReplyChainAnalyser::analyse()` and `RumourFactory::createReply()` each match on it, so a kind added to the threading set is added once, and every match that must handle it fails analysis until it does.
- Each kind is read the way its NIP threads it:
  - Kind 1, and no kind: NIP-10's `e` tags, marked or positional.
  - Kind 1111 and kind 1244: a comment's root is read from `A`, then `E`, then `I` paired with `K`, and its parent from `a`, then `e`, then `i` paired with `k`, each tag name as one claim (ADR-0092, nostr-adrs ADR-0014): a name naming no single target hands over to the next (nostr-adrs ADR-0085). An `A`/`a` value that is not a valid coordinate, or an `E`/`e` value that is not an event id, names nothing.
  - Kind 42: its `e` tags by their markers alone. The `root` marker names its channel and the `reply` marker its parent; an unmarked `e` tag names neither. A kind 42 is a reply only when its parent resolves, since a root that is its channel answers no message.
  - Kind 1311: its `e` tags, as one claim, name its parent, whatever marker they carry; it has no root, its `a` tag naming the live activity.
  - Kind 9: its first `q` tag naming an event id is its parent, the one place a `q` tag threads; it has no root.
- `ExternalContentId` is the NIP-73 identifier as a value, beside `EventCoordinate`: the non-empty tag value kept verbatim, its NIP-73 type (`getKind()`), and the url hint as a `?WebUrl`, an http(s) URL checked by `HttpUrl`'s rules and kept exactly as written, fragment included, dropped when it is not one. The hint is itself one claim: the hints the `I` (or `i`) tags carry are compared exactly as written, those that are not web URLs dropped (nostr-adrs ADR-0090), and the hint is kept only when what remains is one text, so two spellings of one URL state no hint. External content is named only by one non-empty `I` value together with one non-empty `K` value (for the parent, `i` with `k`); an `I` without its `K`, with an empty `K`, or with `K` tags that disagree resolves nothing, and neither does an empty `I`/`i` value or `I` tags that disagree. Two identifiers are equal by value alone.
- A reply to a comment or a voice message comment copies the parent's root scope (`A`, `E`, `I`, `K`, `P`) only when the analyser resolves the parent's root and its `K` is one non-empty value. Otherwise the parent is itself the root, scoped as any other event is: `E` naming it, `K` its kind and `P` its author.
- A copied `I` tag carries its hint exactly as the parent wrote it when it is a `WebUrl`, or no hint when it is not; the builder never republishes a hint a reader would drop (nostr-adrs ADR-0090).
- A copied scope that holds no `P` with a valid public key gains one for the root author when it is known: the pubkey of an `A` root's coordinate, or the one author the `E` tags naming the root event agree on. An external root has no author. When the parent is itself the root, its own author is the `P`.
- Every `e`, `E` and `q` tag is read through `EventReference::tryFromTag`, so the author a tag names has one reading wherever it is asked for: an `e` tag names it by its length, in a fifth element when there is one and otherwise in a fourth that is a public key (nostr-adrs ADR-0012); an `E` or `q` tag, which has no marker slot, names it in its fourth element. Only an `e` tag carries a NIP-10 marker.

## Consequences

- A chat, channel, live chat or voice reply reads as the reply its own clients thread it as.
- A channel message naming only its channel is no reply, though its root resolves; do not derive `isReply()` from the root alone for kind 42.
- A comment naming both an address and an event as its root is rooted at the address, whatever order its tags come in; the event id is not part of the chain.
- A caller asks `getRoot() instanceof EventReference` where it needs an event id.
- Do not restore a kind check in `Rumour`, do not test a kind against a threading kind anywhere but `ThreadingConvention::ofKind()`, and do not widen the threading kinds without a NIP and the shared record.
- Shared decisions: nostr-adrs ADR-0012, ADR-0076 (the copied root scope and its `P`), ADR-0085, ADR-0090 and ADR-0109.
