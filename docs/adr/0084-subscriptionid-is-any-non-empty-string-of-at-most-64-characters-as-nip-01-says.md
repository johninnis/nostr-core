# 84. `SubscriptionId` is any non-empty string of at most 64 characters, as NIP-01 says

## Status

Accepted

Supersedes ADR-0053, which restricted the id to printable ASCII. The rule is now the shared decision, nostr-adrs ADR-0005; this record holds what is particular to this package.

## Context

NIP-01: "`<subscription_id>` is an arbitrary, non-empty string of max length 64 chars." nostr-adrs ADR-0005 decides for every implementation that the length is counted in Unicode code points and that no character set is imposed.

ADR-0053 narrowed the id to `\x21`–`\x7E` as hardening, so a relay built on this library refused a `REQ` a conforming client may send. PHP's obvious measure, `strlen`, counts UTF-8 bytes and would refuse 64 accented or CJK characters.

## Decision

`SubscriptionId::tryFromString` implements nostr-adrs ADR-0005: it accepts any non-empty, valid UTF-8 string of at most 64 code points, measured with `mb_strlen`, and returns `null` for a non-string, the empty string, invalid UTF-8, or a longer string.

## Consequences

- A host that logs or renders a subscription id escapes it there.
- Tests pin the 64-character ceiling in code points, multi-byte and four-byte characters, and the refusal of invalid UTF-8.
- Do not count bytes with `strlen`, and do not narrow the character set.
- Shared decision: nostr-adrs ADR-0005.
