# 12. `Event` does not cache its computed id

## Status

Accepted

## Context

`Event::getId()` computes a SHA-256 over the serialised event, so memoising the result looks like an obvious optimisation: compute once, store it, return the stored value thereafter.

## Decision

`Event` does not cache its computed id.

The choice is immutability over a micro-optimisation, not a limitation forced by the language. A cache is a write-after-construction. Holding the memoised id means dropping the class-level `readonly` (or smuggling in a mutable field), which forfeits the guarantee that an `Event` value never changes once constructed — the property the whole value-object design rests on. That guarantee is load-bearing beyond aesthetics: it is what lets the codebase treat two `Event`s with the same data as interchangeable, copy or share one freely, and reason about equality without asking whether a lazily-populated field has been touched. A mutable cache field reintroduces exactly those questions to save one hash.

And the hash it would save is small and bounded. `getId()` recomputes only for an *unsigned* event; a signed event already carries its id and returns it with no hashing. The common path — events arriving signed off the wire — never recomputes at all, so a cache would optimise only repeated id reads of an event still being assembled, against a SHA-256 that is cheap to begin with. The optimisation targets the rare path and pays for it in the guarantee that protects every path.

## Consequences

- `Event` keeps its class-level `readonly` immutability guarantee: the value cannot mutate after construction, and there is no lazily-populated field to weigh when reasoning about equality, copying, or sharing.
- A signed event carries its id and never rehashes; only an unsigned event recomputes, and only per read.
- Do not drop `readonly` to add a memoisation field — the immutability guarantee outweighs a cheap hash on the rare path.
