# 3. Anticipated outcomes are returned; faults are thrown

## Status

Accepted

## Context

Failure splits into two kinds, and they must be modelled differently. PHP has no checked exceptions, so a `throw` is invisible to PHPStan: a caller can silently forget to handle it. A nullable or `*Failure` return, by contrast, makes "you didn't handle the failure" a level-9 analyser error. This is the most load-bearing analyser decision in the library.

## Decision

- **Anticipated domain outcomes** — a well-formed operation whose answer is "no" (unauthorised, too large, not found, malformed wire input, policy rejection, rate limited) — are **returned** as a typed value: `?T` for a single failure mode, or a sealed family of `*Failure` value objects (or a backed enum when the failure carries no data) for several. They are never thrown.
- **A parser of untrusted input** (`Event::fromArray` / `fromJson`, `Filter::fromArray`, `JsonMessageDeserialiser`, `Nip98Validator`) puts its failure in the return type — `?T`, or a sealed `*Failure` value — and must not throw.
- **Faults** — broken invariants, programmer errors, infrastructure failures, and mid-operation crypto or serialisation errors — are **thrown**. `InvalidArgumentException` (native) covers argument validation of trusted internal values.
- **Two cases legitimately throw** rather than return: a validation command returning `void` (contract is "succeed or throw", paired with a boolean query when a non-throwing check is useful), and a multi-step crypto/serialisation operation whose mid-operation failure is a fault of that operation.

## Consequences

- PHPStan level 9 forces every caller to handle the failure branch of a returned outcome.
- Exceptions in this library signify genuine faults only; a `catch` is never load-bearing control flow for an anticipated "no".
- Do not "simplify" a `?T` / `*Failure` return into a throw — it removes the analyser guarantee that callers handle it.
