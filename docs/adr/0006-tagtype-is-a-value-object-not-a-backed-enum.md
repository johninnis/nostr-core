# 6. `TagType` is a value object, not a backed `enum`

## Status

Accepted

## Context

The well-known NIP-01 tag names form a small, named set, so a backed `enum` looks like the obvious model — and the ecosystem rule is "backed enums for closed sets". But NIP-01 tag names are an *open* set: any string is a valid tag name, and `Tag::fromArray` builds a `TagType` from whatever arrives on the wire.

## Decision

`TagType` is a value object, not a backed `enum`. It keeps typed constants for the well-known names plus a `fromString` constructor for the rest, which is the correct shape for an open vocabulary.

A backed enum models a *closed* set — its `tryFrom` returns `null` for an unrecognised case, so a relay or client could no longer round-trip a tag it does not know.

## Consequences

- Any tag name on the wire round-trips, including ones this library has never heard of.
- The "backed enums for closed sets" rule still holds; this set is simply not closed.
- A test pins the design: it fails if `TagType` is converted to an enum and stops accepting arbitrary tag names. Do not "tighten" it into an enum.
