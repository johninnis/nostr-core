# 133. A seal carries no tags but expiration tags

## Status

Accepted

## Context

`GiftWrapper::unwrap` refused any seal (kind 13) that carried a tag, as `GiftWrapUnwrapFailure::SealMalformed`. NIP-59 says "Tags MUST always be empty in a `kind:13`", but it also says "When adding expiration tags to both `seal` and `gift wrap` layers, implementations SHOULD use independent random timestamps for each layer", and NIP-17 says of disappearing messages that the `expiration` tag "SHOULD be included on the `kind:13` seal as well, in case it leaks". A client following NIP-17 therefore writes an `expiration` on the seal, and refusing every tag refused its messages.

## Decision

- A seal is well formed when every tag it carries is an `expiration` tag whose value is a canonical NIP-40 timestamp: a non-negative decimal integer with no sign or leading zero, read by `Timestamp::tryFromDecimalString` (`DecimalIntegerParser`). No tags at all is still well formed.
- A seal may carry several `expiration` tags, and they need not agree. `expiration` is not a single-valued tag (nostr-adrs ADR-0014); what several of them mean is the expiry rule (nostr-adrs ADR-0011, this package's ADR-0071), which is a question for whoever displays the message, not for opening the wrap.
- Opening a wrap does not judge whether the seal's expiration has passed: an expired seal still opens, since expiry governs display, not unwrap.
- Elements after an `expiration` tag's value are not checked.
- Any other tag on a seal, and an `expiration` with no value or a value that is not a canonical timestamp, is `GiftWrapUnwrapFailure::SealMalformed`, as a tagged seal was before.
- `GiftWrapper` writes no `expiration` on a gift wrap, so it writes none on the seal either. If it ever writes one on the wrap, it writes one on the seal too, as NIP-17 asks.

## Consequences

- A gift wrap from a client that marks its seal for disappearing messages opens.
- A seal is still refused for carrying anything that could name its recipient or otherwise leak metadata.
- Do not accept other tags on a seal, and do not refuse a wrap because its seal has expired.
- Shared decisions: nostr-adrs ADR-0073, ADR-0011 and ADR-0014.
