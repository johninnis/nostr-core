# 124. The native ECDH hash callback is converted to a C function pointer once per handle

## Status

Accepted

## Context

`LibSecp256k1Ffi::computeSharedX` calls `secp256k1_ecdh`, which takes a hash callback. The callback here copies the shared point's x-coordinate out unhashed, because NIP-04 and NIP-44 key agreement consume the raw x-coordinate (ADR-0101).

PHP's FFI turns a PHP callable passed where C expects a function pointer into a native trampoline, and it keeps every trampoline it makes, together with a reference to the callable, until the request ends. It never frees one early, and it makes a new one at every conversion, even of the same closure. Building the closure inside `computeSharedX` therefore leaked one trampoline per key agreement: about 590 bytes a call, 47 MB after 80,000 calls. A long-running relay, signer or bunker does key agreement for every NIP-44 message it opens, so its memory grew without bound.

Two fixes keep the output byte-identical and the scalar multiplication constant-time:

- Convert the callback once, when the handle is loaded, and pass the resulting function pointer on every call.
- Drop the callback: multiply the peer's point by the secret with `secp256k1_ec_pubkey_tweak_mul`, serialise it compressed and take the x-coordinate.

The second has no callback at all, but it uses a primitive built to tweak public keys rather than to agree keys: how it treats a secret scalar is a property of the library's implementation, not part of the contract it documents. `secp256k1_ecdh` is the module the library documents for this job, and its constant-time multiplication is part of that module's purpose.

## Decision

- `LibSecp256k1Ffi::tryLoad` converts the x-copying callback to a `secp256k1_ecdh_hash_function` C value once and holds it on the handle beside the context. `computeSharedX` passes that value to `secp256k1_ecdh` and never builds a callable.
- Key agreement stays on `secp256k1_ecdh`.

## Consequences

- One trampoline per loaded handle, whatever the number of key agreements. An Integration test runs 2,000 native key agreements and fails if memory grows by 64 KiB or more. That bound is deterministic: the leak it guards against costs over 1 MB there.
- The shared x-coordinate is unchanged, as the ECDH parity suite and the NIP-44 vectors pin.
- Do not move the closure back into `computeSharedX`, or pass any PHP callable to an FFI function on a per-call path. Do not swap `secp256k1_ecdh` for `secp256k1_ec_pubkey_tweak_mul` to avoid the callback.
