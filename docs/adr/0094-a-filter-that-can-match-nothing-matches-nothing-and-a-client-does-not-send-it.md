# 94. A filter that can match nothing matches nothing, and a client does not send it

## Status

Accepted

## Context

nostr-adrs ADR-0069 decides for every implementation that an empty list attribute or a `since` after its `until` matches nothing, that a tag condition is `#` plus exactly one letter, that a parser carries every well-formed filter as received, and that only a client's send path leaves out a filter that can match nothing.

In this package `Filter::matches` already read an empty `ids`, `authors`, `kinds` or tag list as matching nothing, but such a filter was sent as it came, and `TagFilter` accepted a `#` key of any length, which no relay indexes.

## Decision

This package implements nostr-adrs ADR-0069 as follows:

- `Filter::canMatch()` is the one test of whether a filter can match anything, and `matches()` is `false` for a filter it refuses.
- `TagFilter` refuses any name but one letter, `a`–`z` or `A`–`Z`, so `Filter::tryFromArray` returns `null` for a filter carrying one; a built filter cannot carry one.
- `TagFilter` refuses a `#e` value that `EventId::tryFromHex` refuses and a `#p` value that `PublicKey::tryFromHex` refuses, so the one hex rule for `ids` and `authors` also holds the 64-character lowercase hex NIP-01 requires of `#e` and `#p`; `tryFromValues` and `tryFromArray` return `null`, `fromValues` throws.
- `FilterCollection::matchable()` keeps the filters that can match. A client decides with it, before it builds a `REQ` or `COUNT`, whether there is anything to send.
- `ReqMessage` and `CountMessage` carry every syntactically valid filter exactly as received, `{"authors":[]}` and crossed bounds included, with one parser per message (ADR-0077).
- `FilterHasher` is unchanged: every existing hash stays the same.

## Consequences

- `innis/nostr-client` answers a subscription with no matchable filter with an end of stored events (its ADR-0018).
- Do not drop or refuse an unmatchable filter in the message parser, and do not skip an empty list, accept a multi-letter `#` key, or accept a `#e` or `#p` value that is not 64-character lowercase hex to be forgiving.
- Shared decision: nostr-adrs ADR-0069.
