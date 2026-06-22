# 12. `Event` does not cache its computed id

## Status

Accepted

## Context

`Event::getId()` computes a SHA-256 over the serialised event, so memoising the result looks like an obvious optimisation.

## Decision

`Event` does not cache its computed id. `Event` is a `final readonly class`, so a lazy memoisation field is not available; `getId()` recomputes the SHA-256 only when the event is unsigned (a signed event already carries its id).

The hash is cheap, and dropping the class-level `readonly` to add a cache would cost more in immutability guarantees than it saves.

## Consequences

- `Event` keeps its class-level `readonly` immutability guarantee.
- The recompute only happens for unsigned events; a signed event carries its id already, so there is no repeated hashing on the common path.
- Do not drop `readonly` to add a memoisation field — the immutability guarantee outweighs a cheap hash.
