# 99. `EventValidator` and `NipComplianceValidator` keep separate signature gates over one shared predicate

## Status

Accepted

Supersedes ADR-0017. Its decision — each validator keeps its own thin signature gate, deferring to one shared predicate — is carried forward unchanged. What is revised is the timestamp gate: ADR-0017 had both validators call `Timestamp::isReasonable()`, which read the wall clock. The `created_at` window is now host acceptance policy that only `EventValidator` applies, against a reference instant its caller supplies (ADR-0080, ADR-0131); `NipComplianceValidator` no longer judges `created_at`, because NIP-01 sets no window, and `Timestamp::isReasonable()` is removed. This record holds the complete current decision.

## Context

Two domain validators check an event, and both contain a signature-validity gate:

- `EventValidator` decides whether an event is acceptable to accept or relay: `created_at`, content length and tag count within the host's `EventLimits` (ADR-0131), `d` tags in agreement, signature valid. For a deletion it additionally delegates to `NipComplianceValidator` for the NIP-09 shape.
- `NipComplianceValidator` decides whether an event complies with a specific NIP: each method asserts that NIP's shape and ends by checking the NIP-01 baseline, a valid signature.

Side by side, a guard wrapping `Event::verify()` appears in both classes, which reads like duplication and invites consolidating it onto one owner.

## Decision

Keep the gates separate. Each validator is a distinct command with its own scope, and both already defer the decision to one shared implementation.

- **The logic is in one place.** Signature validity is decided once, in `Event::verify()`. What repeats is a guard that turns the boolean into that validator's `InvalidEventException`: a translation step, not logic.
- **A shared guard would add indirection, not remove duplication.** It would insert a pass-through over a callee that is already the single source of truth.
- **Merging the validators would change observable behaviour**: a different set and order of checks, and a general structural gate carrying NIP-01 baseline meaning.
- `EventValidator` delegating to `NipComplianceValidator` for the NIP-09 deletion rules is a separate matter and stays, but it calls only `validateNip09Shape()`: the kind and a readable target. `validateNip09Compliance()` is that shape plus the NIP-01 baseline, and the baseline repeats checks `EventValidator` has already made, so calling it would verify every deletion's signature twice. A deletion's signature is verified once.
- The `created_at` window is `EventValidator`'s alone, read from the `CreatedAtWindow` its `EventLimits` carries. There is no clock-reading `isReasonable()`: a domain validator is a pure function of its inputs, and a caller holding a clock passes its instant (ADR-0080).

## Consequences

- Do not inject one validator into the other to "dedupe" the signature gate, and do not extract a shared signature guard.
- A new validator gets its own gate that defers to the same predicate.
- Do not switch `EventValidator` back to `validateNip09Compliance()`: a test counts one signature verification per deletion.
- Breaking: `Timestamp::isReasonable()` is removed, and so is the `isReasonableAt()` that replaced it; `new CreatedAtWindow()->admits($createdAt, Timestamp::now())` is the default window at a caller's edge. `NipComplianceValidatorInterface` methods no longer take an instant. Tests pin the window at fixed instants.
