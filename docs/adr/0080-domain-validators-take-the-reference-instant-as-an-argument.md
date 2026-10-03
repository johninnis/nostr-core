# 80. Domain validators take the reference instant as an argument

## Status

Accepted

## Context

`EventValidator` and `NipComplianceValidator` refused an event whose `created_at` was unreasonable — more than an hour ahead or ten years behind. They decided "ahead of what" by calling `Timestamp::isReasonable()`, which reads the wall clock. The rule is a time-window decision, exactly the case ADR-0005 says must not read the clock directly, and a test could only exercise the boundary by constructing timestamps relative to whenever it ran.

The application-layer validators with the same need (`Nip98Validator`, `Nip42Validator`) inject a `ClockInterface`. These two could not: they are domain services, and the clock port lives in the application layer, which the domain may not depend on.

## Decision

`EventValidatorInterface::validateEvent` / `isEventValid` take a `Timestamp $reference` and judge `created_at` against it through the host's `CreatedAtWindow` (ADR-0131). The caller supplies the instant — from its own clock port, or `Timestamp::now()` at its edge.

`NipComplianceValidator` takes no instant: NIP-01 sets no window on `created_at`, so NIP compliance does not judge it (ADR-0131).

## Consequences

- The domain validators are pure functions of their inputs, and the timestamp window is tested at fixed instants.
- Every caller passes one more argument. A relay passes the instant from the clock it already holds, so validation and every other time decision in a request agree on "now".
- Domain services take the instant; application services inject the clock. The asymmetry follows the layer, not the rule.
