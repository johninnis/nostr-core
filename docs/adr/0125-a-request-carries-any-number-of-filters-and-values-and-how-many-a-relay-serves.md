# 125. A request carries any number of filters and values, and how many a relay serves is its policy

## Status

Accepted

## Context

NIP-01 writes a `REQ` as `["REQ", <subscription_id>, <filters1>, <filters2>, ...]`, and NIP-45 writes `COUNT` the same way. It writes a filter's list fields as `"ids": <a list of event ids>`, `"authors": <a list of lowercase pubkeys>`, `"kinds": <a list of a kind numbers>` and `"#<single-letter (a-zA-Z)>": <a list of tag values>`, and says they "are JSON arrays with one or more values". It sets no maximum on either count.

This package set both. `FilterRequestMessage` refused any request with more than 20 filters. `Filter` refused a filter whose `ids`, `authors` or `kinds` held more than 1000 values, and `TagFilter` refused more than 1000 values for one tag name, on both the parsing and the building path. A `REQ` over either cap never reached the code that answers it: the parser called it malformed, although nothing in the protocol makes it so. A client following more than 1000 keys could not build the filter that reads its timeline.

Neither cap bounded anything a relay depends on. A relay already limits the filters it serves as configurable policy, refuses an excess with a `CLOSED` whose reason NIP-01 prefixes (`blocked: too many filters (max 5)` in innis/nostr-relay), and advertises the limit as NIP-11's `max_filters`. The parser's fixed 20 sat above that policy, so it did nothing for a relay configured below it, and it made a relay configured above it refuse requests it was willing to serve, with no `CLOSED` saying why. The value cap was the same cap one level down: a filter of 1000 values in each of its fields and 52 tag conditions already holds tens of thousands of values, so a relay that cares about query cost has to bound it in its own policy anyway. innis/nostr-relay read the value constant only to split the event ids a deletion request names into filters of 1000, a limit that was never its own.

## Decision

- `ReqMessage` and `CountMessage` carry one or more filters, with no maximum. `FilterRequestMessage::tryFrom` returns `null` only for an empty filter list, and `from` throws only for that.
- `Filter` and `TagFilter` hold any number of values in each list field. Parsing and building refuse a list only for what NIP-01 states about its values (an `ids`, `authors`, `#e` or `#p` value that is not 64 lowercase hex characters, a non-UTF-8 tag value), never for its length.
- How many filters a relay serves, and how many values in a filter, is relay policy, configured and answered by the relay, never by this package's parser.

## Consequences

- A client builds the filters its data needs, a follow list of any size included, and a relay sees every well-formed `REQ` and `COUNT` and refuses an over-large one in its own words with its own limit.
- A request's size is bounded by the frame that carries it, which the transport limits; a relay that wants a lower ceiling imposes it where it owns its resource policy.
- Do not reintroduce a filter-count constant in the message types or a value-count constant in `Filter` or `TagFilter`. A limit belongs to the relay that applies it.
- The `limit` field follows the same reasoning and carries no library ceiling either (ADR-0102).
- Shared decision: nostr-adrs ADR-0069.
