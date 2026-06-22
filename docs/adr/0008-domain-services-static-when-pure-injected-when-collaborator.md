# 8. Domain services are `static` when pure, injected interfaces when they have a collaborator

## Status

Accepted

## Context

Some domain services are `static` functions and others are injected interfaces. At a glance this reads like an inconsistent approach to the same kind of class.

## Decision

The split is by dependency, not by accident.

- A pure, dependency-free transformation (`TagReferenceExtractor`, `ReplyChainAnalyser`, `ReplyTagBuilder`, `EmbeddedEventExtractor`) is a `static` function: there is nothing to inject and nothing to mock, so a unit test feeds it real input and asserts the output.
- A service that needs a collaborator — the ones taking a `Nip19CodecInterface` — is an injected interface so the collaborator can be a test double.

## Consequences

- Pure transformations stay free of wiring and seams that buy nothing; services with real dependencies stay testable through injection.
- Making the pure functions injectable too would add wiring and a seam for no test value.
- Do not "make it consistent" by forcing all domain services into one shape — the dependency, not uniformity, decides.
