# 14. `Nip44Cipher` has no public `encryptWithNonce`; nonce generation stays behind a port

## Status

Accepted

## Context

NIP-44 test vectors are defined for a fixed nonce, so the test suite must be able to reproduce them byte-for-byte. The straightforward way to make that possible is a public `encryptWithNonce(string $nonce, ...)` method — but a caller-supplied nonce is a reuse footgun that catastrophically breaks ChaCha20 confidentiality.

## Decision

`Nip44Cipher` has no public `encryptWithNonce` method. Nonce generation is injected behind a `RandomBytesGeneratorInterface` port. `Nip44Cipher` defaults to `NativeRandomBytesGenerator` (PHP's `random_bytes`) when none is supplied — that is the production path — and test suites inject a deterministic generator to reproduce the official NIP-44 vectors byte-for-byte.

Keeping nonce generation behind a port makes tests deterministic without giving production code a way to misuse it.

## Consequences

- Tests reproduce the official NIP-44 vectors byte-for-byte via a deterministic injected generator.
- Production code has no API surface that accepts a caller-supplied nonce, so nonce reuse cannot be introduced at a call site.
- Do not add a public `encryptWithNonce` for convenience — it reintroduces the reuse footgun.
