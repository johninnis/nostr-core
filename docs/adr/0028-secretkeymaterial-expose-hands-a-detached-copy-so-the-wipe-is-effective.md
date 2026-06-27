# 28. `SecretKeyMaterial::expose` hands the closure a detached copy so the wipe is effective

## Status

Accepted

## Context

`expose(Closure)` lets a caller read the raw secret bytes for the duration of a closure and then wipes them, so the key is reusable across calls but the exposed bytes do not linger. The obvious implementation aliases the stored field and wipes it in a `finally`:

```php
$exposed = $this->bytes;
try { return $fn($exposed); } finally { sodium_memzero($exposed); }
```

That looks like it scrubs the exposed bytes. It does not. PHP strings are copy-on-write, so `$exposed = $this->bytes` shares one buffer (it does not copy), and `sodium_memzero()` takes its argument by reference: passing a string whose refcount is greater than one by reference separates it first, allocating a fresh buffer for the call. The wipe therefore lands on a freshly-detached throwaway, while the buffer the closure actually read is left intact. Empirically: after `expose()` returns, the stored secret is unchanged and a closure that captured the argument still holds the real bytes. The per-call wipe is a no-op.

The stored secret surviving is *required* — the key must be reusable until `zero()`. The defect is only that the wipe of the per-call view never happens.

## Decision

`expose()` materialises a genuinely separate buffer for the closure by XORing the stored bytes against zeros — `$this->bytes ^ str_repeat("\0", self::BYTE_LENGTH)` — a bitwise identity the language guarantees allocates a fresh string rather than a copy-on-write alias. The closure reads that buffer; the `finally`'s `sodium_memzero` then wipes it in place, because once the closure has returned and holds no escaping reference the buffer is the sole owner. The stored secret is never touched here and stays available for repeated exposure; it is wiped only by `zero()` / the destructor.

## Consequences

- The bytes handed to a non-escaping closure are actually zeroed after use, which is the bounded-exposure guarantee the method exists to give. A closure that copies the bytes into an escaping variable still owns wiping that copy — `expose()` cannot reach inside it.
- The XOR is load-bearing. Reducing `$this->bytes ^ str_repeat("\0", …)` to `$this->bytes` reads like removing a no-op but silently reinstates the copy-on-write alias and defeats the wipe. A one-line fence at the call site points here, and the change is invisible to any behavioural test (the stored secret survives either way), so the record and the fence are what protect it.
- This remains plain-string best-effort, not guarded secure memory: GMP scalars and other transient derivations elsewhere stay unwipeable, and a key captured in a closure, static, or trace frame still outlives the call. The deterministic wipe of the persistent secret remains the caller's `zero()` contract (see ADR-0015); this record only makes the per-call view's wipe real instead of theatrical.
