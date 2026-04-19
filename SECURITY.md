# Security

This document describes the security properties `innis/nostr-core` provides, the properties it deliberately does not provide, and the reasoning behind the non-obvious design decisions. It is the reference consumers should read before relying on this library in production, and the reference contributors should read before changing any of the cryptographic, identity, or HTTP-facing code paths.

## Reporting a vulnerability

If you have found a security vulnerability in `innis/nostr-core`, report it privately through GitHub's built-in vulnerability reporting: **Security → Advisories → Report a vulnerability** on the repository page. Do not open a public issue for security-sensitive bugs.

Include:

- A description of the vulnerability and its impact.
- Reproduction steps or a proof-of-concept.
- The affected version (tag or commit SHA).

Acknowledgement is best-effort within 72 hours. Fixes land first, then the advisory is published.

For non-security bugs (typos, error-message tweaks, parsing issues that do not affect crypto), open a regular issue.

This project does not run a bug bounty.

## Supported versions

Only the latest tagged release is supported. Older releases do not receive backported fixes, ever. Use the latest version.

## Security properties

### What this library provides

- **BIP-340 Schnorr signing and verification** via `Secp256k1SignatureAdapter`. The FFI path uses the system `libsecp256k1` C library when available; the pure-PHP path uses `paragonie/ecc` as a fallback. Both paths produce byte-identical signatures under identical `aux_rand`, validated against every BIP-340 specification vector and a 100-iteration cross-engine property sweep.

- **NIP-44 v2 authenticated encryption.** `Nip44EncryptionAdapter` implements the official NIP-44 v2 spec: ChaCha20 with HMAC-SHA256 MAC; MAC verification happens before unpadding (no padding oracle); nonces come from an injected `RandomBytesGeneratorInterface`. Covered by the official NIP-44 test vectors and a 200-iteration property fuzz that includes ciphertext-tamper and MAC-tamper rejection.

- **NIP-44 ECDH with libsecp256k1 acceleration.** `Secp256k1EcdhAdapter` mirrors the signature service: FFI when available, pure-PHP fallback otherwise. FFI and pure-PHP paths produce byte-identical shared-X values, validated by a 100-iteration parity sweep.

- **NIP-49 password-encrypted private keys.** `Nip49EncryptionAdapter` uses scrypt + XChaCha20-Poly1305, NFKC password normalisation per spec, and treats the password as a `Closure(): string` so the plaintext does not persist in caller scope. Spec vectors verified.

- **NIP-59 gift-wrap.** `GiftWrapAdapter` validates both outer and inner signatures before trusting any content, zeroes ephemeral keys it generates, and passes all `ConversationKey` material through the `expose(Closure)` lifecycle so derived secrets do not escape their use-site scope.

- **ECDH input validation.** Every `ConversationKey::derive(...)` call validates the peer public key's x-coordinate lies in `(0, p)` and rejects a shared point that computes to the identity before deriving the conversation key.

- **Secret material lifecycle.** `SecretKeyMaterial`, `PrivateKey`, and `ConversationKey` hold secrets behind an `expose(Closure)` contract. `zero()` deterministically erases the byte buffer and leaves the object in a state that throws `SecretKeyMaterialZeroedException` on any subsequent access. Sign and ECDH operations wrap their pure-PHP intermediate hex and byte buffers in `try/finally` `sodium_memzero` blocks.

- **Defensive relay-protocol parsing.** `REQ` and `COUNT` messages cap filter count at 20; `Filter` caps each array-valued field at 1000; `SubscriptionId` restricts to printable ASCII; `OkMessage` requires a strict boolean for its `accepted` flag. Malformed relay responses are rejected with `InvalidArgumentException` rather than propagating `TypeError`.

- **NIP-05 identifier parsing.** Strict charset validation on both the local part and domain. IP literals, hostnames with injected paths or user-info, and control characters are rejected at construction.

- **NIP-98 event validation.** `Nip98ValidationService` enforces kind, signature, timestamp-within-tolerance, URL match, method match, and payload-hash consistency. A signed event with a `payload` tag but no caller-supplied body hash is rejected explicitly.

### What this library does not provide

These are load-bearing consumer responsibilities. The library deliberately does not handle them.

- **NIP-98 replay protection.** The validator accepts any correctly-signed event whose `created_at` falls within the tolerance window (default 60 seconds). Preventing replay requires caller-side tracking of used event IDs. This is a NIP-98 specification constraint, not a library limitation.

