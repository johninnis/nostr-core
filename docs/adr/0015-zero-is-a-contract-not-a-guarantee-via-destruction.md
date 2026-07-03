# 15. `zero()` is a contract, not a guarantee via destruction

## Status

Accepted

## Context

`PrivateKey` and `ConversationKey` hold their raw bytes inside a `SecretKeyMaterial`, whose destructor calls `zero()`. The risk this guards is secret key material persisting in process memory after it is no longer needed: a later disclosure of that memory — a core dump, a crash reporter, swap, an out-of-bounds over-read, `/proc` access — recovers a key that was never wiped. The wipe itself (`sodium_memzero`) is not in question; *when it is guaranteed to run* is.

It is tempting to treat the destructor as that guarantee — to assume the key clears itself the moment it goes out of scope. PHP makes that assumption false.

## Decision

`zero()` is a contract a caller invokes explicitly. The destructor calls it too, but only as defence-in-depth — never as the primary wipe mechanism.

PHP frees an object on **refcount-zero**, and a secret key is exactly the kind of object whose refcount does not reach zero when the surrounding scope ends:

- captured in a **closure** bound into a long-lived structure — the event loop of a session-scoped bunker signer holds the key for the life of the loop, not the request;
- held in **static or singleton state**, which lives for the whole process;
- pinned in an **exception's stack trace** — every frame from the throw point retains its arguments and locals, so a key passed anywhere up that stack stays reachable for as long as the caught exception is held;
- caught in a **reference cycle**, freed only by the periodic cycle collector, at a nondeterministic later time, not promptly on scope exit.

In each case the destructor runs late or never, so a design that relies on it leaves the key in memory for an unbounded window. The guarantee therefore has to be a caller contract: an application with a bounded-lifetime requirement calls `$privateKey->zero()` explicitly at the end of the scope that owns the key (end of session, for the bunker signer). The destructor remains cleanup-of-last-resort for the paths no one remembered to close.

After `zero()` the object fails closed: the bytes are `sodium_memzero`d and the field nulled, and any subsequent operation throws `SecretKeyMaterialZeroedException` rather than silently signing with a wiped key (`isZeroed()` is the non-throwing query). The read path is bounded the same way: `expose(Closure)` hands the closure a separated copy of the bytes and `sodium_memzero`s that copy in a `finally`, so raw key material never outlives the callback even while the key is live.

This is also why `SecretKeyMaterial` is deliberately a plain `final class`, not a `final readonly` value object: `zero()` must null the bytes field, which a `readonly` class forbids. It is the documented memory-zeroing reason for relaxing the immutability rule — this record is that documentation.

`sodium_memzero` closes the *live-memory* window; it does nothing for the *trace-text* window. The stack-trace-pinning risk named above has a second edge: the ingress points where raw secret material still arrives as an ordinary `string` argument — `SecretKeyMaterial::__construct`/`tryFromHex`/`tryFromBytes`, and the `PrivateKey` and `ConversationKey` factories (`tryFromHex`/`tryFromBytes`/`tryFromBech32`) that forward to them. These validate and throw (`InvalidArgumentException` on a wrong length) *before* the value is wrapped, so a raw key or hex string can be pinned verbatim in the throwing frame's arguments, and surfaced again by any `debug_backtrace()`, `Throwable::getTraceAsString()`, or trace-logging error handler. Those parameters therefore carry `#[SensitiveParameter]` (imported, as with `#[Override]` and the other global symbols), which replaces the argument with a `SensitiveParameterValue` placeholder in every generated trace. This is complementary to `zero()`, not a substitute: the attribute redacts traces and does not touch memory, while `zero()`/`expose()` wipe memory and do not touch traces. Password providers avoid this window differently — a secret is handed in as a `Closure` that returns the value, never as a bare `string` argument, so it is never a trace-pinnable parameter in the first place (`Nip49Cipher`).

## Consequences

- A caller with a bounded-lifetime requirement gets a deterministic wipe only by calling `zero()` explicitly; the destructor cannot promise timeliness.
- A zeroed key is inert and fails loudly: operations throw `SecretKeyMaterialZeroedException`, and reads go through the self-zeroing `expose()` copy.
- `SecretKeyMaterial` stays non-`readonly` on purpose; do not "tidy" it into a `readonly` value object — that removes the ability to wipe.
- Do not remove the explicit `zero()` calls in favour of "the destructor handles it" — for keys captured in closures, statics, trace frames, or cycles it runs late or never.
- Keep `#[SensitiveParameter]` on every `string` parameter that carries raw secret bytes or hex; it is the only thing keeping a wrong-length key out of a thrown trace, and `sodium_memzero` does not cover that window. A new secret-accepting `string` parameter must be marked, or take a `Closure` instead.
