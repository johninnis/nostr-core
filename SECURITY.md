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

- **BIP-340 Schnorr signing and verification** via `Secp256k1Signer`. The FFI path uses the system `libsecp256k1` C library when available; the pure-PHP path uses `paragonie/ecc` as a fallback. Both paths produce byte-identical signatures under identical `aux_rand`, validated against the BIP-340 specification vectors for Nostr's 32-byte event-id messages and a 100-iteration cross-engine property sweep. Signing rejects any message that is not exactly 32 bytes (`InvalidArgumentException`): every Nostr signature is over a `SHA-256` event id, so a wrong-length message is a programmer error rather than a request to sign arbitrary data. See [ADR 0013](docs/adr/0013-secp256k1signer-sign-rejects-non-32-byte-messages.md).

- **NIP-44 v2 authenticated encryption.** `Nip44Cipher` implements the official NIP-44 v2 spec: ChaCha20 with HMAC-SHA256 MAC; MAC verification happens before unpadding (no padding oracle); nonces come from an injected `RandomBytesGeneratorInterface`. Covered by the official NIP-44 test vectors and a 200-iteration property fuzz that includes ciphertext-tamper and MAC-tamper rejection.

- **NIP-44 ECDH with libsecp256k1 acceleration.** `Secp256k1Ecdh` mirrors the signature service: FFI when available, pure-PHP fallback otherwise. FFI and pure-PHP paths produce byte-identical shared-X values, validated by a 100-iteration parity sweep.

- **NIP-49 password-encrypted private keys.** `Nip49Cipher` uses scrypt + XChaCha20-Poly1305, NFKC password normalisation per spec, and treats the password as a `Closure(): string` so the plaintext does not persist in caller scope. Spec vectors verified. Rejecting a tampered key-security byte rather than mapping it to `Unknown` is what makes the AEAD's associated-data authentication detect tampering; see [ADR 0009](docs/adr/0009-keysecuritybyte-frombyte-throws-on-unknown.md).

- **NIP-59 gift-wrap.** `GiftWrapper` validates both outer and inner signatures before trusting any content, zeroes ephemeral keys it generates, and passes all `ConversationKey` material through the `expose(Closure)` lifecycle so derived secrets do not escape their use-site scope.

- **ECDH input validation.** The bundled `Secp256k1Ecdh` validates that the peer public key's x-coordinate lies in `(0, p)` and rejects a shared point that computes to the identity — on both the FFI and pure-PHP paths — before any shared secret is returned. `ConversationKey::derive(...)` delegates the ECDH to the injected `EcdhServiceInterface`, so these checks live in that implementation; a consumer that supplies its own `EcdhServiceInterface` is responsible for the equivalent validation.

- **Secret material lifecycle.** `SecretKeyMaterial`, `PrivateKey`, and `ConversationKey` hold secrets behind an `expose(Closure)` contract. `zero()` deterministically erases the byte buffer and leaves the object in a state that throws `SecretKeyMaterialZeroedException` on any subsequent access. Sign and ECDH operations wrap their pure-PHP intermediate hex and byte buffers in `try/finally` `sodium_memzero` blocks.

- **Defensive relay-protocol parsing.** `REQ` and `COUNT` messages cap filter count at 20; `Filter` caps each array-valued field at 1000; `SubscriptionId` restricts to printable ASCII; `OkMessage` requires a strict boolean for its `accepted` flag. Malformed relay input is rejected as a typed outcome — the message `fromArray`/`fromJson` factories return `null` and the value-object constructors throw `InvalidArgumentException` — rather than propagating a `TypeError`.

- **NIP-05 identifier parsing.** Strict charset validation on both the local part and domain. IP literals, hostnames with injected paths or user-info, and control characters are rejected at construction.

- **NIP-98 event validation.** `Nip98Validator` enforces kind, signature, timestamp-within-tolerance, URL match, method match, and payload-hash consistency, and enforces single-use by recording each event id once through an injected `Nip98ReplayGuardInterface` (returning a `Replayed` failure if the same id is presented again within the replay window). A signed event with a `payload` tag but no caller-supplied body hash is rejected explicitly.

