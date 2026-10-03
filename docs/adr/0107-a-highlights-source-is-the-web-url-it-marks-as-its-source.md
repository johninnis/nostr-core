# 107. A highlight's source is the web URL it marks as its source

## Status

Accepted

The rule is the shared decision, nostr-adrs ADR-0087; this record holds what is particular to this package.

## Context

NIP-84: "The source url MUST have the `source` attribute", and comment URLs "MUST have a `mention` attribute". nostr-adrs ADR-0087 decides for every implementation that the source is read only from `http`/`https` `r` tags — the `source`-marked ones when there is one, otherwise those not marked `mention` — as one claim, so an older unmarked highlight still names its page and a mentioned URL never does.

## Decision

`HighlightMetadata::fromTagCollection` implements nostr-adrs ADR-0087: it selects the candidate `r` tags by that rule and reads their URLs as one claim through `SoleTagValue::fromValues` (ADR-0092), the rule `TagCollection::getSoleValueByType` applies to every value of one tag name, so candidates that disagree leave the source absent.

## Consequences

- Do not take the first `r` tag as the source, and do not let a `mention` URL or a text `r` value stand in for a missing source.
- Shared decision: nostr-adrs ADR-0087.