- **SSRF mitigation for NIP-05 and NIP-11 fetches.** Identifier validation rejects IP literals and syntactically invalid hostnames, but the HTTP request itself is issued by the consumer-supplied `HttpServiceInterface` adapter. Blocking private IP ranges (RFC1918, link-local, metadata services) is the adapter's responsibility, where SSRF policy belongs.

- **Rate limiting of NIP-49 decrypt attempts.** Scrypt runs before AEAD authentication: the scrypt output *is* the AEAD key, so MAC verification cannot happen first. An adversarial `ncryptsec` forces a full scrypt derivation before rejection. Protect the decrypt entry point with rate limiting.

- **Guarantees about memory state after operations.** The library calls `sodium_memzero` on secret-bearing string buffers it creates, as a defence-in-depth discipline. PHP's runtime makes internal copies (interned strings, OPcache, hash-table buckets, temporaries created by standard-library functions) that the library cannot see or clear. Treat memory hygiene as best-effort, not as a guarantee.

- **Destructor-based zeroing.** `SecretKeyMaterial::__destruct` calls `zero()` for defence in depth, but PHP's garbage collector runs on refcount-zero, which may never happen for objects captured in long-lived closures, static state, exception trace frames, or cyclic references. Applications requiring bounded key-material lifetimes must call `$privateKey->zero()` explicitly. Do not rely on the destructor.

- **Timing-side-channel resistance on the pure-PHP fallback path.** The pure-PHP signing, public-key derivation, and ECDH implementations via `paragonie/ecc` are not hardened against timing analysis. Where the threat model includes co-located adversaries, the FFI path (`libsecp256k1`) must be used.

- **Verification of AUTH-event challenge freshness.** `AuthMessage` (client) validates the event is kind 22242 at construction but does not verify the signature or match against an expected relay challenge. The library is a parser; challenge tracking is the relay or client application's responsibility.

- **Safety of PHP's native `unserialize()` on library types.** Value objects in this library are not designed to round-trip through PHP's native `serialize()` / `unserialize()` functions. `unserialize` bypasses constructor validation and can instantiate library types in states the normal constructors would reject. Do not pass untrusted data through `unserialize()` into any library type. Parsing incoming relay JSON via `json_decode` and feeding the result into `Event::fromArray`, `Filter::fromArray`, or the message-layer factories is the intended flow, and those factories perform their own type validation.

- **Transport-layer limits.** `JsonMessageSerialiserAdapter` uses `json_decode` with PHP's default depth limit (512) and no explicit input-size cap. WebSocket-frame size limits belong in the transport layer, outside this library.

## Design decisions

### `::create()` factories over auto-probing constructors

`Secp256k1SignatureAdapter` and `Secp256k1EcdhAdapter` expose a static `create()` factory that probes for `libsecp256k1` and dispatches FFI or pure-PHP. The bare constructor stays on the pure-PHP path unless an FFI handle is passed explicitly. Constructors deliberately do not probe the system library, because a constructor that silently `dlopen`s a C library at construction time is a hidden dependency and a hidden failure mode. Keeping the probe in a named constructor makes the side effect opt-in and easy to reason about for tests and DI containers.

Consumer code should use `Secp256k1SignatureAdapter::create()` and `Secp256k1EcdhAdapter::create()`. The bare constructor is for dependency injection and for tests that need to force the pure-PHP path.

### `zero()` is a contract, not a destructor guarantee

`SecretKeyMaterial` exposes `zero()` as the deterministic erasure primitive. After `zero()`, every subsequent method call on the material (or on the `PrivateKey` / `ConversationKey` wrapping it) throws `SecretKeyMaterialZeroedException`. The destructor also calls `zero()` as defence in depth, but PHP's garbage collector does not run on scope exit. It runs on refcount zero, which may never happen for captured references. Callers requiring deterministic key erasure (session-scoped signers, bunker-style holders) must call `$key->zero()` explicitly at the end of the lifetime.

### NIP-49 password as `Closure(): string`

`Nip49EncryptionInterface::encrypt` and `::decrypt` take the password as a `Closure(): string` rather than a raw string. The adapter invokes the closure exactly once, `sodium_memzero`s the revealed string before the method returns, and `sodium_memzero`s the NFKC-normalised copy on the way out. Callers therefore do not need to maintain a password binding in their own scope. The alternative, accepting a raw `string`, would leave the password in the caller's stack frame until the call site explicitly zeroed it, which is easy to forget.

### Short Schnorr signature tolerance

