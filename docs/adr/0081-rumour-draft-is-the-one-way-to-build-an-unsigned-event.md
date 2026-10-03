# 81. `Rumour::draft` is the one way to build an unsigned event

## Status

Accepted

## Context

At v0.8.3 `RumourFactory` held sixteen static methods. Nine were `createCustomKind(KIND, content, tags)` with a kind constant filled in — `createTextNote`, `createFollowList`, `createMuteList` and the rest — and `createCustomKind` itself only forwarded to the public `Rumour` constructor with `created_at` defaulted to now. Callers therefore had three ways to build the same unsigned event, and the thin wrappers mixed `string` and `EventContent` for the same field.

Which factories remain, and how they are shaped, is decided separately (ADR-0105), as is the rule `createDeletion` enforces (ADR-0106): each could be revised without this one.

## Decision

`Rumour`'s constructor is private. `Rumour::draft(PublicKey $pubkey, EventKind $kind, ?EventContent $content = null, ?TagCollection $tags = null, ?Timestamp $createdAt = null)` is the one way to build one: empty content, no tags and the current instant are the defaults, and `created_at` is read directly rather than through an injected clock (ADR-0005). Its five parameters are the five fields of an unsigned event, which a value's named constructor takes as they are: together they are the rumour, so bundling them into a parameter object would only build the rumour twice.

A factory that only fills in a kind constant is not kept: that is `Rumour::draft` with the kind named at the call site.

## Consequences

- One path builds an unsigned event, and a kind with no tag logic needs no factory.
- `withTags()` and `withCreatedAt()` transform a rumour already drafted, returning a new one with one field replaced; they are not a second way to build one.
- `draft()` and `withTags()` add `["d", ""]` to the tags of an addressable kind (30000 to 39999) that carries no `d` tag, so every addressable event this package builds names its identifier; the empty identifier is what a reader takes a missing `d` to mean anyway (nostr-adrs ADR-0007).
- Breaking: `new Rumour(…)` no longer compiles, and `createCustomKind`, `createMetadata`, `createEncryptedDirectMessage`, `createFollowList`, `createRelayList`, `createMuteList` and `createDmRelayList` are removed from `RumourFactory`.
- Do not add a factory that only fills in a kind constant.
- Shared decision: nostr-adrs ADR-0007.
