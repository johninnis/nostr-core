# 15. `zero()` is a contract, not a guarantee via destruction

## Status

Accepted

## Context

`PrivateKey` and `ConversationKey` hold their raw bytes inside a `SecretKeyMaterial` value object, whose destructor calls `zero()`. It is tempting to treat the destructor as the wipe mechanism and assume secret material clears itself when a key goes out of scope.

## Decision

`zero()` is a contract a caller invokes explicitly, not a guarantee delivered via destruction. The `SecretKeyMaterial` destructor does call `zero()` as defence-in-depth, but PHP's garbage collector runs on refcount-zero, which may never happen for keys captured in long-lived closures, static state, exception trace frames, or cyclic references.

Applications that require bounded key-material lifetimes — session-scoped bunker signers, for example — must call `$privateKey->zero()` explicitly at the end of the session. Treat the destructor as cleanup-of-last-resort, not as the primary wipe mechanism. After `zero()`, any subsequent operation on that key throws `SecretKeyMaterialZeroedException`; infrastructure that needs raw bytes uses the bounded `expose` callback, which passes a CoW-separated copy to the closure and `sodium_memzero`s that copy before returning.

## Consequences

- Callers with a bounded-lifetime requirement get a deterministic wipe only by calling `zero()` explicitly.
- The destructor still provides best-effort cleanup, but is not relied on for timeliness.
- Do not remove the explicit `zero()` calls in favour of "the destructor handles it" — for long-lived references it may never run.
