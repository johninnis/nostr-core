# 132. An `r` tag's marker is read, write or both

## Status

Accepted

## Context

`RelayReference` held an `r` tag's marker as `?string`: whatever the author wrote, or `null` when the tag had none. Every consumer then re-derived the same three-way meaning. hubstr-relay kept its own `RelayMarker` enum to do it, and @innis/nostr-core reads the marker as `"read" | "write" | "both"`.

NIP-65 gives each `r` tag "an optional `read` or `write` marker", and "If the marker is omitted, the relay is both **read** and **write**." It says nothing of any other value.

## Decision

- `RelayMarker` (`Domain\Enum`) has the cases `Read`, `Write` and `Both`, backed by `read`, `write` and `both`.
- `RelayMarker::fromTagValue(?string)` reads `read` and `write` exactly, and answers `Both` for an absent marker, as NIP-65 says, and for any value NIP-65 does not define, which keeps the relay rather than dropping it. This matches @innis/nostr-core.
- `RelayReference` takes a `RelayMarker` and answers it from `getMarker()`; `TagReferenceExtractor` builds it with `fromTagValue`. Its array form writes the marker under `marker`.

## Consequences

- A consumer branches on three cases instead of re-reading a string, and hubstr-relay drops its own enum for this one.
- Breaking: `RelayReference::getMode(): ?string` is now `getMarker(): RelayMarker`, the constructor's second argument is required, and the array key `mode` is now `marker`.
- Do not add a case for a marker NIP-65 does not define.
- Shared decision: nostr-adrs ADR-0108.
