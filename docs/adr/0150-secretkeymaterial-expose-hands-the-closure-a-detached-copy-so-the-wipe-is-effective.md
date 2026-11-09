# 150. `SecretKeyMaterial::expose` hands the closure a detached copy, so the wipe is effective

## Status

Accepted

Supersedes ADR-0028. Carried forward unchanged: `expose()` hands the closure a buffer detached by XORing the stored bytes against zeros, and wipes that buffer in a `finally`. Revised: the account of why, which ADR-0028 gave wrongly. This record holds the complete current decision.

## Context

`expose(Closure)` lets a caller read the raw secret bytes for the duration of a closure and then wipes them, so the key is reusable across calls but the exposed bytes do not linger. The obvious implementation aliases the stored bytes and wipes that variable in a `finally`.

That wipes nothing. PHP strings are copy-on-write, so the variable and the stored bytes share one buffer, and `sodium_memzero` zeroes a string only when its refcount is exactly one: with more references it leaves the bytes untouched and only sets the variable to null. ADR-0028 described this as the string being separated and a throwaway copy wiped. The outcome is the same, but PHP's source shows no copy is made.

## Decision

`expose()` materialises a separate buffer for the closure by XORing the stored bytes against zeros, which allocates a fresh string rather than a copy-on-write alias. The closure reads that buffer; the `finally`'s `sodium_memzero` then wipes it in place, because once the closure has returned and holds no escaping reference the buffer has one reference. The stored bytes are not touched and stay available for repeated exposure, until `zero()` (ADR-0147). `Nip49Cipher` detaches a revealed password the same way.

## Consequences

- The bytes handed to a non-escaping closure are zeroed after use. A closure that keeps a reference, such as by returning its argument unchanged or capturing it in an escaping variable, leaves the refcount above one, so the wipe does not happen; that closure owns what it kept.
- The XOR is load-bearing. Reducing it to the stored bytes reads like removing a no-op but silently defeats the wipe. A one-line fence at the call site points here, and no behavioural test can see the change, since the stored secret survives either way.
- This remains best-effort, not guarded secure memory: GMP scalars and other transient derivations stay unwipeable.
