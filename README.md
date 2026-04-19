# Nostr Core Package

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
- Native libsecp256k1 FFI acceleration with automatic pure-PHP fallback
- Full NIP compliance validation
- Type-safe message handling with domain objects at all boundaries
- Optimised tag lookups via lazy indexing
- Extensive test coverage with PHPStan level 9

## Requirements

Declared in `composer.json`:

- PHP 8.3 or higher
- `ext-intl` (NFKC password normalisation in NIP-49)
- `ext-sodium` (NIP-44 and NIP-49 AEAD, `sodium_memzero`)

Used by the library but not declared as hard requirements, because several code paths are optional and the recommended typical usage will load them anyway:

- `ext-gmp` is needed by the pure-PHP signing and ECDH fallback (the documented path when `libsecp256k1` is unavailable). If you know you always have `libsecp256k1` installed and never invoke the pure-PHP path, this extension is not touched.
- `ext-mbstring` is needed by the search-filter matcher, `EventContent::getLength`, and the bech32 TLV decoder. Most consumers will hit one of these.
- `ext-ffi` is needed by NIP-49 (unconditionally) and by the `Secp256k1SignatureAdapter::create()` / `Secp256k1EcdhAdapter::create()` factories (for the `libsecp256k1` probe). Consumers who do not use NIP-49 and who construct the adapters directly with `new Secp256k1SignatureAdapter(null, ...)` / `new Secp256k1EcdhAdapter()` can run without `ext-ffi` at all and stay on the pure-PHP path.
- `libsodium` system library, reachable via FFI, is required for NIP-49 scrypt. Typically already installed wherever `ext-sodium` is installed.

### Optional (recommended)

- `libsecp256k1` system library

When present, Schnorr signing, verification, public-key derivation, and NIP-44 ECDH use the native C library for significantly faster performance. Without it, the library falls back to a pure-PHP implementation via `paragonie/ecc` automatically.

## Installation

```bash
composer require innis/nostr-core
```

## Quick Start

Cryptographic operations (signing, verification, public-key derivation, ECDH) are exposed as Domain service interfaces with Infrastructure adapters. The `Secp256k1SignatureAdapter` and `Secp256k1EcdhAdapter` pick an FFI-accelerated path when `libsecp256k1` is available and fall back to pure PHP otherwise — callers do not need to care.

### Key Generation

```php
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1SignatureAdapter;

$signatureService = Secp256k1SignatureAdapter::create();
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

$signedEvent = $event->sign($keyPair->getPrivateKey(), $signatureService);

$signedEvent->verify($signatureService); // bool
```

### NIP-44 Encryption

Deriving a conversation key needs an ECDH service. `Secp256k1EcdhAdapter::create()` follows the same FFI-or-fallback pattern as the signature adapter:

```php
use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Infrastructure\Adapter\Nip44EncryptionAdapter;
use Innis\Nostr\Core\Infrastructure\Adapter\Secp256k1EcdhAdapter;

$ecdhService = Secp256k1EcdhAdapter::create();
$conversationKey = ConversationKey::derive(
    $senderPrivateKey,
    $recipientPublicKey,
    $ecdhService,
);

$encryption = new Nip44EncryptionAdapter();
$ciphertext = $encryption->encrypt('Hello in private', $conversationKey);
$plaintext = $encryption->decrypt($ciphertext, $conversationKey);
```

Always construct the adapters through their `::create()` factories. Direct instantiation via `new Secp256k1SignatureAdapter(null, ...)` or `new Secp256k1EcdhAdapter()` exists for dependency injection and testing but stays on the pure-PHP path regardless of whether `libsecp256k1` is installed.

### Message Handling

```php
use Innis\Nostr\Core\Infrastructure\Adapter\JsonMessageSerialiserAdapter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;

$serialiser = new JsonMessageSerialiserAdapter();

$eventMessage = new EventMessage($signedEvent);
$json = $eventMessage->toJson();

$deserialised = $serialiser->deserialiseClientMessage($json);
```

### Password-Encrypted Private Keys (NIP-49)

The NIP-49 adapter takes the password as a `Closure(): string` rather than a raw string. The adapter invokes the closure exactly once, `sodium_memzero`s the revealed password before the method returns, and the caller never has to maintain a password binding in its own scope:

```php
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Adapter\Nip49EncryptionAdapter;

$adapter = new Nip49EncryptionAdapter();
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

**`zero()` is a contract, not a guarantee via destruction.** `SecretKeyMaterial`'s destructor does call `zero()` as defence-in-depth, but PHP's garbage collector runs on refcount-zero, which may never happen for keys captured in long-lived closures, static state, exception trace frames, or cyclic references. Applications that require bounded key-material lifetimes — session-scoped bunker signers, for example — must call `$privateKey->zero()` explicitly at the end of the session. Treat the destructor as cleanup-of-last-resort, not as the primary wipe mechanism.

## Supported NIPs

| NIP | Description | Support |
|-----|-------------|---------|
| [NIP-01](https://github.com/nostr-protocol/nips/blob/master/01.md) | Basic protocol flow | Event creation, signing, verification, serialisation |
| [NIP-02](https://github.com/nostr-protocol/nips/blob/master/02.md) | Follow list | Kind 3 with contact list tags |
| [NIP-04](https://github.com/nostr-protocol/nips/blob/master/04.md) | Encrypted direct messages | Kind 4 with recipient validation |
| [NIP-05](https://github.com/nostr-protocol/nips/blob/master/05.md) | DNS-based identity | Identifier parsing and HTTP verification |
| [NIP-09](https://github.com/nostr-protocol/nips/blob/master/09.md) | Event deletion | Kind 5 with deletion tag validation and `isDeletion()` detection |
| [NIP-10](https://github.com/nostr-protocol/nips/blob/master/10.md) | Reply conventions | Reply chain analysis with root/reply/mention markers |
| [NIP-11](https://github.com/nostr-protocol/nips/blob/master/11.md) | Relay information | Relay metadata fetching and parsing |
| [NIP-17](https://github.com/nostr-protocol/nips/blob/master/17.md) | Private direct messages | Kind 14 with NIP-44 encryption and gift wrap (kind 1059/1060) |
| [NIP-18](https://github.com/nostr-protocol/nips/blob/master/18.md) | Reposts | Kind 6/16 with embedded event extraction and quote detection |
| [NIP-19](https://github.com/nostr-protocol/nips/blob/master/19.md) | Bech32 encoding | npub, nsec, note, nprofile, nevent, naddr encoding/decoding |
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

The library can use the system's native `libsecp256k1` C library via PHP's FFI extension for cryptographic operations. This provides significant performance gains for applications performing bulk signature verification, such as relays or indexers.

To install the native library:

```bash
# Ubuntu/Debian
sudo apt install libsecp256k1-1

# macOS (Homebrew)
brew install libsecp256k1
```

No code changes are required. The library detects and uses the native implementation automatically, falling back to pure PHP when unavailable.

## Architecture

This package follows Clean Architecture principles with strict layer separation:

- **Domain Layer**: Pure business logic, immutable entities and value objects (cryptographic library is the sole external dependency, used directly by identity value objects)
- **Application Layer**: Port interfaces for external service integration
- **Infrastructure Layer**: External adapters and implementations

## Dependencies

| Package | Purpose |
|---------|---------|
| `paragonie/ecc` | Pure-PHP secp256k1 elliptic curve operations (fallback when FFI unavailable) |
| `paragonie/sodium_compat` | ChaCha20 primitives used by NIP-44 |
| `psr/log` | PSR-3 logger interface for infrastructure services |

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

## License

MIT License. See LICENSE file for details.
