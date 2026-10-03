# 131. Event limits are host acceptance policy, injected into the event validator

## Status

Accepted

## Context

`EventValidator` refused an event whose content was longer than 65536 characters, which carried more than 5000 tags, or whose `created_at` was more than an hour ahead of or ten years behind the reference instant. The numbers were class constants and the window was `Timestamp::isReasonableAt()`. `NipComplianceValidator` applied the same window as part of what it called NIP-01 compliance.

NIP-01 sets none of these. It defines `created_at` as "unix timestamp in seconds" and the content as "arbitrary string", and places no bound on either or on the number of tags. The bounds a relay applies are its own, and NIP-11 is where it advertises them: `max_content_length`, `max_event_tags`, `created_at_lower_limit` and `created_at_upper_limit` in its `limitation` object. A relay advertising a `max_content_length` above 65536 could not honour it, because the library refused the event first, and a relay that wanted a tighter window could not have one. Calling the window NIP-01 compliance described a library policy as a protocol rule.

This is the reasoning of ADR-0102 and ADR-0125, applied to the event rather than the filter: a limit the protocol does not set belongs to the relay that applies it.

## Decision

- `EventLimits` is a value object holding the maximum content length in characters, the maximum tag count and a `CreatedAtWindow`. `CreatedAtWindow` holds how many seconds behind and ahead of a reference instant a `created_at` may be. Their defaults are the old constants: 65536 characters, 5000 tags, ten years (315360000 seconds) behind and one hour ahead.
- `EventValidator` takes an `EventLimits` as its third constructor argument, defaulting to `new EventLimits()`, and judges content length, tag count and `created_at` by it. A host passes the limits it advertises.
- `NipComplianceValidator` checks only what a NIP states. Its NIP-01 baseline is the signature; it does not judge `created_at`, and its methods take no reference instant.
- `Timestamp::isReasonableAt()` is removed. A caller wanting the default window outside the validator uses `CreatedAtWindow`, configured as it needs.

## Consequences

- A relay applies and advertises one set of numbers: the validator refuses exactly what the relay's NIP-11 document says it refuses.
- An event refused for its size, tag count or `created_at` is still an `InvalidEventException` from `validateEvent`, so a relay answers it `invalid:`, as NIP-01's own example answers a `created_at` "too far off from the current time".
- Breaking: `NipComplianceValidatorInterface` methods drop their `Timestamp $reference` argument, and `Timestamp::isReasonableAt()` is removed.
- Do not reintroduce limit constants in `EventValidator` or a window in `NipComplianceValidator`; a limit NIP-01 does not set is configured by the host.
