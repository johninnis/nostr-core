# 126. `TagType` is a value object, not a backed `enum`, and names each meaning of a tag once

## Status

Accepted

Supersedes ADR-0006. Its decision that `TagType` is a value object rather than a backed `enum` is carried forward unchanged; how a `TagType` is obtained (constants, shortcuts, `fromString`) is ADR-0129's. What is revised is its consequence that `PARENT_KIND` and `EXTERNAL_CONTENT_KIND` are two names for unrelated meanings of `k`: they are one meaning, and this record holds the complete current decision.

## Context

The well-known NIP-01 tag names form a small, named set, so a backed `enum` looks like the obvious model, and a closed set is usually an enum. But NIP-01 tag names are an open set: any string is a valid tag name, and `Tag::tryFromArray` builds a `TagType` from whatever arrives on the wire. A backed enum models a closed set; its `tryFrom` returns `null` for an unrecognised case, so a relay or client could no longer round-trip a tag it does not know.

Unrelated NIPs reuse one letter for unrelated meanings: `u` is a NIP-98 URL and a NIP-60 mint, `i` is a NIP-73 external content id and a NIP-39 identity claim. Each name states what the tag means where it is used.

ADR-0006 counted `k` among them, as `PARENT_KIND` (NIP-22) and `EXTERNAL_CONTENT_KIND` (NIP-73). It is not. In a NIP-22 comment, `k` names the kind of the item commented on, and when that item is external content, its kind is the NIP-73 type NIP-73 tells the comment to write in `k`. One meaning had two names, and `externalContentKind()` was called by nothing but a test asserting it equalled `parentKind()`.

## Decision

- `TagType` is a value object, not a backed `enum`. Its constants, shortcuts and `fromString` are decided by ADR-0129.
- Two constants may share a wire value only where unrelated NIPs give the letter unrelated meanings, each stating what the tag means where it is used, so neither is a duplicate to collapse onto the other. A constant exists only while something reads or writes its tag: `MINT` (`'u'`, beside `URL`) and `IDENTITY_CLAIM` (`'i'`, beside `EXTERNAL_CONTENT`) were removed with `NONCE`, `SUBJECT`, `GEOHASH` and `DELEGATION` when nothing used them, and come back when a reader or builder of that tag does.
- One meaning has one name. `k` is `PARENT_KIND` (`parentKind()`) wherever it names the kind of what an event answers, the NIP-73 type of external content included; there is no `EXTERNAL_CONTENT_KIND`.

## Consequences

- Any tag name on the wire round-trips, including ones this library has never heard of.
- A test pins the design: it fails if `TagType` is converted to an enum and stops accepting arbitrary tag names. Do not tighten it into an enum.
- The constants sharing a wire value are not fenced at the code with a `// Deliberate:` marker; this record covers all of them.
- Breaking: `TagType::EXTERNAL_CONTENT_KIND` and `TagType::externalContentKind()` are removed; use `PARENT_KIND` and `parentKind()`.
- Breaking: `TagType::SENDER_PUBKEY` and `TagType::senderPubkey()` are renamed `ROOT_PUBKEY`, with no shortcut: a `P` tag is built by `RumourFactory`, not by hand (ADR-0129). `P` names the author of a NIP-22 comment's root, the uppercase twin of `p` as `E` and `A` are of `e` and `a`; the zap sender is read from the zap request, not from a `P` tag.
- Do not add a second name for a meaning a constant already names. A new constant sharing a letter needs a meaning unrelated to every other constant on that letter.
