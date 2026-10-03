# 110. NIP-98 is checked by a domain checker at a given instant, and the validator adds the clock and the replay guard

## Status

Accepted

Supersedes ADR-0038. Its decision that the timestamp tolerance is configuration with a 60-second default, not a constant, is carried forward. What is revised is where it lives: ADR-0038 kept it as a fenced fourth constructor argument of `Nip98Validator`, and this record holds the complete current decision.

## Context

`Nip98Validator` took a signature service, a replay guard, a clock and the timestamp tolerance. ADR-0038 recorded the tolerance as a configuration scalar that could neither be bundled nor split off. A fourth constructor argument usually means a class holds two jobs, and a fence that exempts it hides which two.

The validator does two things. It judges an event against the request it claims to authorise — kind, timestamp window, expiry, `u`, `method` and `payload` binding, signature — which is a pure function of the event, the request and an instant (ADR-0080). And it reads the clock and records the event once in a replay store, which is I/O. The tolerance belongs to the first; the replay window, the 2 × tolerance + 1 whole seconds the inclusive timestamp window spans, is derived from it.

## Decision

- `Nip98EventChecker` (`Domain\Service`) takes the signature service and the timestamp tolerance (default 60 seconds), and `check(event, request, at)` returns the first `Nip98ValidationFailure` or `null`. `getReplayWindowSeconds()` is 2 × tolerance + 1: the timestamp window is inclusive at both ends, so an event is accepted at every whole second from `at - tolerance` to `at + tolerance`, and the replay record must outlive all of them (60 seconds gives 121, zero gives 1). A negative tolerance is a configuration fault and the constructor throws `InvalidArgumentException`; zero is a window of exactly the given instant.
- `Nip98EventChecker` implements `Nip98EventCheckerInterface`, beside it in `Domain\Service`. `Nip98Validator` (`Application\Service`) takes that interface, the replay guard and the clock, so a test or a host substitutes the checker without the validator naming its implementation (ADR-0098). It checks the event at the clock's instant and, when it passes, records its id once for the checker's replay window.
- The gates and their order are unchanged.

## Consequences

- A host builds `new Nip98Validator(new Nip98EventChecker($signer, $tolerance), $replayGuard, $clock)`; a custom window is passed to the checker.
- The checker can be used at a fixed instant without a clock, as the shared NIP-98 check in @innis/nostr-core is (`checkNip98Event`).
- Neither constructor carries a fence.
- Shared decisions: nostr-adrs ADR-0097 and ADR-0070.
