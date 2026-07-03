# 17. `EventValidator` and `NipComplianceValidator` keep separate signature and timestamp gates

## Status

Accepted

## Context

Two domain validators check an event, and both contain a signature-validity gate and a timestamp-reasonableness check:

- `EventValidator` decides whether an event is structurally acceptable to accept or relay: timestamp reasonable, content within length, tag count within bounds, signature valid. For a deletion it additionally delegates to `NipComplianceValidator` for the NIP-09 rules.
- `NipComplianceValidator` decides whether an event complies with a specific NIP: each method asserts that NIP's shape and ends by checking the NIP-01 baseline — timestamp reasonable, signature valid.

Side by side, a private `validateSignature()` wrapping `Event::verify()` and a `Timestamp::isReasonable()` check appear in both classes, which reads like duplication and invites consolidating them onto one owner — injecting one validator into the other to perform the signature check, or extracting a shared `assertSignatureValid()` helper.

## Decision

Keep the two gates separate. Each validator is a distinct command with its own scope, and both already defer the actual decision to a single shared implementation.

- **The logic is already in one place.** Signature validity is decided once, in `Event::verify()`; timestamp reasonableness once, in `Timestamp::isReasonable()`. Each validator calls these; neither re-implements them. What repeats is a two-line guard that turns the returned boolean into that validator's `InvalidEventException` — a translation step, not logic. Both guards currently raise the same message (`"Event signature is invalid"`), but the wording is a property of each validator's own contract, free to diverge, not a shared constant to factor out.
- **A shared guard would not remove duplication, only add indirection.** The repeated lines wrap a callee (`Event::verify`) that is already the single source of truth; factoring them into a helper inserts a pass-through over that callee without consolidating any real logic.
- **Merging the validators would change observable behaviour.** Routing `EventValidator`'s signature check through `NipComplianceValidator` would couple a general structural check to NIP-compliance semantics and alter what callers see: a different set and order of checks, and a general structural gate that now carries NIP-01 baseline meaning.
- `EventValidator` delegating to `NipComplianceValidator` for NIP-09 deletion rules is a separate matter and stays — that is one validator using another for a genuinely different rule set, not the signature gate.

## Consequences

- Signature validity has one home (`Event::verify`) and timestamp reasonableness one home (`Timestamp::isReasonable`); the two validators each wrap them with a thin guard scoped to their own contract.
- Do not inject one validator into the other to "dedupe" the signature gate, and do not extract a shared signature/timestamp guard — it wraps an already-shared callee and would couple two validators with different scopes.
- A new validator gets its own gate that defers to the same shared implementations, rather than being folded into an existing validator.
