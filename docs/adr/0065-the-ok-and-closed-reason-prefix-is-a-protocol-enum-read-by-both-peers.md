# 65. The OK and CLOSED reason prefix is a protocol enum read by both peers

## Status

Accepted

## Context

NIP-01 gives the human-readable text of an OK or CLOSED reply a machine-readable head: a single word, a colon, then the prose. The words are fixed by the protocol (`duplicate`, `pow`, `blocked`, `rate-limited`, `invalid`, `restricted`, `mute`, `error`) and NIP-42 adds `auth-required`. The relay writes the prefix so that the client can act on it without reading the prose: retry after a rate limit, authenticate and resend, or give up on a block.

This library held the vocabulary in two places, neither of them right. `OkMessage::isAuthRequired()` matched one prefix by string comparison, so the reply knew about one word and no others. The relay library defined the full list as an enum of its own, called a rejection reason, and formatted the wire text from it. A client built on this library therefore had no name for `blocked` or `rate-limited` and had to compare strings, which the relay pool already does on the TypeScript side, while the relay library carried a copy of a vocabulary that belongs to the messages it was writing.

The name mattered too. Two of the words, `duplicate` and `pow`, arrive on an accepted OK as often as on a refused one, so the head of the text is not a rejection reason; it is the reason prefix of whatever the relay said.

## Decision

`ReasonPrefix` is a backed enum in this library carrying the NIP-01 words and the NIP-42 addition, with `format()` to write the wire text and `tryFromMessage()` to read the head of one. `OkMessage` and `ClosedMessage` expose `getReasonPrefix(): ?ReasonPrefix`, parsed from the message text; a message without a known head answers null, and `isAuthRequired()` is expressed through it. The relay library writes its replies with this enum and defines none of its own.

## Consequences

- A client branches on an enum case, not a string, and the relay and the client cannot drift apart on the spelling because they share the one list.
- The prefix is parsed from the text on demand rather than stored beside it, so the message stays the plain wire triple and a hand-written reason without a head still round-trips untouched.
- The list follows the protocol, not what any one relay emits. Adding a case because a relay needs it is right; removing one because no relay here sends it is not, since a client still has to read it.
- Do not reintroduce a per-prefix boolean for each case as `isAuthRequired()` once was. That one remains because a client's resend loop asks it directly; the rest are a comparison against the enum.
