# 138. A `payload` tag on an empty body is matched against the empty body's hash, and a wrong one is a `PayloadMismatch`

## Status

Accepted

## Context

Shared nostr-adrs ADR-0024 lets an empty body carry a `payload` tag only when it holds the SHA-256 of the empty string, and calls any other value a mismatch. `Nip98EventChecker` refused that other value as `PayloadTagWithoutBodyHash`, a case kept from when every `payload` tag on an empty body was refused. Once the empty body's hash was admitted, the case was reached only by a tag naming some other body's hash, which `PayloadMismatch` already reports for a non-empty body, so one fault had two cases and which one a server saw depended on the body's length. Its message, "Event contains payload tag but no request body hash was supplied for verification", described neither: a tag alone is not refused, and a caller who gave `Nip98Request::fromBodyHash` the empty body's hash supplied one.

## Decision

- `Nip98EventChecker` matches a `payload` tag against the request's body hash, and against the empty body's hash when the request has no body (`Nip98Request::fromBodyHash` reads the empty body's hash as no body). A tag naming any other hash is `PayloadMismatch`, whatever the body.
- A missing tag is `MissingPayloadTag` for a non-empty body and is accepted for an empty one.
- `Nip98ValidationFailure` has no `PayloadTagWithoutBodyHash` case.
- The gates and their order are unchanged (ADR-0110).

## Consequences

- A server switching on the failure reads one case for a tag naming the wrong body, whichever body was sent.
- `PayloadTagWithoutBodyHash` and its code `payload_tag_without_body_hash` are gone; a consumer that matched on them matches `PayloadMismatch`. No innis package did.
- @innis/nostr-core makes the same change, dropping `payload-unexpected`, under its ADR-0011.
- Shared decision: nostr-adrs ADR-0024.
