# 8. Domain services are `static` when pure, injected interfaces when they have a collaborator

## Status

Accepted

## Context

Some domain services are `static` functions (`TagReferenceExtractor`, `ReplyChainAnalyser`, `ReplyTagBuilder`, `EmbeddedEventExtractor`); others are injected interfaces (the ones taking a `Nip19CodecInterface`). Two units that are both "domain logic" carrying different shapes reads like an inconsistent approach to one kind of class, and invites unifying them — making everything `static`, or putting every service behind an injected interface.

## Decision

The split is by dependency, and it follows directly from what makes a unit testable and substitutable. A seam — an interface plus constructor injection — earns its place only where there is a collaborator to substitute; adding one elsewhere is cost without value.

- A **pure, dependency-free transformation** is a `static` function. Its output depends only on its arguments; it performs no I/O, reads no clock or RNG, holds no state, and has exactly one correct implementation. There is nothing to substitute and nothing to mock, so a unit test feeds it real input and asserts the output. Wrapping it in an interface would add a seam with no second implementation behind it and invite a pointless test double for a function that has no behaviour to fake.
- A service that **needs a collaborator** (the `Nip19CodecInterface` cases) is an injected interface. The collaborator *is* the seam: injection lets a test supply a double and lets the wiring choose the implementation. That is the case inversion of control exists for.

A pure `static` function is not the hidden global state that the "interfaces over singletons" rule targets. That rule exists to break implicit coupling to mutable shared state and untestable singletons; a pure function has neither — no state, no I/O, no shared mutable handle — so there is nothing to invert. The shape therefore tracks the only thing that matters for testing: whether the unit has a dependency a test must replace.

## Consequences

- Pure transformations stay free of wiring and seams that buy no test value; services with real dependencies stay testable and swappable through injection.
- The shape of a domain service tells the reader whether it has a collaborator: a `static` call has none; an injected interface has one.
- Do not "make it consistent" by forcing all domain services into one shape. Making the pure functions injectable adds seams and mocks for nothing; making the collaborator-bearing ones static would hard-wire a dependency a test needs to replace. The dependency, not uniformity, decides.
