# 86. `Rumour::tryFromArray` rejects a non-string `content`

## Status

Accepted

Supersedes ADR-0022, which coerced a non-string `content` to its JSON string. This record holds the complete current decision.

## Context

NIP-59: "A `rumor` is the same thing as an unsigned event." NIP-01 defines an event's `content` as `<arbitrary string>`. A value whose `content` is an object, number, boolean or null is therefore not an event or a rumour. ADR-0022 re-encoded such a value to a JSON string and kept it, inventing content the sender never wrote and letting malformed input through the one parser every event passes.

## Decision

`Rumour::tryFromFields`, the one reader of an unsigned event's fields (ADR-0103), refuses — so `Rumour::tryFromArray` returns `RumourParseFailure::Malformed` and `Event::tryFromArray`/`tryFromJson` return `null` — when `content` is not a string or is not valid UTF-8, the same "type mismatch is malformed" rule as every other field.

The UTF-8 rule belongs to `EventContent`, the one parser that owns it (ADR-0077): `EventContent::tryFromString` returns `null` and `EventContent::fromString` throws `InvalidArgumentException` for text that is not UTF-8, and `Rumour::tryFromFields` reads `content` through `tryFromString`. A rumour therefore cannot be drafted with content it could not serialise, as a `Tag` cannot be built with a value that is not UTF-8.

## Consequences

- An event with non-string content is refused at the boundary rather than rewritten.
- Tests pin the refusal for an object, a list, a number, a boolean and null, and `EventContent`'s refusal of text that is not UTF-8.
- Shared decision: nostr-adrs ADR-0073.
