# 98. Domain services are `static` when pure, and injected behind an interface only when they need a collaborator

## Status

Accepted

Supersedes ADR-0008. The rule — the shape of a domain service follows whether it has a collaborator, not a wish for uniformity — is carried forward unchanged. What is revised is the example it rested on: ADR-0008's injected services were the ones taking a `Nip19CodecInterface`, and that interface, with the other interfaces over pure services (`MessageDeserialiserInterface`, `ContentReferenceExtractorInterface`, `ContentReferenceTagBuilderInterface`, `EventReferenceExtractorInterface`, `RelayHintExtractorInterface`), has been deleted. This record holds the complete current decision.

## Context

Some domain services are `static` (`Nip19Codec`, `ContentReferenceExtractor`, `ReplyChainAnalyser`, `TagReferenceExtractor`, `EmbeddedEventExtractor`); others are instances behind an interface (`EventValidator`, `NipComplianceValidator`, `ZapReceiptVerifier`). Two shapes for "domain logic" read like an inconsistency and invite unifying them — making everything `static`, or putting every service behind an injected interface.

The package tried the second for its pure services: an interface over the NIP-19 codec, injected into the extractors that call it. Each interface had one implementation and could have no other — a NIP-19 string decodes one way — so the seam bought a test double for a function with no behaviour to fake, and the injection pushed a constructor parameter into every caller.

## Decision

The split is by dependency.

- **A pure, dependency-free transformation is a `static` function with no interface.** Its output depends only on its arguments; it performs no I/O, reads no clock or RNG, holds no state, and has exactly one correct implementation. Other domain code calls it directly. A unit test feeds it real input and asserts the output.
- **A service that needs a collaborator is an instance with its collaborator injected, reached through an interface.** The domain validators and the zap receipt verifier need a `SignatureServiceInterface`, which the host wires to a native or pure-PHP backend; the collaborator is the seam, and the service's own interface lets a caller substitute it.
- **A pure service that takes configuration is an instance, and is reached through an interface when an application service is composed around it.** Its rules are pure, but a host-chosen setting (`Nip42EventChecker`'s timestamp tolerance) is construction state, so it cannot be `static`. The application service that adds the clock to it (`Nip42Validator`, ADR-0127) takes its interface, as `Nip98Validator` takes `Nip98EventCheckerInterface` (ADR-0110), so the application layer names no domain implementation and a host or test substitutes the checker with a different configuration or a fixed answer. A configured pure service that nothing composes stays a plain instance with no interface.
- **A capability whose implementation is external technology** (signing, ECDH, the NIP-04/NIP-44/NIP-49 ciphers, gift wrapping) is an interface in the domain implemented in Infrastructure (ADR-0100).

A pure `static` function is not the hidden global state that "interfaces over singletons" targets: it holds no state and no handle, so there is nothing to invert.

## Consequences

- The shape of a domain service tells the reader whether it has a collaborator: a `static` call has none; an injected interface has one.
- Do not add an interface over a pure service "for testability" or for symmetry, and do not make a collaborator-bearing service `static`, which would hard-wire the dependency a test replaces.
- `ClaimedOffsets` is not a service but a mutable set local to one extraction (ADR-0108).
