# 147. `zero()` is a contract, not a guarantee via destruction

## Status

Accepted

Supersedes ADR-0015. Carried forward unchanged: `zero()` is a contract a caller invokes, the destructor is defence in depth only, and a zeroed key fails closed. Revised: how the bytes are held, which ADR-0148 now decides, so `SecretKeyMaterial` is no longer a non-`readonly` class; and marking secret parameters, which ADR-0149 now decides. This record holds the complete current decision.

## Context

`PrivateKey` and `ConversationKey` hold their raw bytes inside a `SecretKeyMaterial`, whose destructor calls `zero()`. The risk this guards is secret key material persisting in process memory after it is no longer needed: a later disclosure of that memory recovers a key that was never wiped. It is tempting to treat the destructor as the guarantee, but PHP frees an object on refcount zero, and a secret key is exactly the kind of object whose refcount does not reach zero when its scope ends: it is captured in a long-lived closure, held in static state, pinned in an exception's stack trace, or caught in a reference cycle.

## Decision

`zero()` is a contract a caller invokes explicitly. The destructor calls it too, but only as defence in depth. An application with a bounded-lifetime requirement, such as a session-scoped bunker signer, calls `$privateKey->zero()` at the end of the scope that owns the key.

After `zero()` the object fails closed: any subsequent `expose()` throws `SecretKeyMaterialZeroedException` rather than silently signing with a wiped key, and `isZeroed()` is the non-throwing query.

## Consequences

- A caller with a bounded-lifetime requirement gets a deterministic wipe only by calling `zero()`; the destructor cannot promise timeliness.
- Do not remove the explicit `zero()` calls in favour of the destructor.
