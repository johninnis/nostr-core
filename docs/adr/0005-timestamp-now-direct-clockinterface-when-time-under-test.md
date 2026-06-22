# 5. `Timestamp::now()` reads the clock directly; `ClockInterface` is injected only where elapsed time is under test

## Status

Accepted

## Context

Reading wall-clock time is a side effect, which the functional rules push to the edges behind a port. Taken literally that would inject a `ClockInterface` everywhere an instant is needed — including value-object construction — which adds wiring and a seam to code that has no time-dependent behaviour to test.

The library reads wall-clock time in exactly one place — `Timestamp::now()` — and everything that needs the current instant goes through it, so there is a single obvious source of "now" rather than scattered `time()` calls. `Timestamp` is a value object and `now()` is simply its named constructor for the present instant. The comparisons it backs are available in a pure form that takes the reference instant as an argument (`isReasonableAt(self $reference)`, `isAfter`, `isBefore`, `differenceInSeconds`), so the time-dependent logic itself stays a pure function of its inputs.

## Decision

Read `Timestamp::now()` directly in construction and glue code. Inject a `ClockInterface` port wherever elapsed time is the thing being decided, so a test can freeze time and exercise the boundaries deterministically. `Nip98Validator` is the example: its whole job is to accept or reject an event against a timestamp-tolerance window, so it takes a `ClockInterface`.

## Consequences

- There is one source of "now" (`Timestamp::now()`); the comparison logic is pure and tested by passing the reference instant as an argument.
- Time-window behaviour (NIP-98 tolerance) is tested deterministically by injecting a frozen clock.
- Do not thread a `ClockInterface` through value-object construction — it buys no test value where elapsed time is not the behaviour under test.
