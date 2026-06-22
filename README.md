# Nostr Core Package

[![CI](https://github.com/johninnis/nostr-core/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/nostr-core/actions/workflows/ci.yml)

A PHP library implementing core domain entities and services for the Nostr protocol, built with Clean Architecture principles.

## Why this library?

Existing PHP Nostr libraries (nostriphant, swentel/nostr-php) are organised around individual NIPs, mixing protocol concerns, infrastructure, and application logic together. This makes them difficult to integrate into projects that follow clean architecture or domain-driven design.

This library takes a different approach:

- **Domain-first, not NIP-first.** Code is organised around domain concepts (events, identities, tags, messages) rather than NIP numbers. A single `Event` entity handles creation, signing, and verification regardless of which NIP defines the event kind.
- **Clean Architecture with strict layer separation.** Domain entities and value objects have no framework dependencies. The only external library in the domain layer is cryptographic (secp256k1 elliptic curve math), which is intrinsic to Nostr identity. Bech32 encoding, JSON serialisation, and other infrastructure concerns live behind interfaces or in infrastructure adapters.
- **Immutable value objects and pure functions.** Events, tags, timestamps, and identities are all immutable. Factory methods are static. Services are stateless. No hidden side effects.
- **Designed for composition.** This is a core library, not an application. It provides the building blocks for relays, clients, and web applications without imposing architectural decisions on consumers.

## Features

- Complete Nostr protocol implementation
- Clean Architecture with strict layer separation
- Domain-driven design with pure business logic
- Comprehensive cryptographic support using secp256k1
- Native libsecp256k1 FFI acceleration covering BIP340 sign/verify, x-only
  pubkey derivation, NIP-44 ECDH, and group-law primitives (compressed
  scalar-base-mul, point-mul, point-add) — automatic pure-PHP fallback
  when the C library is unavailable
- Bech32 *and* bech32m encoding/decoding via a single `Bech32Codec`
  (NIP-19 prefixes plus BIP-350 variants used by FROSTR and other
  bech32m-prefixed consumers)
- Full NIP compliance validation
- Type-safe message handling with domain objects at all boundaries
- Optimised tag lookups via lazy indexing
- Extensive test coverage with PHPStan level 9

## Requirements

Declared in `composer.json`:

- PHP 8.4 or higher
- `ext-intl` (NFKC password normalisation in NIP-49)
- `ext-sodium` (NIP-44 and NIP-49 AEAD, `sodium_memzero`)
- `paragonie/ecc` (pure-PHP secp256k1 fallback)
- `paragonie/sodium_compat` (raw ChaCha20 keystream with explicit block counter for NIP-44, which `ext-sodium` does not expose)

Declared under `suggest` in `composer.json` (used by optional code paths that the recommended typical usage will load anyway):

- `ext-gmp` is needed by the pure-PHP signing and ECDH fallback (the documented path when `libsecp256k1` is unavailable). If you know you always have `libsecp256k1` installed and never invoke the pure-PHP path, this extension is not touched.
- `ext-mbstring` is needed by the search-filter matcher, `EventContent::getLength`, and the bech32 TLV decoder. Most consumers will hit one of these.
- `ext-ffi` is needed by NIP-49 (unconditionally) and by the `Secp256k1Signer::create()` / `Secp256k1Ecdh::create()` factories (for the `libsecp256k1` probe). Consumers who do not use NIP-49 and who construct the adapters directly with `new Secp256k1Signer(null, ...)` / `new Secp256k1Ecdh()` can run without `ext-ffi` at all and stay on the pure-PHP path.
- `libsodium` system library, reachable via FFI, is required for NIP-49 scrypt. Typically already installed wherever `ext-sodium` is installed.

### Optional (recommended)

- `libsecp256k1` system library

When present, Schnorr signing, verification, public-key derivation, and NIP-44 ECDH use the native C library for significantly faster performance. Without it, the library falls back to a pure-PHP implementation via `paragonie/ecc` automatically.

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

Always construct the adapters through their `::create()` factories. Direct instantiation via `new Secp256k1Signer(null, ...)` or `new Secp256k1Ecdh()` exists for dependency injection and testing but stays on the pure-PHP path regardless of whether `libsecp256k1` is installed.

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

`PrivateKey` and `ConversationKey` hold their raw bytes inside a `SecretKeyMaterial` value object. Callers that need to clear secret material from memory can call `zero()`; any subsequent operation on that key throws `SecretKeyMaterialZeroedException`. Infrastructure code that genuinely needs raw bytes uses the bounded `expose` callback, which passes a CoW-separated copy of the bytes to the closure and `sodium_memzero`s that copy before the method returns:

```php
$derived = $privateKey->expose(static function (string $bytes): string {
    return derive_something($bytes);
});

$privateKey->zero();
$signatureService->sign($privateKey, $message); // throws SecretKeyMaterialZeroedException
```

`zero()` is a contract a caller invokes explicitly, not a guarantee delivered by the destructor. Applications that require bounded key-material lifetimes — session-scoped bunker signers, for example — must call `$privateKey->zero()` explicitly at the end of the session rather than relying on garbage collection. See [ADR-0015](docs/adr/0015-zero-is-a-contract-not-a-guarantee-via-destruction.md).

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

## Performance

### Native FFI Acceleration

The library can use the system's native `libsecp256k1` C library via PHP's
FFI extension for cryptographic operations. This provides significant
performance gains for applications performing bulk signature verification
(relays, indexers) or threshold-signature math (FROSTR signers).

Operations routed through `LibSecp256k1Ffi` when the library is loaded:

- `sign` — BIP340 Schnorr sign
- `verify` — BIP340 Schnorr verify
- `derivePublicKey` — secret to 32-byte x-only pubkey
- `derivePublicKeyCompressed` — secret to 33-byte compressed pubkey (parity-aware)
- `computeSharedX` — x-only ECDH for NIP-44 conversation keys
- `pointMulCompressed` — arbitrary-base scalar multiplication on a compressed point
- `pointAddCompressed` — group addition of two compressed points

The last three primitives are what threshold-signature consumers like
[`innis/frostr-core`](https://github.com/innis-xyz/frostr-core) need for
FROST partial signing, partial ECDH and dealer setup. With FFI loaded,
those operations run roughly 60× faster than the pure-PHP fallback.

To install the native library:

```bash
# Ubuntu/Debian
sudo apt install libsecp256k1-1

# macOS (Homebrew)
brew install libsecp256k1
```

No code changes are required. The library detects and uses the native
implementation automatically, falling back to pure PHP when unavailable.

## Architecture

This package follows Clean Architecture principles with strict layer separation:

- **Domain Layer**: Pure business logic, immutable entities and value objects (cryptographic library is the sole external dependency, used directly by identity value objects)
- **Application Layer**: Port interfaces for external service integration
- **Infrastructure Layer**: Implementations of the domain and application interfaces, grouped by concern (`Crypto/`, `Encoding/`, `Http/`, `Reference/`)

## Architecture decisions

Design rationale lives in [`docs/adr/`](docs/adr/) as immutable Architecture Decision Records — read these before "correcting" a choice that reads like a smell. Each record states the context, the decision, and what it forbids.

| ADR | Decision |
|-----|----------|
| [0001](docs/adr/0001-anticipated-outcomes-returned-faults-thrown.md) | Anticipated outcomes are returned (`?T` / `*Failure`); faults are thrown |
| [0002](docs/adr/0002-nostrexception-roots-nostr-faults-only.md) | `NostrException` roots Nostr faults; consumers root their own |
| [0003](docs/adr/0003-value-objects-keep-getter-methods-not-property-hooks.md) | Value objects keep `getX()` methods, not property hooks |
| [0004](docs/adr/0004-publickey-eventid-signature-stay-separate.md) | `PublicKey`, `EventId`, and `Signature` stay separate types |
| [0005](docs/adr/0005-timestamp-now-direct-clockinterface-when-time-under-test.md) | `Timestamp::now()` reads the clock; `ClockInterface` is injected only where elapsed time is under test |
| [0006](docs/adr/0006-tagtype-is-a-value-object-not-a-backed-enum.md) | `TagType` is a value object, not a backed `enum` (open vocabulary) |
| [0007](docs/adr/0007-subscriptioncollection-does-not-extend-typedcollection.md) | `SubscriptionCollection` does not extend `TypedCollection` |
| [0008](docs/adr/0008-domain-services-static-when-pure-injected-when-collaborator.md) | Domain services are `static` when pure, injected when they have a collaborator |
| [0009](docs/adr/0009-keysecuritybyte-frombyte-throws-on-unknown.md) | `KeySecurityByte::fromByte` throws on an unrecognised byte |
| [0010](docs/adr/0010-relayurl-fromstring-rejects-more-than-malformed-syntax.md) | `RelayUrl::fromString` rejects more than malformed syntax |
| [0011](docs/adr/0011-contentreferencetagbuilder-emits-q-tag-only-for-quotes.md) | `ContentReferenceTagBuilder` emits a `q` tag only for a quoted event (no `e` mention) |
| [0012](docs/adr/0012-event-does-not-cache-its-computed-id.md) | `Event` does not cache its computed id |
| [0013](docs/adr/0013-secp256k1signer-sign-rejects-non-32-byte-messages.md) | `Secp256k1Signer::sign` rejects any message that is not exactly 32 bytes |
| [0014](docs/adr/0014-nip44cipher-has-no-public-encryptwithnonce.md) | `Nip44Cipher` has no public `encryptWithNonce`; nonce stays behind a port |
| [0015](docs/adr/0015-zero-is-a-contract-not-a-guarantee-via-destruction.md) | `zero()` is a contract, not a guarantee via destruction |

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

`FilterHasher::hash` (PHP `@innis/nostr-core`) and `hashFilters` (TypeScript `@innis/nostr-core`) compute a stable identity for a NIP-01 `REQ` filter set, suitable as a subscription dedup key. Both follow the same canonicalisation spec:

1. Represent the filter set as an ordered list of filters in wire form (PHP: `Filter::toArray()`; TS: `NostrFilter` objects).
2. Canonicalise recursively:
   - **object / map** — sort keys ascending (bytewise), then canonicalise each value;
   - **array / list** — canonicalise each element, then sort the elements ascending (bytewise) by their canonical encoding;
   - **scalar** — left unchanged.
3. Encode the canonicalised structure as **ASCII-safe JSON**: compact (no inserted whitespace), `/` left unescaped (`JSON_UNESCAPED_SLASHES`), and every non-ASCII code unit escaped as a lowercase `\uXXXX` (astral characters as UTF-16 surrogate pairs) — i.e. `json_encode` *without* `JSON_UNESCAPED_UNICODE`. The TS side post-escapes `JSON.stringify` to match.
4. The hash is the lowercase-hex **SHA-256** of that canonical string.

Because object keys, array elements, and the filters themselves are all sorted, two filter sets that select the same events produce the same digest regardless of how they were ordered on input.

### Cross-language parity

The two implementations are **byte-for-byte identical for every input**, including non-ASCII `search` strings and tag-filter values. Making the canonical form ASCII-safe (step 3) is the mechanism: with no raw non-ASCII bytes, bytewise / UTF-8-byte / UTF-16-code-unit / code-point collation all coincide, so both runtimes sort and encode identically — closing both the `json_encode`-vs-`JSON.stringify` escaping gap (`U+2028` / `U+2029`) and the UTF-8-vs-UTF-16 sort gap (astral characters such as emoji).

Parity is locked by shared conformance anchors asserted in both test suites — equivalent inputs must hash to the same digest in both:

| Input | SHA-256 digest |
|---|---|
| `[]` (empty set) | `4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945` |
| `[{}]` (one empty filter) | `e10808d43975dc400731053386849f864f297e6c4f7519c380f3dbaf7067a840` |
| `[{ "kinds": [2,1], "limit": 5 }]` | `a34519033f2032b87a019ef94f4be40fc1ab6a621d2b66c55b0d386c3e576587` |
| `[{ "search": "U+2028" }]` | `aee96085e5802e7b70a145ffdf6aa7e2335469aa223be66c79c9ad1699ecd7f2` |
| `[{ "search": "U+1F600" }]` (astral) | `ac283a84cb87cd19a956f552a82cb9155fc1a980d576356c4d987e71710a4dd3` |
| `[{ "#t": ["U+1F600","U+1F4A9"] }]` (astral sort) | `a47382ebe89a655c3d9d1e27a1e5e445ca0dd4f5348e72f518b2a98b6f77f92b` |

## License

MIT License. See LICENSE file for details.
