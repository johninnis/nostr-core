# 71. An event is expired once any expiry it states has passed

## Status

Accepted

## Context

NIP-40 gives an event one `expiration` tag holding one timestamp. Nothing stops a client sending two, and nothing in the protocol says what a relay should then do.

Reading the first tag and ignoring the rest is the obvious answer, and it is the one this library gave. It has a flaw that only shows up once something other than this library looks at the same event. "First" is a property of the order the tags happened to arrive in, and a store that indexes tags for querying does not keep that order: a relay asking its database "which events have expired" gets an answer drawn from whichever tag the index reached, while the same relay asking this library gets the answer from the first. The two disagree, and because one of them decides what is served and the other decides what is deleted, the disagreement is how an event gets destroyed while it is still being served.

The package already knows this shape. `ZapReceiptVerifier` refuses a second `description` tag rather than picking one, with a fence saying that resolving by position would let a receipt carry one request for that verifier and another for whatever reads it next; `Nip98Validator` refuses duplicate `u`, `method` and `payload` tags for the same reason. Both are about a value that must mean one thing to every reader.

Expiry cannot simply be refused the same way, because a predicate has to answer true or false. What it can do is answer without reference to order.

## Decision

`isExpiredAt()` considers every `expiration` tag on the event and returns true when any of them names an instant that has passed. A value that is not a plain decimal timestamp is ignored rather than treated as an expiry, as before.

An event that states one expiry is unaffected, which is every conforming event. An event that states several is expired by the earliest of them.

## Consequences

- The answer no longer depends on tag order, so a store indexing tags, a client reading an event and this library all reach the same conclusion. That is the property the decision exists for.
- Where two expiries disagree, the earlier one wins. An `expiration` tag is a request to stop serving, and a relay should not keep serving on the strength of a second, contradictory claim by the same author.
- A relay may therefore delete such an event, because what it deletes is exactly what it has stopped serving. The alternative rule, expiring only once every stated expiry has passed, would have kept the two sets apart and left the relay serving an event it had been asked to expire.
- Do not restore `getFirstValueByType` here. It reads as the simpler code and it is the bug.
