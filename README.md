# Nostr Core Package

A PHP library implementing core domain entities and services for the Nostr protocol, built with Clean Architecture principles.

## Why this library?

Existing PHP Nostr libraries (nostriphant, swentel/nostr-php) are organised around individual NIPs, mixing protocol concerns, infrastructure, and application logic together. This makes them difficult to integrate into projects that follow clean architecture or domain-driven design.

This library takes a different approach:

- **Domain-first, not NIP-first.** Code is organised around domain concepts (events, identities, tags, messages) rather than NIP numbers. A single `Event` entity handles creation, signing, and verification regardless of which NIP defines the event kind.
- **Clean Architecture with strict layer separation.** Domain entities and value objects have no framework dependencies. The only external libraries in the domain layer are cryptographic (secp256k1 elliptic curve math) and bech32 encoding, which are intrinsic to Nostr identity. Infrastructure concerns (JSON serialisation, HTTP calls) live behind interfaces that consumers implement or use the provided adapters.
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

- PHP 8.1 or higher
- BCMath extension (for ECC operations)

### Optional (recommended)

- PHP FFI extension
- libsecp256k1 system library

When both are available, Schnorr signature operations (signing, verification, public key derivation) use the native C library for significantly faster performance. Without them, the library falls back to a pure-PHP implementation automatically.

## Installation

```bash
composer require innis/nostr-core
```

## Quick Start

### Key Generation

```php
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;

$keyPair = KeyPair::generate();

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

$signedEvent = $event->sign($keyPair->getPrivateKey());
```

### Message Handling

```php
use Innis\Nostr\Core\Infrastructure\Adapter\JsonMessageSerialiserAdapter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;

$serialiser = new JsonMessageSerialiserAdapter();

$eventMessage = new EventMessage($signedEvent);
$json = $eventMessage->toJson();

$deserialised = $serialiser->deserialiseClientMessage($json);
```

## Supported NIPs

| NIP | Description | Support |
|-----|-------------|---------|
| [NIP-01](https://github.com/nostr-protocol/nips/blob/master/01.md) | Basic protocol flow | Event creation, signing, verification, serialisation |
| [NIP-02](https://github.com/nostr-protocol/nips/blob/master/02.md) | Follow list | Kind 3 with contact list tags |
| [NIP-04](https://github.com/nostr-protocol/nips/blob/master/04.md) | Encrypted direct messages | Kind 4 with recipient validation |
| [NIP-05](https://github.com/nostr-protocol/nips/blob/master/05.md) | DNS-based identity | Identifier parsing and HTTP verification |
| [NIP-09](https://github.com/nostr-protocol/nips/blob/master/09.md) | Event deletion | Kind 5 with deletion tag validation |
| [NIP-10](https://github.com/nostr-protocol/nips/blob/master/10.md) | Reply conventions | Reply chain analysis with root/reply/mention markers |
| [NIP-11](https://github.com/nostr-protocol/nips/blob/master/11.md) | Relay information | Relay metadata fetching and parsing |
| [NIP-18](https://github.com/nostr-protocol/nips/blob/master/18.md) | Reposts | Kind 6/16 with embedded event extraction and quote detection |
| [NIP-19](https://github.com/nostr-protocol/nips/blob/master/19.md) | Bech32 encoding | npub, nsec, note, nprofile, nevent, naddr encoding/decoding |
| [NIP-22](https://github.com/nostr-protocol/nips/blob/master/22.md) | Comments | Kind 1111 with root/parent kind tags and reply chain analysis |
| [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) | Long-form content | Kind 30023 as parameterised replaceable events |
| [NIP-25](https://github.com/nostr-protocol/nips/blob/master/25.md) | Reactions | Kind 7 event support |
| [NIP-28](https://github.com/nostr-protocol/nips/blob/master/28.md) | Public chat | Kind 40-44 channel event types |
| [NIP-51](https://github.com/nostr-protocol/nips/blob/master/51.md) | Lists | Kind 10000 (mute) and 10002 (relay) lists |
| [NIP-57](https://github.com/nostr-protocol/nips/blob/master/57.md) | Lightning zaps | Zap request/receipt parsing, BOLT-11 amount extraction |
| [NIP-61](https://github.com/nostr-protocol/nips/blob/master/61.md) | Nutzaps | Kind 9321 cashu proof parsing and amount extraction |

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

- **Domain Layer**: Pure business logic, immutable entities and value objects (cryptographic and bech32 libraries are the sole external dependencies, used directly by identity value objects)
- **Application Layer**: Port interfaces for external service integration
- **Infrastructure Layer**: External adapters and implementations

## Dependencies

| Package | Purpose |
|---------|---------|
| `paragonie/ecc` | Pure-PHP secp256k1 elliptic curve operations (fallback when FFI unavailable) |
| `nostriphant/nip-19` | Bech32 encoding/decoding for NIP-19 entity references |
| `psr/log` | PSR-3 logger interface for infrastructure services |

## Testing

```bash
# Run tests
composer test

# Run PHPStan analysis (level 9)
composer analyse

# Fix code style
composer fix-style
```

## License

MIT License. See LICENSE file for details.
