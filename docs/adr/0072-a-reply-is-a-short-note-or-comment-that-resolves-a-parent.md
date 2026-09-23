# 72. A reply is a short note or a comment that resolves a parent

## Status

Accepted

## Context

Two places in this package answered "is this event a reply", and they did not agree.

`Rumour::isReply()` decided from the kind and the tags directly: a repost was never a reply, a NIP-22 comment always was, and anything else was a reply if it carried an `e` tag that was not marked `mention`. `ReplyChainAnalyser` answered the same question while building a `ReplyChain`, and its NIP-10 branch returned `true` whenever the event carried any `e` tag at all.

The two disagreed on cases that arise in ordinary traffic:

- An `e` tag whose id does not parse. The analyser counted the tag and called the event a reply while leaving both the root and the parent null, so callers got a reply pointing at nothing.
- A kind-1111 comment carrying no parseable `e` or `a` tag. `Rumour` called it a reply on the strength of the kind alone, again with nothing to reply to.
- A repost. `Rumour` excluded it; the analyser did not, so a `ReplyChain` built from a kind-6 event reported it as a reply to the event it reposts.

Excluding the repost by name was itself too narrow. A reaction names the event it reacts to, a zap receipt names the event that was zapped, and a long-form article or a legacy direct message may name an event for any number of reasons. Every one of those carries an `e` tag as a matter of routine, and none of them is a reply. Listing the kinds that are not replies means listing them forever and being wrong until the list is complete. The sibling TypeScript package had already settled this the other way round, by naming the kinds that thread.

A reply that names no parent is not a useful answer to give a caller. Anything that acts on the flag goes on to ask for the root or the parent, and gets null from a value that has just said there is one. The flag and the chain are returned together and contradict each other.

So the answer needs both halves. The kind decides whether the event threads at all, because NIP-10 defines threading for the short note and NIP-22 defines it for the comment, and no other kind claims it. The tags then decide whether it actually resolves a parent. Neither half is sufficient: a kind alone says nothing about what the event points at, and an `e` tag alone appears on events that are not replies.

## Decision

There is one answer to this question and `ReplyChainAnalyser` gives it. `Rumour::isReply()` delegates to it, so there is no second rule anywhere:

- An event is a reply when its kind threads **and** it resolves a root or a parent. Both halves are required.
- The kinds that thread are the short note under NIP-10 and the comment under NIP-22. Every other kind is not a reply however many events it names, so a reaction, a repost, a zap receipt and a long-form article are all excluded by the same rule rather than by a list of exceptions.
- An `e` tag that does not parse, or one marked `mention`, resolves neither a root nor a parent, so it cannot make a threading kind a reply either.
- A marker NIP-10 does not define is treated as no marker at all, so the tag is read positionally. The old rule refused any marker it did not recognise, which meant a client writing a marker this library had not heard of lost its thread. Reading it positionally is what a relay-agnostic reader does with an unknown marker, and the tag still has to resolve a root or a parent to make the event a reply.
- A caller that names no kind is asking what a tag set looks like as a thread, and is answered on the tags alone. `Rumour` always names its kind, so an actual event always gets the full rule.

This matches `nostr-core-ts`, which reaches the same answer for every kind.

## Consequences

- A caller that is told an event is a reply can ask for the parent and get one. The flag and the chain agree by construction, because the flag is derived from the chain.
- This is a behaviour change for several inputs a released version answered differently. An event whose only `e` tag is unparseable, a comment with no resolvable parent, and every reaction, zap receipt, long-form article and legacy direct message carrying an `e` tag all now report `false`. The last group is the one a consumer is most likely to notice, and it was the wrong answer before: a reaction has never been a reply to the note it reacts to.
- One input changes the other way. A short note whose `e` tag carries a marker NIP-10 does not define now reports `true`, where the released version reported `false`. That is the only widening, and it is deliberate: an unknown marker is not evidence that the event is not a reply.
- The rule is enforced once. Do not restore a kind check in `Rumour`: it would reintroduce the second definition this record exists to remove, and the two would drift again.
- Adding a kind to the threading set is a deliberate act. Do not widen it because some client threads an unusual kind; widen it when a NIP says that kind threads, and change the TypeScript package in the same breath so the two keep agreeing.
- The early return that skipped analysis when an event carried no `e` tag is gone, because the general computation already yields the same result for that input. Do not add it back as an optimisation; it is the special case that let the two answers diverge.
- A kind on its own still never makes something a reply. If a future NIP defines a reply relationship, it adds a kind to the threading set and the tags still have to resolve a parent.
