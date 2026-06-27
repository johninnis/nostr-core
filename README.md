# Nostr Core Package

[![CI](https://github.com/johninnis/nostr-core/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/nostr-core/actions/workflows/ci.yml)

A PHP library implementing core domain entities and services for the Nostr protocol, built with Clean Architecture principles.

Code is organised around domain concepts (events, identities, tags, messages) rather than NIP numbers: a single `Event` entity handles creation, signing, and verification regardless of which NIP defines the event kind. Domain entities and value objects are immutable, services are stateless, and the package provides building blocks for relays, clients, and web applications without imposing architectural decisions on consumers. See [ADR-0019](docs/adr/0019-domain-first-organisation-cryptography-only-domain-dependency.md) for the organising rationale.

## Features

- Complete Nostr protocol implementation
- Clean Architecture with strict layer separation
- Domain-driven design with pure business logic
- Comprehensive cryptographic support using secp256k1
- Native libsecp256k1 FFI acceleration covering BIP340 sign/verify, x-only
  pubkey derivation, and NIP-44 ECDH — automatic pure-PHP fallback
  when the C library is unavailable
- Bech32 *and* bech32m encoding/decoding via a single `Bech32Codec`
  (NIP-19 prefixes plus BIP-350 bech32m variants), selected through the
  `Bech32Variant` enum
- Content-reference extraction (event, pubkey, relay and quote references
  from tags and content) and reply-chain analysis
- Typed, immutable domain collections and a subscription model
- Full NIP compliance validation
- Type-safe message handling with domain objects at all boundaries
- Extensive test coverage with PHPStan level 9

## Requirements

Declared in `composer.json`:

- PHP 8.4 or higher
- `ext-intl` (NFKC password normalisation in NIP-49)
- `ext-mbstring` (search-filter matching on untrusted event content, `EventContent::getLength`, and the bech32 TLV decoder)
- `ext-sodium` (NIP-44 and NIP-49 AEAD, `sodium_memzero`)
- `paragonie/ecc` (pure-PHP secp256k1 fallback)
- `paragonie/sodium_compat` (raw ChaCha20 keystream with explicit block counter for NIP-44, which `ext-sodium` does not expose)

Declared under `suggest` in `composer.json` (used by optional code paths that the recommended typical usage will load anyway):

- `ext-gmp` is needed by the pure-PHP signing and ECDH fallback (the documented path when `libsecp256k1` is unavailable). If you know you always have `libsecp256k1` installed and never invoke the pure-PHP path, this extension is not touched.
- `ext-ffi` is needed by NIP-49 (unconditionally) and by the `Secp256k1Signer::create()` / `Secp256k1Ecdh::create()` factories (for the `libsecp256k1` probe). Consumers who do not use NIP-49 and who construct the adapters directly with `new Secp256k1Signer(null, ...)` / `new Secp256k1Ecdh(null)` can run without `ext-ffi` at all and stay on the pure-PHP path.

### Optional system libraries

- `libsecp256k1` — when present, Schnorr signing, verification, public-key derivation, and NIP-44 ECDH use the native C library (reached via `ext-ffi`) for significantly faster performance. Without it, the library falls back to a pure-PHP implementation via `paragonie/ecc` automatically.
- `libsodium` — required by NIP-49 scrypt derivation, which calls `crypto_pwhash_scryptsalsa208sha256_ll` through `ext-ffi`. Typically already installed wherever `ext-sodium` is.

## Installation

```bash
composer require innis/nostr-core
```

## Quick Start

Cryptographic operations (signing, verification, public-key derivation, ECDH) are exposed as Domain service interfaces with Infrastructure implementations. The `Secp256k1Signer` and `Secp256k1Ecdh` pick an FFI-accelerated path when `libsecp256k1` is available and fall back to pure PHP otherwise — callers do not need to care.

### Key Generation

```php
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

$signatureService = Secp256k1Signer::create();
$keyPair = KeyPair::generate($signatureService);

echo $keyPair->getPrivateKey()->toBech32(); // nsec1...
echo $keyPair->getPublicKey()->toBech32();  // npub1...
```

### Event Creation and Signing

```php
use Innis\Nostr\Core\Domain\Factory\EventFactory;

$event = EventFactory::createTextNote(
    $keyPair->getPublicKey(),
    'Hello Nostr!'
);

$signedEvent = $event->sign($keyPair, $signatureService);

$signedEvent->verify($signatureService); // bool
```

### NIP-44 Encryption

Deriving a conversation key needs an ECDH service. `Secp256k1Ecdh::create()` follows the same FFI-or-fallback pattern as the signature adapter:

```php
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;

$ecdhService = Secp256k1Ecdh::create();
$conversationKey = ConversationKey::derive(
    $senderPrivateKey,
    $recipientPublicKey,
    $ecdhService,
);

$encryption = new Nip44Cipher();
$ciphertext = $encryption->encrypt('Hello in private', $conversationKey);
$plaintext = $encryption->decrypt($ciphertext, $conversationKey);
```

Nonce generation is injected. `Nip44Cipher` accepts an optional `RandomBytesGeneratorInterface` and defaults to `NativeRandomBytesGenerator` (PHP's `random_bytes`) when none is supplied — that is the production path. Test suites inject a deterministic generator to reproduce the official NIP-44 vectors byte-for-byte. There is deliberately no public `encryptWithNonce` method; see [ADR-0014](docs/adr/0014-nip44cipher-has-no-public-encryptwithnonce.md).

Always construct the adapters through their `::create()` factories. Direct instantiation via `new Secp256k1Signer(null, ...)` or `new Secp256k1Ecdh(null)` exists for dependency injection and testing but stays on the pure-PHP path regardless of whether `libsecp256k1` is installed.

### Message Handling

```php
use Innis\Nostr\Core\Infrastructure\Encoding\JsonMessageDeserialiser;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;

$deserialiser = new JsonMessageDeserialiser();

$eventMessage = new EventMessage($signedEvent);
$json = $eventMessage->toJson();

$deserialised = $deserialiser->deserialiseClientMessage($json);
```

### Password-Encrypted Private Keys (NIP-49)

The NIP-49 adapter takes the password as a `Closure(): string` rather than a raw string. The adapter invokes the closure exactly once, `sodium_memzero`s the revealed password before the method returns, and the caller never has to maintain a password binding in its own scope:

```php
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Cipher;

$adapter = new Nip49Cipher();
$privateKey = PrivateKey::generate();

$ncryptsec = $adapter->encrypt(
    $privateKey,
    static fn (): string => readPasswordFromUser(),
    logN: 16,
    keySecurity: KeySecurityByte::ClientSideOnly,
);

$stored = (string) $ncryptsec; // ncryptsec1...

$decoded = Ncryptsec::fromString($stored);
$recovered = $adapter->decrypt($decoded, static fn (): string => readPasswordFromUser());
```

### Secret Key Lifecycle

`PrivateKey` and `ConversationKey` hold their raw bytes inside a `SecretKeyMaterial` value object. Callers that need to clear secret material from memory can call `zero()`; any subsequent operation on that key throws `SecretKeyMaterialZeroedException`. Infrastructure code that genuinely needs raw bytes uses the bounded `expose` callback, which passes a freshly-allocated copy of the bytes (not a copy-on-write alias of the stored secret) to the closure and `sodium_memzero`s that copy before the method returns, so the exposed bytes are actually wiped rather than left in a spared buffer; see [ADR-0028](docs/adr/0028-secretkeymaterial-expose-hands-a-detached-copy-so-the-wipe-is-effective.md):

```php
$derived = $privateKey->expose(static function (string $bytes): string {
    return derive_something($bytes);
});

$privateKey->zero();
$signatureService->sign($privateKey, $message); // throws SecretKeyMaterialZeroedException
```

`zero()` is a contract a caller invokes explicitly, not a guarantee delivered by the destructor. Applications that require bounded key-material lifetimes — session-scoped bunker signers, for example — must call `$privateKey->zero()` explicitly at the end of the session rather than relying on garbage collection. See [ADR-0015](docs/adr/0015-zero-is-a-contract-not-a-guarantee-via-destruction.md).

## Examples

Runnable scripts live in [`examples/`](examples/); run one with `php examples/<name>.php`:

- [`sign_and_verify.php`](examples/sign_and_verify.php) — generate a key pair, create and sign a text note, verify it
- [`nip44_encrypt_decrypt.php`](examples/nip44_encrypt_decrypt.php) — derive a NIP-44 conversation key via ECDH and encrypt/decrypt a message
- [`nip49_password_encrypt.php`](examples/nip49_password_encrypt.php) — encrypt a private key under a password to an `ncryptsec` and recover it (requires `ext-ffi` and libsodium)
- [`giftwrap_direct_message.php`](examples/giftwrap_direct_message.php) — seal and gift-wrap a NIP-17 private message, then unwrap it

The `examples/` directory is covered by PHPStan and php-cs-fixer in CI, like `src` and `tests`.

## Supported NIPs

| NIP | Description | Support |
|-----|-------------|---------|
| [NIP-01](https://github.com/nostr-protocol/nips/blob/master/01.md) | Basic protocol flow | Event creation, signing, verification, serialisation |
| [NIP-02](https://github.com/nostr-protocol/nips/blob/master/02.md) | Follow list | Kind 3 with contact list tags |
| [NIP-04](https://github.com/nostr-protocol/nips/blob/master/04.md) | Encrypted direct messages | Kind 4 with recipient validation; `Nip04Cipher` for AES-256-CBC encrypt/decrypt over a 32-byte ECDH shared secret |
| [NIP-05](https://github.com/nostr-protocol/nips/blob/master/05.md) | DNS-based identity | Identifier parsing and HTTP verification |
| [NIP-09](https://github.com/nostr-protocol/nips/blob/master/09.md) | Event deletion | Kind 5 with deletion tag validation and `isDeletion()` detection |
| [NIP-10](https://github.com/nostr-protocol/nips/blob/master/10.md) | Reply conventions | Reply chain analysis with root/reply/mention markers |
| [NIP-11](https://github.com/nostr-protocol/nips/blob/master/11.md) | Relay information | Relay metadata fetching and parsing |
| [NIP-17](https://github.com/nostr-protocol/nips/blob/master/17.md) | Private direct messages | Kind 14 with NIP-44 encryption and gift wrap (kind 1059/1060) |
| [NIP-18](https://github.com/nostr-protocol/nips/blob/master/18.md) | Reposts | Kind 6/16 with embedded event extraction and quote detection |
| [NIP-19](https://github.com/nostr-protocol/nips/blob/master/19.md) | Bech32 encoding | npub, nsec, note, nprofile, nevent, naddr encoding/decoding; `Bech32Codec` also supports the BIP-350 bech32m variant for non-NIP consumers (e.g. FROSTR `bfgroup1…` / `bfshare1…` / `bfonboard1…`) via the `Bech32Variant` enum |
| [NIP-22](https://github.com/nostr-protocol/nips/blob/master/22.md) | Comments | Kind 1111 with root/parent kind tags and reply chain analysis |
| [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) | Long-form content | Kind 30023 as parameterised replaceable events |
| [NIP-25](https://github.com/nostr-protocol/nips/blob/master/25.md) | Reactions | Kind 7 event support |
| [NIP-28](https://github.com/nostr-protocol/nips/blob/master/28.md) | Public chat | Kind 40-44 channel event types |
| [NIP-40](https://github.com/nostr-protocol/nips/blob/master/40.md) | Expiration | Event expiration detection via `isExpired()` |
| [NIP-42](https://github.com/nostr-protocol/nips/blob/master/42.md) | Authentication | AUTH message handling and challenge detection |
| [NIP-44](https://github.com/nostr-protocol/nips/blob/master/44.md) | Encrypted payloads | NIP-44 v2 encrypt/decrypt with ECDH, ChaCha20, HMAC-SHA256 |
| [NIP-45](https://github.com/nostr-protocol/nips/blob/master/45.md) | Counting | COUNT relay message support |
| [NIP-49](https://github.com/nostr-protocol/nips/blob/master/49.md) | Private key encryption | Password-encrypted `ncryptsec` with scrypt + XChaCha20-Poly1305 |
| [NIP-50](https://github.com/nostr-protocol/nips/blob/master/50.md) | Search | Search filter support |
| [NIP-51](https://github.com/nostr-protocol/nips/blob/master/51.md) | Lists | All standard list kinds (10000-10102) and set kinds (30000-39092) |
| [NIP-57](https://github.com/nostr-protocol/nips/blob/master/57.md) | Lightning zaps | Zap request/receipt parsing, BOLT-11 amount extraction |
| [NIP-61](https://github.com/nostr-protocol/nips/blob/master/61.md) | Nutzaps | Kind 9321 cashu proof parsing and amount extraction |
| [NIP-70](https://github.com/nostr-protocol/nips/blob/master/70.md) | Protected events | Protected event detection via `isProtected()` |
| [NIP-98](https://github.com/nostr-protocol/nips/blob/master/98.md) | HTTP auth | Kind 27235 validation: signature, URL, method, payload hash, timestamp tolerance |

Beyond the NIPs listed above, `EventKind` carries named constants for a broad range of registered kinds (metadata, channels, MLS messaging, polls, cashu wallet events, live events, web pages, and more) together with the replaceable / ephemeral / parameterised-replaceable range boundaries, so consumers can classify kinds the library does not otherwise model.

## Performance

### Native FFI Acceleration

The library can use the system's native `libsecp256k1` C library via PHP's
FFI extension for cryptographic operations. This provides significant
performance gains for applications performing bulk signature verification
(relays, indexers).

Operations routed through `LibSecp256k1Ffi` when the library is loaded:

- `sign` — BIP340 Schnorr sign
- `verify` — BIP340 Schnorr verify
- `derivePublicKey` — secret to 32-byte x-only pubkey
- `computeSharedX` — x-only ECDH for NIP-44 conversation keys

To install the native library:

```bash
# Ubuntu/Debian
sudo apt install libsecp256k1-1

# macOS (Homebrew)
brew install libsecp256k1
```

No code changes are required. The library detects and uses the native
implementation automatically, falling back to pure PHP when unavailable.

## Security

See [SECURITY.md](SECURITY.md) for the library's security properties, the
responsibilities it leaves to the consumer, and the reasoning behind the
non-obvious cryptographic decisions. In particular, the pure-PHP cryptography
fallback is **not constant-time**, so any server-side or long-lived signer
should ensure the native `libsecp256k1` (FFI) path is active.

## Architecture

This package follows Clean Architecture principles with strict layer separation:

- **Domain Layer**: Pure business logic, immutable entities and value objects (cryptographic library is the sole external dependency, used directly by identity value objects)
- **Application Layer**: Port interfaces for external service integration
- **Infrastructure Layer**: Implementations of the domain and application interfaces, grouped by concern (`Crypto/`, `Encoding/`, `Http/`, `Time/`)

## Architecture decisions

Design rationale lives in [`docs/adr/`](docs/adr/) as immutable Architecture Decision Records — read these before "correcting" a choice that reads like a smell. Each record states the context, the decision, and what it forbids.

| ADR | Decision |
|-----|----------|
| [0000](docs/adr/0000-record-architecture-decisions.md) | Record architecture decisions |
| [0001](docs/adr/0001-value-objects-keep-getter-methods-not-property-hooks.md) | Value objects expose state through `getX()` accessors, not public properties or hooks |
| [0002](docs/adr/0002-nostrexception-roots-nostr-faults-only.md) | `NostrException` roots Nostr faults; consumers root their own |
| [0003](docs/adr/0003-anticipated-outcomes-returned-faults-thrown.md) | Anticipated outcomes are returned; faults are thrown |
| [0004](docs/adr/0004-publickey-eventid-signature-stay-separate.md) | `PublicKey`, `EventId`, and `Signature` stay separate types, not a shared base |
| [0005](docs/adr/0005-timestamp-now-direct-clockinterface-when-time-under-test.md) | `Timestamp::now()` reads the clock directly; `ClockInterface` is injected only where elapsed time is under test |
| [0006](docs/adr/0006-tagtype-is-a-value-object-not-a-backed-enum.md) | `TagType` is a value object, not a backed `enum` |
| [0007](docs/adr/0007-subscriptioncollection-does-not-extend-typedcollection.md) | `SubscriptionCollection` does not extend `TypedCollection` |
| [0008](docs/adr/0008-domain-services-static-when-pure-injected-when-collaborator.md) | Domain services are `static` when pure, injected interfaces when they have a collaborator |
| [0009](docs/adr/0009-keysecuritybyte-frombyte-throws-on-unknown.md) | `KeySecurityByte::fromByte` throws on an unrecognised byte |
| [0010](docs/adr/0010-relayurl-fromstring-rejects-more-than-malformed-syntax.md) | `RelayUrl::fromString` canonicalises, and rejects what it cannot canonicalise |
| [0011](docs/adr/0011-contentreferencetagbuilder-emits-q-tag-only-for-quotes.md) | `ContentReferenceTagBuilder` emits a `q` tag only for a quoted event |
| [0012](docs/adr/0012-event-does-not-cache-its-computed-id.md) | `Event` does not cache its computed id |
| [0013](docs/adr/0013-secp256k1signer-sign-rejects-non-32-byte-messages.md) | `Secp256k1Signer::sign` rejects any message that is not exactly 32 bytes |
| [0014](docs/adr/0014-nip44cipher-has-no-public-encryptwithnonce.md) | `Nip44Cipher` has no public `encryptWithNonce`; nonce generation stays behind a port |
| [0015](docs/adr/0015-zero-is-a-contract-not-a-guarantee-via-destruction.md) | `zero()` is a contract, not a guarantee via destruction |
| [0016](docs/adr/0016-message-hierarchy-uses-inheritance-for-a-sum-type.md) | The protocol message hierarchy uses inheritance for a discriminated union |
| [0017](docs/adr/0017-eventvalidator-and-nipcompliancevalidator-keep-separate-signature-gates.md) | `EventValidator` and `NipComplianceValidator` keep separate signature and timestamp gates |
| [0018](docs/adr/0018-randomness-read-directly-in-named-constructors-port-injected-where-output-is-under-test.md) | Random generation reads the entropy source directly in named constructors; `RandomBytesGeneratorInterface` is injected where the random output is under test |
| [0019](docs/adr/0019-domain-first-organisation-cryptography-only-domain-dependency.md) | Code is organised domain-first, and cryptography is the only external dependency in the domain layer |
| [0020](docs/adr/0020-filterhasher-canonicalises-to-ascii-safe-json-for-cross-language-parity.md) | `FilterHasher` canonicalises to ASCII-safe JSON for byte-identical cross-language hashes |
| [0021](docs/adr/0021-single-bech32codec-covers-bech32-and-bech32m.md) | A single `Bech32Codec` covers both bech32 and bech32m |
| [0022](docs/adr/0022-event-fromarray-coerces-non-string-content.md) | `Event::fromArray` coerces non-string `content` to its JSON string rather than rejecting it |
| [0023](docs/adr/0023-tagtype-keeps-named-constructors-alongside-fromstring.md) | `TagType` keeps convenience named constructors alongside `fromString` and constants |
| [0024](docs/adr/0024-typed-collections-memoise-a-membership-index.md) | Typed collections memoise a membership index |
| [0025](docs/adr/0025-secp256k1-keeps-a-native-ffi-path-and-a-pure-php-fallback.md) | secp256k1 signing and ECDH keep a native FFI path and a pure-PHP fallback |
| [0026](docs/adr/0026-signature-fromhex-requires-a-full-64-byte-signature.md) | `Signature::fromHex` requires a complete 64-byte signature |
| [0027](docs/adr/0027-secp256k1signer-verify-is-a-total-predicate.md) | `Secp256k1Signer::verify` is a total predicate — it returns `false`, never throws |
| [0028](docs/adr/0028-secretkeymaterial-expose-hands-a-detached-copy-so-the-wipe-is-effective.md) | `SecretKeyMaterial::expose` hands the closure a detached copy so the wipe is effective |
| [0029](docs/adr/0029-privatekey-rejects-scalars-outside-the-curve-order.md) | `PrivateKey` rejects scalars outside `[1, n-1]` |
| [0030](docs/adr/0030-nip49-floors-encryption-logn-but-accepts-weaker-on-decrypt.md) | NIP-49 floors what it encrypts at logN 16 but accepts weaker on decrypt, with a configurable decrypt ceiling |

## Dependencies

| Package | Purpose |
|---------|---------|
| `paragonie/ecc` | Pure-PHP secp256k1 elliptic curve operations (fallback when FFI unavailable) |
| `paragonie/sodium_compat` | Raw ChaCha20 keystream with an explicit block counter for NIP-44 (not exposed by `ext-sodium`) |

## Testing

```bash
# Full suite: Unit + Integration + Compliance + PHPStan (ship gate)
composer test

# Unit suite only (fast inner loop; skips compliance property fuzz)
composer test-unit

# PHPStan analysis (level 9)
composer analyse

# Fix code style
composer fix-style
```

## Filter-set hash

`FilterHasher::hash` computes a stable, order-independent identity for a NIP-01 `REQ` filter set, suitable as a subscription dedup key. Two filter sets that select the same events hash to the same digest regardless of input ordering, and the digest is byte-for-byte identical to the TypeScript sibling's `hashFilters` for every input — including non-ASCII `search` strings and tag-filter values.

```php
$key = FilterHasher::hash(...$filters); // lowercase-hex SHA-256
```

The canonicalisation contract and the cross-language parity rationale are recorded in [ADR-0020](docs/adr/0020-filterhasher-canonicalises-to-ascii-safe-json-for-cross-language-parity.md); the conformance anchors that lock the two runtimes together are asserted in both packages' test suites.

## License

MIT License. See LICENSE file for details.
