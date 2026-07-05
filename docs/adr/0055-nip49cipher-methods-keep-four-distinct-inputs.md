# 55. `Nip49Cipher::encrypt` and its key-derivation step keep four distinct inputs

## Status

Accepted

## Context

Two units in the NIP-49 cipher take four arguments, past the point where an argument count is a design signal to split the unit or to group cohesive arguments into a value object.

- `Nip49Cipher::encrypt(PrivateKey $key, Closure $password, int $logN, KeySecurityByte $keySecurity)` — the private key to wrap, the password source, the scrypt work factor, and the key-security byte.
- The internal derive-key-and-run step takes the password source, the scrypt salt, the scrypt cost, and the consuming closure.

Read against the "more than three arguments is a design signal" guideline, both look like units that have taken on too much and should be decomposed or given a parameter object.

## Decision

Both signatures are accepted as they stand; neither is split and neither folds its arguments into a value object.

The four inputs to `encrypt` are genuinely distinct, independently-sourced values, not a cohesive group. The key is the secret being wrapped. The password arrives as a `Closure` deliberately (so a secret is never a trace-pinnable bare `string` argument), from a different origin than the key. `logN` is a caller-tuned cost knob with its own validity floor. The key-security byte is a NIP-49 header field the caller sets independently, authenticated as associated data. They share no cohesion that a `Ncryptsec parameters` object would express honestly; bundling them would only dodge the count while hiding four separate decisions behind one type. Splitting `encrypt` would fracture a single, atomic wrap operation across classes.

The same holds for the internal derive step: password, salt, cost, and the closure that consumes the derived key are four independent inputs to one indivisible KDF-then-use operation, not a group with a natural name.

## Consequences

- The two four-argument signatures stand, and this record is the reason, so a reviewer applying the argument-count guideline does not "fix" them into a cohesion-free parameter object or split the wrap and derive operations.
- A one-line fence on each method points here.
- If NIP-49 ever grows a genuinely cohesive cluster of header parameters, those may form a value object at that point; the current four inputs are not that cluster.