`Signature::fromHex` accepts 126-128 character hex strings and left-zero-pads shorter inputs to 128. BIP-340 specifies that signatures are always exactly 64 bytes; producers that strip leading zero bytes are non-conformant. `rust-secp256k1`, `libsecp256k1`, and `@noble/curves` all reject short signatures outright. This library tolerates them as a pragmatic accommodation for specific non-conformant producers observed in the nostr ecosystem. The tolerance only succeeds when the missing bytes were stripped from `r`; a sig stripped from `s` pads into a wrong shape and fails verification, which is the correct outcome. If a try-both-splits upgrade is ever needed, it can be added to the verify path without altering `Signature`.

### NIP-44 nonce injection via `RandomBytesGeneratorInterface`

`Nip44EncryptionAdapter` has no public `encryptWithNonce` method. Test determinism is achieved by injecting a `QueuedRandomBytesGenerator` through the `RandomBytesGeneratorInterface` port; production uses the default `NativeRandomBytesGeneratorAdapter`. The alternative, a public method that accepts a caller-supplied nonce, is a nonce-reuse footgun that breaks ChaCha20 confidentiality catastrophically. Keeping nonce generation behind DI lets tests produce byte-identical vectors while preventing misuse in production.

The same pattern applies to `Nip49EncryptionAdapter`'s salt and nonce generation.

### `SecretKeyMaterial::fromBytes` is not public

Construction of `SecretKeyMaterial` goes through its constructor, which validates length inline. There is no `fromBytes` named factory. Callers wanting a `PrivateKey` or `ConversationKey` go through `PrivateKey::fromBytes` / `ConversationKey::fromBytes`, which always allocate a fresh material the returned object owns. This eliminates a footgun where two wrapping types could share the same lifecycle-aware buffer and `zero()` on one would silently kill the other.

### Gift-wrap outer signature verified, not merely present

`GiftWrapAdapter::validateGiftWrap` calls `$giftWrap->verify($signatureService)`, not just `isSigned()`. NIP-44's AEAD MAC catches ciphertext tampering on its own, but a malicious relay or man-in-the-middle could still modify the outer wrap's non-content fields (tags, `created_at`, claimed pubkey) without the MAC firing. The outer `verify()` closes that gap.

### NIP-05 domain charset rejects IP literals

`Nip05Identifier::fromString` enforces a strict DNS-hostname pattern on the domain (lowercase alphanumerics, hyphens, dots, at least one label separator) and rejects IPv4 and IPv6 literals outright. It does not reject private-range public hostnames (for example `intranet.local`) because that decision is a policy concern that belongs in the HTTP adapter alongside the rest of SSRF mitigation. Splitting the concerns keeps the identifier type syntactically strict and the HTTP adapter semantically responsible.

### `Nip11Info` returns `null` for malformed fields

`Nip11Info::fromArray` and each lazy accessor type-guard the data they read. A relay returning `"supported_nips": "not-an-array"` causes `getSupportedNips()` to return `null` rather than propagating a `TypeError` to the caller. Relays are untrusted peers; graceful degradation on malformed responses is preferable to exception-based denial of service.

### `Secp256k1SignatureAdapter::verify` catches `Throwable`

Any exception from the underlying FFI or pure-PHP verify implementation is caught and returned as `false`. Verification has two legitimate outcomes, valid or invalid, and any path that reaches an exception is a failure of verification, not an error worth propagating. The cost is that programmer errors in the caller surface as "invalid signature" rather than as exceptions; this is accepted as a correct trade for a method whose semantic output is a boolean.

### Relay-protocol parsing caps

`REQ` and `COUNT` cap filter count at 20 (`ReqMessage::MAX_FILTERS`, `CountMessage::MAX_FILTERS`). `Filter` caps each array-valued field at 1000 (`Filter::MAX_VALUES_PER_FIELD`). `SubscriptionId` is capped at 64 bytes of printable ASCII. These bounds prevent an individual WebSocket frame from pinning arbitrary amounts of memory in a relay built on this library. The values match common relay defaults; consumers that want different limits should wrap the parsing layer rather than modify these constants.

### Memzero is a discipline, not a tested contract

Pure-PHP signing and ECDH contain explicit `sodium_memzero` calls on every hex and byte intermediate that briefly holds secret material. These calls are a defence-in-depth discipline, not a tested contract. Writing a test that asserts the memzero calls fire with the expected sequence and lengths is possible (via a namespace-scoped function override) but has narrow value: it would protect a fallback code path that is not the hot path in any production deployment, against the specific regression of someone deleting a `sodium_memzero` line during a refactor. The existing byte-identical BIP-340 vectors, cross-engine property sweeps, and NIP-44 property fuzz give stronger correctness guarantees for the paths that matter. If the pure-PHP path ever becomes primary, the memzero-contract spy is the regression guard to add.
