# Nostr Core Package

[![CI](https://github.com/johninnis/nostr-core/actions/workflows/ci.yml/badge.svg)](https://github.com/johninnis/nostr-core/actions/workflows/ci.yml)

A PHP library implementing core domain entities and services for the Nostr protocol, built with Clean Architecture principles.

Code is organised around domain concepts (events, identities, tags, messages) rather than NIP numbers: an unsigned draft is a `Rumour` value object, and signing it mints the signed `Event` entity, regardless of which NIP defines the event kind. Domain entities and value objects are immutable, services are stateless, and the package provides building blocks for relays, clients, and web applications without imposing architectural decisions on consumers. See [ADR-0100](docs/adr/0100-code-is-organised-domain-first-and-a-classs-layer-is-decided-by-what-its-implementation.md) for the organising rationale and [ADR-0103](docs/adr/0103-rumour-is-the-unsigned-event-value-object-event-composes-it-and-signing-mints.md) for the rumour/event split.

> [!IMPORTANT]
> **Install the native `libsecp256k1` library (via the `ffi` extension) for any server-side or long-lived signer.** When it is absent, signing, public-key derivation, and ECDH fall back to a pure-PHP implementation that is **not constant-time** and cannot be made so. A local or co-located attacker able to measure signing/ECDH timing could in principle recover private-key material, so a relay, a NIP-46 remote signer/bunker, or any service that repeatedly signs with a fixed key should confirm the native path is active before deploying, and a non-CLI host (php-fpm, a web SAPI) needs `ffi.enable=true` or `opcache.preload`, because PHP's default `ffi.enable=preload` gives it no FFI. The pure-PHP fallback is intended for portability and low-exposure client use, not a hardened signing oracle. See [Security](#security) and [SECURITY.md](SECURITY.md#security-properties).

## Features

- Broad Nostr protocol coverage, listed NIP by NIP below
- Clean Architecture with strict layer separation
- Domain-driven design with pure business logic
- Comprehensive cryptographic support using secp256k1
- Native libsecp256k1 FFI acceleration covering BIP340 sign/verify, x-only pubkey derivation, and NIP-44 ECDH — automatic pure-PHP fallback when the C library is unavailable (the fallback is **not constant-time**; see [Security](#security))
- Bech32 *and* bech32m encoding/decoding via a single `Bech32Codec` (NIP-19 prefixes plus BIP-350 bech32m variants), selected through the `Bech32Variant` enum
- NIP-19 entities as distinct value objects (`Npub`, `Note`, `Nprofile`, `Nevent`, `Naddr`) behind `Nip19EntityInterface` — each carries only the fields its variant has, and encodes itself
- Content-reference extraction (event, pubkey, relay and quote references from tags and content) and reply-chain analysis
- Typed, immutable domain collections and a subscription model
- NIP compliance validation for NIP-01, NIP-02, NIP-04 and NIP-09 events (`NipComplianceValidator`)
- Event acceptance validation (`EventValidator`) against the host's `EventLimits` — content length, tag count and a `created_at` window, which NIP-01 leaves to the relay and NIP-11 advertises; defaults 65536 characters, 5000 tags, ten years behind to one hour ahead (see [ADR-0131](docs/adr/0131-event-limits-are-host-acceptance-policy-injected-into-the-event-validator.md))
- Type-safe message handling with domain objects at all boundaries
- Extensive test coverage with PHPStan level 9

## Requirements

Declared in `composer.json`:

- PHP 8.4 or higher
- `ext-ctype` (`ctype_digit` in `DecimalIntegerParser`, which reads every untrusted decimal string: a coordinate's kind, the `expiration` and `published_at` timestamps, a file's `size` and a zap request's `amount`)
- `ext-filter` (`FILTER_VALIDATE_INT` in the same parser, which refuses a decimal that overflows an integer)
- `ext-gmp` (bignum arithmetic for the pure-PHP secp256k1 signing and ECDH path; required transitively by `paragonie/ecc`, so the package cannot install without it even on a host that always uses the native `libsecp256k1` path)
- `ext-intl` (NFKC password normalisation in NIP-49)
- `ext-mbstring` (the `mb_check_encoding` UTF-8 check on every string a value is read from: event content, tags, subscription ids, filter values and search terms, coordinates, external content ids and event metadata, and every NIP-04 and NIP-44 plaintext, encrypted or decrypted, see [ADR-0116](docs/adr/0116-a-plaintext-that-is-not-utf-8-is-refused-by-encryption-and-is-a-decryption-failure.md); `mb_strlen` for `EventContent::getLength` and a subscription id's 64-character limit; `mb_strtolower` for `Hashtag` and search-filter matching on untrusted event content)
- `ext-openssl` (AES-256-CBC for NIP-04)
- `ext-sodium` (XChaCha20-Poly1305 AEAD for NIP-49, the constant-time hex codec for keys, ids and signatures, see [ADR-0120](docs/adr/0120-identity-hex-is-converted-by-sodiums-constant-time-codec-and-hexcodec-only-validates.md), the constant-time scalar comparison in `PrivateKey`, and `sodium_memzero` on secret buffers, the NIP-44 message keys included; NIP-44 itself encrypts with `sodium_compat`'s ChaCha20 and authenticates with `hash_hmac`)
- `paragonie/ecc` (pure-PHP secp256k1 fallback)
- `paragonie/sodium_compat` (raw ChaCha20 keystream with explicit block counter for NIP-44, which `ext-sodium` does not expose)

Declared under `suggest` in `composer.json`:

- `ext-ffi` is needed only by NIP-49, which has no pure-PHP fallback. The `Secp256k1Signer::create()` / `Secp256k1Ecdh::create()` factories use it to reach `libsecp256k1` when it is loaded, and without it fall back to the pure-PHP path, so consumers who do not use NIP-49 can run without `ext-ffi` and still construct the adapters through `::create()`.

### Optional system libraries

- `libsecp256k1` — when present, Schnorr signing, verification, public-key derivation, and NIP-44 ECDH use the native C library (reached via `ext-ffi`) for significantly faster performance. Without it, the library falls back to a pure-PHP implementation via `paragonie/ecc` automatically. That fallback is **not constant-time**, so installing the native library is a security measure as well as a performance one for any server-side or long-lived signer; see [Security](#security).
- `libsodium` — required by NIP-49 scrypt derivation, which calls `crypto_pwhash_scryptsalsa208sha256_ll` through `ext-ffi`. Typically already installed wherever `ext-sodium` is. A non-CLI SAPI under PHP's default `ffi.enable=preload` reaches neither library unless the host sets `ffi.enable=true` or preloads this library through `opcache.preload`; see [Native FFI Acceleration](#native-ffi-acceleration).

- **Running the test suite requires `ext-ffi` and `libsecp256k1`**, even though *using* the library does not. The NIP-49 tests need FFI unconditionally (see [ADR-0039](docs/adr/0039-nip-49-scrypt-requires-ffi-and-libsodium-with-no-pure-php-fallback.md)) and the native-path crypto tests need the shared library. On a host without them, run `composer test-no-ffi`, which excludes the `ffi` group and is the same command CI runs to verify the pure-PHP deployment.

## Installation

```bash
composer require innis/nostr-core
```

## Quick Start

Cryptographic operations (signing, verification, public-key derivation, ECDH) are exposed as Domain service interfaces with Infrastructure implementations. The `Secp256k1Signer` and `Secp256k1Ecdh` pick an FFI-accelerated path when `libsecp256k1` is available and fall back to pure PHP otherwise; both paths produce byte-identical results, so callers do not need to care which one runs for correctness. The two are **not** equivalent for timing side channels, though — see [Security](#security) before running a server-side or long-lived signer on the pure-PHP path.

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

`Rumour::draft` builds an unsigned event (content, tags and `created_at` default to empty, none and now, and an addressable kind with no `d` tag gains `["d", ""]`); signing it mints a signed `Event`. A `RumourFactory`, constructed for the author of every draft it makes, adds the drafts that need tags built for them — kind 1 notes and replies, which tag what their content mentions, reposts, reactions, deletion requests, NIP-17 private messages and reactions, NIP-42 and NIP-98 auth, file metadata and long-form articles. Every draft is stamped with the current instant; `withCreatedAt()` restamps one that needs a fixed `created_at`:

```php
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;

$rumour = Rumour::draft(
    $keyPair->getPublicKey(),
    EventKind::fromInt(EventKind::TEXT_NOTE),
    EventContent::fromString('Hello Nostr!'),
);

$signedEvent = $rumour->sign($keyPair, $signatureService);

$signedEvent->verify($signatureService); // bool

$reply = new RumourFactory($keyPair->getPublicKey())
    ->createReply($signedEvent, EventContent::fromString('A reply'))
    ->sign($keyPair, $signatureService);
```

`Event::toJson()` always encodes the fields the event holds, never the string it was parsed from, so a relayed event carries exactly what was verified — see [ADR-0076](docs/adr/0076-an-event-serialises-its-fields-never-the-bytes-it-was-parsed-from.md).

### NIP-44 Encryption

`ConversationCipher` encrypts to and decrypts from a peer in one call: it derives the NIP-44 conversation key from your private key and the peer's public key, uses it once and wipes it. Deriving the key needs an ECDH service; `Secp256k1Ecdh::create()` follows the same FFI-or-fallback pattern as the signature adapter:

```php
use Innis\Nostr\Core\Domain\Service\ConversationCipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;

$cipher = new ConversationCipher(new Nip44Cipher(), Secp256k1Ecdh::create());

$ciphertext = $cipher->encrypt('Hello in private', $senderPrivateKey, $recipientPublicKey);
$plaintext = $cipher->decrypt($ciphertext, $recipientPrivateKey, $senderPublicKey);
```

It is the same capability `GiftWrapper` encrypts with (see [ADR-0109](docs/adr/0109-giftwrapper-takes-a-conversation-cipher-a-signer-and-its-envelope-factory.md)). `Nip44Cipher` underneath takes a `ConversationKey` directly, for a caller that already holds one.

Nonce generation is injected: `Nip44Cipher` accepts an optional `RandomBytesGeneratorInterface`, defaulting to `NativeRandomBytesGenerator` (PHP's `random_bytes`) for production. There is no public `encryptWithNonce` method — see [ADR-0014](docs/adr/0014-nip44cipher-has-no-public-encryptwithnonce-nonce-generation-stays-behind-a-port.md).

`Nip44Cipher` also takes a maximum plaintext length, defaulting to `Nip44Cipher::DEFAULT_MAX_PLAINTEXT_LENGTH` (262144 bytes, 256 KiB). `encrypt` refuses a longer plaintext, and `decrypt` refuses a payload longer than the base64 length that maximum produces before decoding it. A host that must carry more passes a larger maximum, up to NIP-44's own 4294967295 — see [ADR-0117](docs/adr/0117-nip-44-carries-the-extended-length-prefix-up-to-a-configurable-maximum-that-defaults.md).

Always construct the adapters through their `::create()` factories. Direct instantiation via `new Secp256k1Signer(null, ...)` or `new Secp256k1Ecdh(null)` exists for dependency injection and testing but stays on the pure-PHP path regardless of whether `libsecp256k1` is installed.

### Message Handling

```php
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;

$json = new EventMessage($signedEvent)->toJson();

$anyClientMessage = ClientMessage::tryFromJson($json);
$eventMessage = EventMessage::tryFromJson($json);
```

`ClientMessage::tryFromJson` and `RelayMessage::tryFromJson` parse a message of unknown type and return the leaf its tag names, or `null`. Called on a leaf, the same method returns that leaf or `null` for any other tag — see [ADR-0075](docs/adr/0075-the-protocol-message-hierarchy-is-an-inheritance-sum-type-that-parses-and-serialises.md).

### NIP-19 Entities

Each NIP-19 entity is its own `final readonly` value object — `Npub`, `Note`, `Nprofile`, `Nevent`, `Naddr` — implementing `Nip19EntityInterface`, which declares `type(): Nip19EntityType` and `toBech32(): string`. There is no `encode` on the codec: an entity is minted through its own named constructor and encodes itself. An `npub` or `note` is the bech32 form of a `PublicKey` or `EventId`, which encode it themselves (`PublicKey::toBech32()`, `EventId::toBech32()`), and `Npub` and `Note` delegate to them, so each NIP-19 string has exactly one encoder.

```php
use Innis\Nostr\Core\Domain\Service\Nip19Codec;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Npub;

echo Npub::fromPublicKey($publicKey)->toBech32(); // npub1...

$nevent = Nevent::tryFromEventId($eventId, $relays, author: $publicKey, kind: $kind)
    ?? throw new RuntimeException('TLV payload exceeds the encodable size');

echo $nevent->toBech32(); // nevent1...
```

`Npub::fromPublicKey` and `Note::fromEventId` carry no optional records and cannot fail, so they are `from`; the three TLV entities are `try*` because an oversized payload has no encoding (see [ADR-0051](docs/adr/0051-named-constructors-follow-the-from-tryfrom-split.md)). The optional records mirror the spec: `nevent` takes relays, author, and kind, `nprofile` takes relays, and `naddr` mandates author and kind, so `Naddr::tryFromCoordinate` takes a whole `EventCoordinate` rather than letting either go missing. Following NIP-19 and NIP-01, an `EventCoordinate` (and so an `naddr`) addresses a replaceable kind with an empty identifier and an addressable kind with any identifier, including the empty one; every other kind is rejected.

Decoding a string whose prefix you do not know goes through the codec, which returns the interface. Because each variant carries only the fields it has, dispatch is on the type — there are no nullable getters to interrogate:

```php
$entity = Nip19Codec::decodeEntity($input); // ?Nip19EntityInterface

$publicKey = match (true) {
    $entity instanceof Npub, $entity instanceof Nprofile => $entity->getPublicKey(),
    default => null,
};
```

When you already know the prefix, decode with the type that owns it instead: `PublicKey::tryFromBech32` for an `npub`, `EventId::tryFromBech32` for a `note`, and `Nprofile::tryFromBech32`, `Nevent::tryFromBech32` or `Naddr::tryFromBech32` for the TLV entities. For the narrower "give me whatever event this string points at" question, `parseEventReference` accepts a raw hex id, a `note`, an `nevent`, an `naddr` (its first relay kept as the coordinate's relay hint), or a `kind:pubkey:d` coordinate string, and returns `EventId|EventCoordinate|null`, as `parseEventOrAddressRef` does in the TypeScript core. Rationale for the split — and for `Nip19EntityType` gaining a `Note` case so five entities map to five cases — is in [ADR-0082](docs/adr/0082-nip-19-entities-are-distinct-value-objects-and-an-naddr-addresses-any-replaceable.md); the TLV codec is an internal value object per [ADR-0104](docs/adr/0104-the-nip-19-tlv-framing-is-an-internal-value-object-beside-the-entities-and-enforces.md).

### Verifying Zap Receipts (NIP-57)

`ZapReceipt::tryFromEvent` parses a kind 9735 event carrying exactly one signed kind 9734 zap request and one readable BOLT-11 amount that the request agrees with, and returns `null` for anything else. It authenticates nothing — its `getSenderPubkey()` is the zap request's author, attacker-chosen until checked. `ZapReceiptVerifier` applies NIP-57 Appendix F to the parsed receipt, returning `null` on success or a `ZapReceiptVerificationFailure` case naming what failed:

```php
use Innis\Nostr\Core\Domain\Service\ZapReceiptVerifier;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;

$receipt = ZapReceipt::tryFromEvent($receiptEvent) ?? throw new RuntimeException('Not a zap receipt');

$failure = new ZapReceiptVerifier($signatureService)->verify($receipt, $lnurlProviderPubkey, $expectedLnurl);

if (null !== $failure) {
    throw new RuntimeException('Zap receipt rejected: '.$failure->value);
}
```

**You must supply the LNURL provider pubkey, and it is the root of trust.** This package never fetches LNURL configuration, so it cannot discover that key — with the wrong one, a receipt from *any* provider verifies. Read the `nostrPubkey` from the recipient's own LNURL endpoint. The verifier checks the receipt's signature *and* the embedded zap request's signature; the latter is not an Appendix F requirement, but without it every Appendix F condition can hold while the provider attributes the zap to an arbitrary sender. See [ADR-0079](docs/adr/0079-a-zap-receipt-holds-the-one-zap-request-it-carries-and-verification-takes-the-receipt.md) and [SECURITY.md](SECURITY.md#what-this-library-does-not-provide).

### Password-Encrypted Private Keys (NIP-49)

The NIP-49 adapter takes the password as a `Closure(): string` rather than a raw string. It invokes the closure exactly once and `sodium_memzero`s both the revealed password and its NFKC-normalised copy on the way out:

```php
use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip49WorkFactor;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Cipher;

$adapter = Nip49Cipher::create(new Nip49WorkFactor(encryptLogN: 16, maxDecryptLogN: 22));
$privateKey = PrivateKey::generate();

$ncryptsec = $adapter->encrypt(
    $privateKey,
    static fn (): string => readPasswordFromUser(),
    KeySecurityByte::NotKnownInsecure,
);

$stored = (string) $ncryptsec; // ncryptsec1...

$decoded = Ncryptsec::tryFromString($stored) ?? throw new RuntimeException('Malformed ncryptsec');
$recovered = $adapter->decrypt($decoded, static fn (): string => readPasswordFromUser());
```

That wipes the library's copy, not yours. A closure returning a variable you still hold — `static fn (): string => $password` — leaves your binding readable after the call, so the `Closure` shape pays off only when it *produces* the password without the caller retaining it: reading a prompt, unsealing it from a keystore, or decrypting it on demand. If you do keep the password in scope, zero it yourself. See [SECURITY.md](SECURITY.md#nip-49-password-as-closure-string).

Build the adapter through `Nip49Cipher::create()`, which probes for libsodium scrypt via `ext-ffi`; the bare constructor (`new Nip49Cipher(...)`) is for dependency injection and tests. NIP-49 has no pure-PHP fallback — see [ADR-0041](docs/adr/0041-nip-49-adapters-probe-libsodium-in-create-not-the-constructor.md) and [ADR-0039](docs/adr/0039-nip-49-scrypt-requires-ffi-and-libsodium-with-no-pure-php-fallback.md).

### Secret Key Lifecycle

`PrivateKey` and `ConversationKey` hold their raw bytes inside a `SecretKeyMaterial` value object. Callers that need to clear secret material from memory can call `zero()`; any subsequent operation on that key throws `SecretKeyMaterialZeroedException`. Infrastructure code that genuinely needs raw bytes uses the bounded `expose` callback, which hands the closure the secret bytes and `sodium_memzero`s them when it returns; see [ADR-0028](docs/adr/0028-secretkeymaterial-expose-hands-the-closure-a-detached-copy-so-the-wipe-is-effective.md):

```php
$derived = $privateKey->expose(static function (string $bytes): string {
    return derive_something($bytes);
});

$privateKey->zero();
$signatureService->sign($privateKey, $message); // throws SecretKeyMaterialZeroedException
```

Applications that require bounded key-material lifetimes — session-scoped bunker signers, for example — should call `$privateKey->zero()` explicitly at the end of the scope that owns the key. See [ADR-0015](docs/adr/0015-zero-is-a-contract-not-a-guarantee-via-destruction.md) for why the destructor is not relied upon.

## Examples

Runnable scripts live in [`examples/`](examples/); run one with `php examples/<name>.php`:

- [`sign_and_verify.php`](examples/sign_and_verify.php) — generate a key pair, create and sign a text note, verify it
- [`nip44_encrypt_decrypt.php`](examples/nip44_encrypt_decrypt.php) — derive a NIP-44 conversation key via ECDH and encrypt/decrypt a message
- [`nip49_password_encrypt.php`](examples/nip49_password_encrypt.php) — encrypt a private key under a password to an `ncryptsec` and recover it (requires `ext-ffi` and libsodium)
- [`giftwrap_direct_message.php`](examples/giftwrap_direct_message.php) — seal and gift-wrap a NIP-17 private message to its chat room, then unwrap the recipient's copy
- [`relay_auth_flow.php`](examples/relay_auth_flow.php) — issue a NIP-42 `Challenge`, answer it from the client side, and validate the answer with `Nip42Validator`

The `examples/` directory is covered by PHPStan and php-cs-fixer in CI, like `src` and `tests`, and CI runs every example with `composer examples`.

## Supported NIPs

| NIP | Description | Support |
|-----|-------------|---------|
| [NIP-01](https://github.com/nostr-protocol/nips/blob/master/01.md) | Basic protocol flow | Event creation, signing, verification, serialisation, the id computed over JSON that escapes a control character NIP-01 does not name as `\u00XX`, as JSON encoders and the rest of the ecosystem do ([ADR-0123](docs/adr/0123-the-id-serialiser-keeps-json-encode-s-control-character-escapes-and-writes-the-line.md)); the machine-readable `ReasonPrefix` on OK and CLOSED replies, read as `error` for a refusal that names none; `EventVersion` to decide which of two replaceable events survives |
| [NIP-02](https://github.com/nostr-protocol/nips/blob/master/02.md) | Follow list | Kind 3 with contact list tags |
| [NIP-04](https://github.com/nostr-protocol/nips/blob/master/04.md) | Encrypted direct messages | **Deprecated — use NIP-44.** Kind 4 with recipient validation; `Nip04Cipher` for AES-256-CBC encrypt/decrypt over a 32-byte ECDH shared secret. Unauthenticated and malleable; both interface methods carry `#[Deprecated]`. Shipped for kind-4 interoperability only — read [SECURITY.md](SECURITY.md#what-this-library-provides) before using it |
| [NIP-05](https://github.com/nostr-protocol/nips/blob/master/05.md) | DNS-based identity | Identifier parsing and HTTP verification |
| [NIP-09](https://github.com/nostr-protocol/nips/blob/master/09.md) | Event deletion | Kind 5 with deletion tag validation and `isDeletion()` detection; `RumourFactory::createDeletion` names a replaceable or addressable target by its coordinate and any other by its id, with its `k` tag |
| [NIP-10](https://github.com/nostr-protocol/nips/blob/master/10.md) | Reply conventions | Reply chain analysis with root/reply/mention markers |
| [NIP-11](https://github.com/nostr-protocol/nips/blob/master/11.md) | Relay information | Relay metadata fetching and parsing; `Nip11Info::getPubkey()` and `getSelf()` read the operator's and the relay's own public key |
| [NIP-17](https://github.com/nostr-protocol/nips/blob/master/17.md) | Private direct messages | Kind 14 with NIP-44 encryption and gift wrap (kind 1059); `Rumour::getChatRoom()` is the author plus the `p` tags, `RumourFactory::createPrivateMessage` and `createPrivateReaction` take the room's receivers, leaving the author out unless no one else is in it, when the room is the author alone and its one `p` tag names the author; a private reaction tags the author of the message it answers last among its `p` tags, the sender included; `GiftWrapper::wrapForChatRoom` wraps once to every member, so a room of one is wrapped once to its author; a rumour may serialise to at most 163840 bytes (`GiftWrapper::MAX_RUMOUR_LENGTH`) |
| [NIP-18](https://github.com/nostr-protocol/nips/blob/master/18.md) | Reposts | Kind 6/16 with embedded event extraction and quote detection; every `note`, `nevent` and `naddr` mention becomes a `q` tag, written by `ContentReferenceTagBuilder` for `RumourFactory::createTextNote` and `createReply` |
| [NIP-19](https://github.com/nostr-protocol/nips/blob/master/19.md) | Bech32 encoding | npub, nsec, note, nprofile, nevent, naddr — each entity a distinct value object behind `Nip19EntityInterface`, encoding itself within NIP-19's 5000 characters and decoded through `Nip19Codec`; `Bech32Codec` also supports the BIP-350 bech32m variant for non-NIP consumers (e.g. FROSTR `bfgroup1…` / `bfshare1…` / `bfonboard1…`) via the `Bech32Variant` enum |
| [NIP-22](https://github.com/nostr-protocol/nips/blob/master/22.md) | Comments | Kind 1111 with root/parent kind tags and reply chain analysis over `E`/`A`/`I` root and `e`/`a`/`i` parent tags, an `I`/`i` value read as a NIP-73 `ExternalContentId`; a comment is a reply when it resolves a root or a parent |
| [NIP-23](https://github.com/nostr-protocol/nips/blob/master/23.md) | Long-form content | Kind 30023 as addressable events; `LongformMetadata` carries its topics as a `HashtagCollection` |
| [NIP-24](https://github.com/nostr-protocol/nips/blob/master/24.md) | Extra metadata | `Hashtag` carries the lowercase rule for `t` tag values; `TagCollection::getHashtags()` and `EventContent::extractHashtags()` both return a deduplicated `HashtagCollection` |
| [NIP-25](https://github.com/nostr-protocol/nips/blob/master/25.md) | Reactions | Kind 7 event support; `RumourFactory::createReaction` names the target by its `e`, `p` and `k` tags, adds an `a` tag only for an addressable target, and writes an optional relay hint where the target can be found |
| [NIP-28](https://github.com/nostr-protocol/nips/blob/master/28.md) | Public chat | Kind 40-44 channel event types |
| [NIP-40](https://github.com/nostr-protocol/nips/blob/master/40.md) | Expiration | Expiry detection via `isExpiredAt()` against the instant the caller supplies |
| [NIP-42](https://github.com/nostr-protocol/nips/blob/master/42.md) | Authentication | AUTH messages over a non-empty `Challenge`, and `Nip42Validator` for the relay side, checking an event against the `RelayChallenge` it answers |
| [NIP-44](https://github.com/nostr-protocol/nips/blob/master/44.md) | Encrypted payloads | NIP-44 v2 encrypt/decrypt with ECDH, ChaCha20, HMAC-SHA256; plaintext of 1 to 262144 bytes by default, configurable up to 4294967295, with the extended six-byte length prefix from 65536; a payload over the ceiling is refused by its length, and one within it that starts with `#` is reported as an unsupported version |
| [NIP-45](https://github.com/nostr-protocol/nips/blob/master/45.md) | Counting | COUNT messages; the reply carries an `EventCount` that may be approximate |
| [NIP-49](https://github.com/nostr-protocol/nips/blob/master/49.md) | Private key encryption | Password-encrypted `ncryptsec` with scrypt + XChaCha20-Poly1305 |
| [NIP-50](https://github.com/nostr-protocol/nips/blob/master/50.md) | Search | Search filter support; a filter matched locally holds every term of its search in its content, and ignores a `key:value` extension |
| [NIP-51](https://github.com/nostr-protocol/nips/blob/master/51.md) | Lists | `EventKind` constants for every standard list kind NIP-51 defines (kind 3 and 10000–10102, among them 10021, 10054 and 10064) and every set kind (30000–39092); the list and set contents are read through `TagCollection` |
| [NIP-57](https://github.com/nostr-protocol/nips/blob/master/57.md) | Lightning zaps | Zap request/receipt parsing, BOLT-11 amount extraction (a mixed-case invoice has none), and Appendix F receipt verification via `ZapReceiptVerifier` (parsing alone authenticates nothing) |
| [NIP-59](https://github.com/nostr-protocol/nips/blob/master/59.md) | Gift wrap | `GiftWrapper` seals a rumour to its recipient and wraps it under an ephemeral key, verifying the gift wrap's signature and then the seal's on unwrap and returning a `GiftWrapUnwrapFailure` for a wrap it cannot open or a seal that carries any tag but `expiration` tags holding a valid NIP-40 timestamp; it unwraps a kind 1059 gift wrap and a kind 21059 ephemeral gift wrap alike; a rumour must state its `id`, which must be the one its fields hash to, may be of any kind, and may serialise to at most 163840 bytes (`GiftWrapper::MAX_RUMOUR_LENGTH`), the largest whose seal still fits NIP-44's default 262144-byte plaintext; a larger one is refused before anything is signed |
| [NIP-61](https://github.com/nostr-protocol/nips/blob/master/61.md) | Nutzaps | Kind 9321 cashu proof parsing and amount extraction; a proof's `amount` counts only when it is a JSON integer, and only a `sat` or `msat` unit states an amount |
| [NIP-65](https://github.com/nostr-protocol/nips/blob/master/65.md) | Relay list metadata | Kind 10002 (`EventKind::RELAY_LIST`); each `r` tag read by `TagReferenceExtractor` as a `RelayReference` whose `RelayMarker` is `read`, `write` or `both`, an omitted or unknown marker read as both (see [ADR-0132](docs/adr/0132-an-r-tags-marker-is-read-write-or-both.md)) |
| [NIP-70](https://github.com/nostr-protocol/nips/blob/master/70.md) | Protected events | Protected event detection via `isProtected()`, true only for a tag that is exactly `["-"]` |
| [NIP-84](https://github.com/nostr-protocol/nips/blob/master/84.md) | Highlights | Kind 9802; `HighlightMetadata` reads its `context` and `comment` tags and its source URL, the `http(s)` `r` tags marked `source` or, when none is, those not marked `mention`, read as one claim (see [ADR-0107](docs/adr/0107-a-highlights-source-is-the-web-url-it-marks-as-its-source.md)) |
| [NIP-86](https://github.com/nostr-protocol/nips/blob/master/86.md) | Relay management | `Nip86Request`, `Nip86Response` and the `Nip86Method` names |
| [NIP-92](https://github.com/nostr-protocol/nips/blob/master/92.md) | Media attachments | `FileMetadata::tryFromImetaTag` and `toImetaTag` read and write an `imeta` tag, which must carry a `url` and at least one other field |
| [NIP-94](https://github.com/nostr-protocol/nips/blob/master/94.md) | File metadata | `FileEventMetadata` is a kind 1063 event's metadata, which must state `url`, an `m` that is a MIME type, and `x` and `ox` as SHA-256 hex; `FileMetadata` reads the same tags leniently for a BUD-08 descriptor or a NIP-92 `imeta` tag, refusing an empty `url`, reading `size` only as decimal digits and `m` only as an RFC 6838 `type/subtype` (`MimeTypeParser`, stated in lowercase), and keeping any other empty field as the empty string (nostr-adrs ADR-0079); `FileMetadata::tryFrom` returns `null`, and `from` throws, for an empty `url`, a field that is not UTF-8, an `m` that is not a lowercase `type/subtype` or a negative `size` |
| [NIP-98](https://github.com/nostr-protocol/nips/blob/master/98.md) | HTTP auth | Kind 27235 validation: signature, URL, method, payload hash, timestamp tolerance, NIP-40 expiry; `NostrAuthHeaderCodec` decodes the `Authorization` header, matching the scheme token without regard to case, and encodes one only within the 4096 characters it decodes |

Beyond the NIPs listed above, `EventKind` carries named constants for a broad range of registered kinds (metadata, channels, MLS messaging, polls, cashu wallet events, live events, and more) and `EventKind::category()` answers `EventKindCategory` so consumers can classify a kind the library does not otherwise model.

## Performance

### Native FFI Acceleration

The library can use the system's native `libsecp256k1` C library via PHP's FFI extension for cryptographic operations. This provides significant performance gains for applications performing bulk signature verification (relays, indexers).

Operations routed through `LibSecp256k1Ffi` when the library is loaded:

- `sign` — BIP340 Schnorr sign
- `verify` — BIP340 Schnorr verify
- `derivePublicKey` — secret to 32-byte x-only pubkey
- `computeSharedX` — x-only ECDH, the NIP-04 shared secret and the input to a NIP-44 conversation key. `LibSecp256k1Ffi::computeSharedX` returns the 32 raw bytes as a `string`; `Secp256k1Ecdh::computeSharedX`, the `EcdhServiceInterface` callers use, wraps them in `SecretKeyMaterial` so they are wiped when released

To install the native library:

```bash
# Current Debian/Ubuntu releases (provides libsecp256k1.so.2)
sudo apt install libsecp256k1-2

# Older Debian/Ubuntu releases (provides libsecp256k1.so.1)
sudo apt install libsecp256k1-1

# macOS (Homebrew)
brew install libsecp256k1
```

No code changes are required. The library detects and uses the native implementation automatically, falling back to pure PHP when unavailable. PHP's default `ffi.enable=preload` allows FFI only on the CLI and in preloaded code, so under a non-CLI SAPI such as php-fpm the native path is silently unavailable — signing falls back to the pure-PHP implementation, which is not constant-time, and NIP-49 throws — unless the host sets `ffi.enable=true` or preloads this library through `opcache.preload`.

## Security

See [SECURITY.md](SECURITY.md) for the library's security properties, the responsibilities it leaves to the consumer, and the reasoning behind the non-obvious cryptographic decisions.

The most important operational caveat: the pure-PHP cryptography fallback used when native `libsecp256k1` is unavailable is **not constant-time** and cannot be made so (the secret-dependent scalar arithmetic runs on variable-time GMP and the interpreted Zend engine). A local or co-located attacker able to measure signing/ECDH timing could in principle recover private-key material. Any server-side or long-lived signer — a relay, a NIP-46 remote signer/bunker, or any service that repeatedly signs attacker-influenced messages with a fixed key — should install `libsecp256k1`, enable the `ffi` extension (on a non-CLI SAPI such as php-fpm, with `ffi.enable=true` or `opcache.preload`, since the default `ffi.enable=preload` allows FFI only on the CLI), and confirm the native path is active before deploying. `Secp256k1Signer::backend()` and `Secp256k1Ecdh::backend()` report which path the constructed adapter will take, so that confirmation belongs in a startup assertion rather than a deployment checklist:

```php
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Backend;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

$signer = Secp256k1Signer::create();

if (Secp256k1Backend::Native !== $signer->backend()) {
    throw new RuntimeException('Refusing to start: libsecp256k1 is unavailable and signing would run on the non-constant-time pure-PHP path');
}
```

The pure-PHP fallback is intended for portability and low-exposure client use, not a hardened signing oracle. The full analysis is in [SECURITY.md](SECURITY.md#security-properties) and [ADR-0101](docs/adr/0101-secp256k1-signing-and-ecdh-keep-a-native-ffi-path-and-a-pure-php-fallback-and-their.md).

## Architecture

This package follows Clean Architecture principles with strict layer separation:

- **Domain Layer**: Pure business logic, immutable entities and value objects. Its dependencies are the cryptography (`paragonie/ecc` and `ext-sodium`, used directly by identity value objects) and three bundled string extensions, `ext-mbstring`, `ext-ctype` and `ext-filter` (see [ADR-0100](docs/adr/0100-code-is-organised-domain-first-and-a-classs-layer-is-decided-by-what-its-implementation.md))
- **Application Layer**: Port interfaces for external service integration, and the services that orchestrate one (`Nip42Validator`, which reads an injected clock around the domain's `Nip42EventChecker`, and `Nip98Validator`, which reads an injected clock and a replay guard around the domain's `Nip98EventChecker`; `Nip05Verifier`, `Nip11Fetcher`, each calling the injected HTTP port)
- **Infrastructure Layer**: Implementations of the domain and application interfaces that reach external technology, grouped by concern (`Crypto/`, `Time/`)

## Architecture decisions

Design rationale lives in [`docs/adr/`](docs/adr/) as immutable, sequentially-numbered Architecture Decision Records — read these before "correcting" a choice that reads like a smell. Each record states the context, the decision, and what it forbids; the filenames are the index.

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

# Spec-vector and cross-language parity suite
composer test-compliance

# What a host without ext-ffi and libsecp256k1 can run (the CI pure-PHP leg)
composer test-no-ffi

# PHPStan analysis (level 9)
composer analyse

# Fix code style / check it without writing
composer fix-style
composer check-style

# Apply or check the Rector 8.4 modernisation set
composer rector
composer check-rector

# Run every script in examples/, failing on the first that fails
composer examples
```

CI runs the full gate on PHP 8.4 and 8.5, plus a separate leg with `ext-ffi` disabled and no `libsecp256k1` that runs `test-no-ffi` — the gmp-only deployment the Requirements section offers is verified rather than assumed.

## Filter-set hash

`FilterHasher::hash` computes a stable, order-independent identity for a NIP-01 `REQ` filter set, suitable as a subscription dedup key. Filter sets that differ only in the order of their filters, of the fields within a filter, or of the values within a list hash to the same digest; any other difference, even one that selects the same events (a redundant value, a different spelling of an empty window), gives a different digest. The digest is byte-for-byte identical to the TypeScript sibling's `hashFilters` for every input — including non-ASCII `search` strings and tag-filter values.

```php
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Service\FilterHasher;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;

$filters = [Filter::from(kinds: EventKindCollection::fromInts([1]))];

$key = FilterHasher::hash(...$filters); // lowercase-hex SHA-256
```

The canonicalisation contract and the cross-language parity rationale are recorded in [ADR-0020](docs/adr/0020-filterhasher-canonicalises-to-ascii-safe-json-for-byte-identical-cross-language-hashes.md); the conformance anchors that lock the two runtimes together are asserted in both packages' test suites.

## License

MIT License. See LICENSE file for details.