### What this library does not provide

These are load-bearing consumer responsibilities. The library deliberately does not handle them.

- **A durable NIP-98 replay store.** `Nip98Validator` enforces single-use by calling the injected `Nip98ReplayGuardInterface::recordOnce(...)`, but the durability, scope, and eviction of the seen-id store live in the consumer-supplied adapter. An in-memory guard only blocks replay within one process; a multi-node deployment needs a shared store. The replay window is `2 ×` the timestamp tolerance (default `2 × 60 = 120` seconds); events older than that fall outside the tolerance check and are rejected before the replay guard is consulted.

- **SSRF mitigation for NIP-05 and NIP-11 fetches.** Identifier validation rejects IP literals and syntactically invalid hostnames, but the HTTP request itself is issued by the consumer-supplied `HttpServiceInterface` adapter. Blocking private IP ranges (RFC1918, link-local, metadata services) is the adapter's responsibility, where SSRF policy belongs.

- **Rate limiting of NIP-49 decrypt attempts.** Scrypt runs before AEAD authentication: the scrypt output *is* the AEAD key, so MAC verification cannot happen first. An adversarial `ncryptsec` forces a full scrypt derivation before rejection. Protect the decrypt entry point with rate limiting.

- **Guarantees about memory state after operations.** The library calls `sodium_memzero` on secret-bearing string buffers it creates, as a defence-in-depth discipline. PHP's runtime makes internal copies (interned strings, OPcache, hash-table buckets, temporaries created by standard-library functions) that the library cannot see or clear. Treat memory hygiene as best-effort, not as a guarantee.

- **Destructor-based zeroing.** `SecretKeyMaterial::__destruct` calls `zero()` for defence in depth, but PHP's garbage collector runs on refcount-zero, which may never happen for objects captured in long-lived closures, static state, exception trace frames, or cyclic references. Applications requiring bounded key-material lifetimes must call `$privateKey->zero()` explicitly. Do not rely on the destructor.

- **Timing-side-channel resistance on the pure-PHP fallback path.** The pure-PHP signing, public-key derivation, and ECDH implementations (via `paragonie/ecc` over GMP) are not constant-time, and cannot be made so. The secret-dependent operations — scalar multiplication of the private key and the per-signature nonce, the modular arithmetic that builds `s`, and the ECDH `priv·P` — run on GMP big-integer arithmetic and an interpreted double-and-add point multiplication, both of which branch on the value of the secret; a garbage-collected interpreted runtime also gives no control over instruction timing or memory-access patterns. Mitigations (Montgomery ladder, scalar/nonce blinding) can reduce the leakage but not eliminate it, because they still run on variable-time GMP and the Zend engine — a true guarantee needs the secret-independent machine code that native `libsecp256k1` provides. A local or co-located attacker able to measure signing/ECDH timing could, in principle, recover private-key material. For any server-side or long-lived signer — a relay, a NIP-46 remote signer/bunker, or any service that repeatedly signs attacker-influenced messages with a fixed key — install `libsecp256k1`, enable the `ffi` extension, and verify the native path is active before deploying. The pure-PHP fallback is intended for portability and low-exposure client use, not a hardened signing oracle. See [ADR 0025](docs/adr/0025-secp256k1-keeps-a-native-ffi-path-and-a-pure-php-fallback.md).

- **Verification of AUTH-event challenge freshness.** `AuthMessage` (client) validates the event is kind 22242 at construction but does not verify the signature or match against an expected relay challenge. The library is a parser; challenge tracking is the relay or client application's responsibility.

- **Safety of PHP's native `unserialize()` on library types.** Value objects in this library are not designed to round-trip through PHP's native `serialize()` / `unserialize()` functions. `unserialize` bypasses constructor validation and can instantiate library types in states the normal constructors would reject. Do not pass untrusted data through `unserialize()` into any library type. Parsing incoming relay JSON via `json_decode` and feeding the result into `Event::fromArray`, `Filter::fromArray`, or the message-layer factories is the intended flow, and those factories perform their own type validation.

