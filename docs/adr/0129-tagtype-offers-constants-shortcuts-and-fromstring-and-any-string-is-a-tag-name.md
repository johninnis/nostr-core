# 129. `TagType` offers constants, shortcuts and `fromString`, and any string is a tag name

## Status

Accepted

Supersedes ADR-0023 in full. ADR-0023 kept the three construction surfaces on the ground that they all funnel into one validation that rejects the empty string. The code has no such validation and NIP-01 gives no ground for one: the empty string is a tag name. This record restates the three surfaces on the ground that holds, and holds the complete current decision.

## Context

`TagType` is the value object for a NIP-01 tag name. NIP-01 says each tag is an array of one or more strings and constrains no tag name, so the set is open and includes the empty string; `Tag::tryFromArray` builds a `TagType` from whatever arrives on the wire, and a tag this library has never heard of has to round-trip (ADR-0126).

`TagType` exposes three ways to get a value:

- **typed constants** for the well-known names (`TagType::EVENT = 'e'`, `TagType::ROOT_PUBKEY = 'P'`, …), naming the wire vocabulary;
- **named shortcuts** for the tags built by hand (`TagType::event()`, `TagType::pubkey()`, `TagType::expiration()`, …), covering only some of the constants;
- **`fromString(string)`** and **`tryFromString(mixed)`**, the general constructors for any name.

Three ways to get one value reads like "more than one way to do a thing", and a shortcut set that covers some constants but not others reads like a half-finished API. Both invite the same correction: delete the shortcuts and route everything through `fromString(TagType::CONST)`, or complete the set with a shortcut per constant.

## Decision

- **Keep the three surfaces.** They are one path, not three. A tag name has no invariant beyond being a string, so there is nothing for them to validate differently: `tryFromString` refuses only a value that is not a string, `fromString` takes a string already, and every shortcut is `fromString(self::CONST)` with no logic of its own.
- **Each surface has its own job.** Constants name the wire vocabulary once, so no tag name is a bare literal in the code. Shortcuts give a typo-proof, discoverable call for the tags assembled by hand here and in the consumers. `fromString` and `tryFromString` take the open set, wire-sourced names included.
- **The shortcut set is intentionally partial.** It covers the tags applications assemble by hand. A rarely built well-known tag is written `TagType::fromString(TagType::CONST)`; that is the expected form, not a gap to fill with a shortcut per constant.
- **Any string is a tag name, the empty string included.** `TagType` refuses no string. A rule about a particular tag's name or values belongs to the reader or builder of that tag, not to `TagType`.

## Consequences

- A unit that writes `TagType::identifier()` beside `TagType::fromString(TagType::TITLE)` uses the API as intended; it is not an inconsistency to unify.
- A new shortcut is warranted when a tag becomes commonly hand-built, not for every constant.
- The shortcuts are published surface used across the consumers; removing them is a breaking change, not an internal refactor.
- `TagTypeTest` pins the shortcuts and that `fromString('')` and `tryFromString('')` build a tag name. Do not add a validation that refuses a string, and do not collapse the shortcuts onto `fromString`.
- Shared decision: nostr-adrs ADR-0103.
