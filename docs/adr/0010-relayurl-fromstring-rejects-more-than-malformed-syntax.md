# 10. `RelayUrl::fromString` rejects more than malformed syntax

## Status

Accepted

## Context

A URL parser is expected to reject only syntactically malformed input. `RelayUrl::fromString` returns `null` for more than that, which reads like over-strict parsing or a set of accidental rejections.

## Decision

`RelayUrl::fromString` also returns `null` for a path that repeats the host, a doubled slash, a percent-encoded space, and an out-of-range port — normalisation strictness aimed at the duplicated-authority and mangled URLs that show up in real relay lists.

These are deliberate, tested rejections, not oversights.

## Consequences

- Mangled and duplicated-authority relay URLs from real relay lists are rejected at the boundary rather than flowing through as distinct-but-equivalent values.
- Rejection is a returned `null` (an anticipated outcome per ADR-0001), not a throw.
- Tests pin the specific rejections. Do not "loosen" the parser to accept these as valid.