- **Transport-layer limits.** `JsonMessageDeserialiser` decodes through `JsonWireFormat::decodeArray`, which `json_validate`s then `json_decode`s at a nesting-depth limit of 512 and applies no input-size cap. WebSocket-frame size limits belong in the transport layer, outside this library.

## Design decisions

### `::create()` factories over auto-probing constructors

`Secp256k1Signer` and `Secp256k1Ecdh` expose a static `create()` factory that probes for `libsecp256k1` and dispatches FFI or pure-PHP. The bare constructor stays on the pure-PHP path unless an FFI handle is passed explicitly. Constructors deliberately do not probe the system library, because a constructor that silently `dlopen`s a C library at construction time is a hidden dependency and a hidden failure mode. Keeping the probe in a named constructor makes the side effect opt-in and easy to reason about for tests and DI containers.

Consumer code should use `Secp256k1Signer::create()` and `Secp256k1Ecdh::create()`. The bare constructor is for dependency injection and for tests that need to force the pure-PHP path.

### `zero()` is a contract, not a destructor guarantee

`SecretKeyMaterial` exposes `zero()` as the deterministic erasure primitive. After `zero()`, every subsequent method call on the material (or on the `PrivateKey` / `ConversationKey` wrapping it) throws `SecretKeyMaterialZeroedException`. The destructor also calls `zero()` as defence in depth, but PHP's garbage collector does not run on scope exit. It runs on refcount zero, which may never happen for captured references. Callers requiring deterministic key erasure (session-scoped signers, bunker-style holders) must call `$key->zero()` explicitly at the end of the lifetime. See [ADR 0015](docs/adr/0015-zero-is-a-contract-not-a-guarantee-via-destruction.md).

### NIP-49 password as `Closure(): string`

`Nip49EncryptionInterface::encrypt` and `::decrypt` take the password as a `Closure(): string` rather than a raw string. The adapter invokes the closure exactly once, `sodium_memzero`s the revealed string before the method returns, and `sodium_memzero`s the NFKC-normalised copy on the way out. Callers therefore do not need to maintain a password binding in their own scope. The alternative, accepting a raw `string`, would leave the password in the caller's stack frame until the call site explicitly zeroed it, which is easy to forget.

### Strict 64-byte signatures

`Signature::fromHex` requires a complete 64-byte signature — exactly 128 lowercase hex characters — and returns `null` for anything shorter, longer, upper-case, or non-hex. There is no zero-padding of short inputs. BIP-340 signatures are always 64 bytes, and `libsecp256k1`, `rust-secp256k1`, and `@noble/curves` all reject short signatures outright. A producer that strips leading zero bytes therefore has its signatures rejected at parse time rather than silently reconstructed: left-padding a short input can fabricate the *wrong* signature when the missing bytes came from `s`, so the parser refuses to guess and returns `null` (an anticipated outcome the caller handles). If interoperability with such producers is ever required, it belongs in a verify-and-pick step above the value object, never in `fromHex`. See [ADR 0026](docs/adr/0026-signature-fromhex-requires-a-full-64-byte-signature.md).

### NIP-44 nonce injection via `RandomBytesGeneratorInterface`

`Nip44Cipher` has no public `encryptWithNonce` method. Test determinism is achieved by injecting a `QueuedRandomBytesGenerator` through the `RandomBytesGeneratorInterface` port; production uses the default `NativeRandomBytesGenerator`. The alternative, a public method that accepts a caller-supplied nonce, is a nonce-reuse footgun that breaks ChaCha20 confidentiality catastrophically. Keeping nonce generation behind DI lets tests produce byte-identical vectors while preventing misuse in production.

The same pattern applies to `Nip49Cipher`'s salt and nonce generation. See [ADR 0014](docs/adr/0014-nip44cipher-has-no-public-encryptwithnonce.md).

### Each secret wrapper owns a fresh `SecretKeyMaterial`

