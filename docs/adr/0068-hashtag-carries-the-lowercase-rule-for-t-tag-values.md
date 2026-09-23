# 68. `Hashtag` carries the lowercase rule for `t` tag values

## Status

Accepted

## Context

NIP-24 defines the `t` tag as a hashtag whose value must be a lowercase string. Any party that writes a `t` tag, indexes one, or matches a `#t` filter against one has to apply the same rule, or a post tagged `Nostr` is invisible to a search for `nostr`.

This library's `Tag::hashtag()` accepted any string and stored it verbatim, so a client built on it could emit a non-conforming tag without noticing, while a relay application downstream lowercased the value at its persistence edge in two separate places. The rule was enforced where it happened to be needed instead of where the vocabulary is defined.

## Decision

`Hashtag` is a value object in the tag vocabulary, and `Tag::hashtag()` takes one rather than a string. It is built through the trust boundary the rest of the package uses: `Hashtag::tryFromString()` returns null for anything that is not a non-empty string, and `Hashtag::fromString()` asserts the same on a trusted value and throws otherwise. Both lowercase what they accept. A relay or index needing the canonical form of a hashtag from untrusted input builds a `Hashtag` and reads it back.

Everything in this package that produces a hashtag produces one of these, and it produces them in a `HashtagCollection` like every other group of values the package hands across a boundary. `EventContent::extractHashtags()` returns one, `LongformMetadata` holds its topics as one, and `TagCollection::getHashtags()` reads them back out of a tag set beside the existing `getPubkeys()` and `getEventIds()`.

The read side matters as much as the write side. A type that a caller can only write to, and must read back as raw text, has moved the canonicalisation problem rather than solved it: the caller would still be comparing a `Hashtag` it built against a string the tag set gave it.

An empty value is not a hashtag. `Tag` itself permits an empty value, because the tag vocabulary is open, but a `t` tag with nothing in it names nothing, so the value object refuses it the way `SubscriptionId` and `Challenge` refuse theirs.

## Consequences

- A `t` tag built through this library is lowercase by construction, and a store that canonicalises hashtags does so through the same value, so a client's tag and a relay's index agree.
- Lowercasing is Unicode-aware (`mb_strtolower`), matching what a relay does to the values it indexes.
- `Hashtag` does not strip a leading `#` or trim whitespace. NIP-24 says nothing about either, and a rule this library invented would diverge from every other implementation. Do not add one.
- The rule binds what this library *writes*. A `#t` filter carries raw strings, because `TagFilter` is generic over every tag type and cannot know which of them are hashtags; a caller building a `#t` filter canonicalises its values through `Hashtag` first, and one that does not will match nothing on a conforming relay.
- `Tag::hashtag()` no longer takes a string, `LongformMetadata` takes and returns a `HashtagCollection` rather than a `list<Hashtag>`, and `EventContent::extractHashtags()` returns one too. All three are breaking changes on the 0.x line, taken together so that a consumer adapts to the hashtag type once rather than twice.
- Do not add a string overload anywhere on this path to spare a caller the wrap. The wrap is where the rule is applied, and a second way in is a second answer to what a hashtag is.