`PrivateKey::fromBytes` and `ConversationKey::fromBytes` each allocate their own `SecretKeyMaterial` (via `SecretKeyMaterial::fromBytes`, which validates the length and returns `null` on a wrong size). A wrapping type therefore owns the buffer it zeroes: calling `zero()` on one `PrivateKey` cannot silently wipe the key material held by another object, because no API hands the same material instance to two wrappers. `SecretKeyMaterial`'s constructors (`__construct`, `fromBytes`, `fromHex`, `random`) are public; the lifecycle safety comes from each wrapper allocating a distinct material, not from hiding the factory.

### Gift-wrap outer signature verified, not merely present

`GiftWrapper::validateGiftWrap` calls `$giftWrap->verify($signatureService)`, not just `isSigned()`. NIP-44's AEAD MAC catches ciphertext tampering on its own, but a malicious relay or man-in-the-middle could still modify the outer wrap's non-content fields (tags, `created_at`, claimed pubkey) without the MAC firing. The outer `verify()` closes that gap.

### NIP-05 domain charset rejects IP literals

`Nip05Identifier::fromString` enforces a strict DNS-hostname pattern on the domain (lowercase alphanumerics, hyphens, dots, at least one label separator) and rejects IPv4 and IPv6 literals outright. It does not reject private-range public hostnames (for example `intranet.local`) because that decision is a policy concern that belongs in the HTTP adapter alongside the rest of SSRF mitigation. Splitting the concerns keeps the identifier type syntactically strict and the HTTP adapter semantically responsible.

### `Nip11Info` returns `null` for malformed fields

`Nip11Info::fromArray` and each lazy accessor type-guard the data they read. A relay returning `"supported_nips": "not-an-array"` causes `getSupportedNips()` to return `null` rather than propagating a `TypeError` to the caller. Relays are untrusted peers; graceful degradation on malformed responses is preferable to exception-based denial of service.

### `Secp256k1Signer::verify` is a total predicate

`verify` catches `Throwable` and returns `false`: a signature that cannot be positively established as valid is, for a verification predicate, simply invalid. This is a deliberate, bounded exception to the "never swallow an exception" rule — `verify` is called per event on untrusted data by relays, so a throwing verifier would be a denial-of-service vector, and "not valid" is the safe verdict for any input it cannot verify. The trade-off is that a genuine implementation bug surfaces as "invalid signature" rather than an exception; the BIP-340 vectors and cross-engine property sweeps guard against that. Unlike `sign` — which throws on a wrong-length message, a programmer error on trusted input ([ADR 0013](docs/adr/0013-secp256k1signer-sign-rejects-non-32-byte-messages.md)) — `verify` consumes untrusted data whose every failure mode is honestly "invalid". See [ADR 0027](docs/adr/0027-secp256k1signer-verify-is-a-total-predicate.md).

### Relay-protocol parsing caps

`REQ` and `COUNT` cap filter count at 20 (`ReqMessage::MAX_FILTERS`, `CountMessage::MAX_FILTERS`). `Filter` caps each array-valued field at 1000 (`Filter::MAX_VALUES_PER_FIELD`). `SubscriptionId` is capped at 64 bytes of printable ASCII. These bounds prevent an individual WebSocket frame from pinning arbitrary amounts of memory in a relay built on this library. The values match common relay defaults; consumers that want different limits should wrap the parsing layer rather than modify these constants.

### Memzero is a discipline, not a tested contract

Pure-PHP signing and ECDH contain explicit `sodium_memzero` calls on every hex and byte intermediate that briefly holds secret material. These calls are a defence-in-depth discipline, not a tested contract. Writing a test that asserts the memzero calls fire with the expected sequence and lengths is possible (via a namespace-scoped function override) but has narrow value: it would protect a fallback code path that is not the hot path in any production deployment, against the specific regression of someone deleting a `sodium_memzero` line during a refactor. The existing byte-identical BIP-340 vectors, cross-engine property sweeps, and NIP-44 property fuzz give stronger correctness guarantees for the paths that matter. If the pure-PHP path ever becomes primary, the memzero-contract spy is the regression guard to add.
