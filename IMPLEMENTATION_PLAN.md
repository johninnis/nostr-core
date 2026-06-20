# nostr-core conformance to PHP-CONVENTIONS (Phase 1)

Reference: `../PHP-CONVENTIONS.md`, `../CLAUDE.md`. Bring the foundational library into line so it is the reference implementation the siblings copy. Keep the gate (`composer test` + `composer check-style`) green after every stage.

## Stage 1: Drop *Adapter suffix, regroup Infrastructure by concern
**Goal**: Rename the 13 infrastructure implementations to `<Distinguisher><RoleNoun>` and move them out of the generic `Adapter/` bucket into concern folders. Mirror the change in the test tree.
**Rename map**:
- Crypto/ — Secp256k1Signer, Secp256k1Ecdh, Nip04Cipher, Nip44Cipher, Nip49Cipher, GiftWrapper, NativeRandomBytesGenerator
- Encoding/ — JsonMessageSerialiser, Bech32Encoder
- Http/ — Nip05Verifier, Nip11Client
- Reference/ — ContentReferenceExtractor, RelayHintExtractor
**Success Criteria**: No class ends in `*Adapter`; no `Infrastructure/Adapter/` dir; `composer test` + `check-style` green.
**Status**: Complete

## Stage 2: Propagate to downstream — RELEASE-GATED (deferred)
**Finding**: every downstream repo installs nostr-core as a **released Packagist package** (`^0.3.x`), NOT a live path symlink. So core's renames are a **breaking public-API change** that cannot reach downstream until core is released. (Premature edits attempted here were reverted; downstream is green against released core.)
**Plan**: complete core Stages 1,3,4,5 → tag a new **breaking** core release (0.4.0; `^0.3` will not auto-adopt it) → then, per downstream repo, bump the constraint to `^0.4`, update the two references (`Secp256k1SignatureAdapter`→`Secp256k1Signer`, `JsonMessageSerialiserAdapter`→`JsonMessageSerialiser`), `composer update innis/nostr-core`, dump-autoload, gate. This is Phase 2/3 work, done alongside each downstream repo's own conformance.
**Status**: Deferred (blocked on core release)

## Stage 3: Dissolve the WithCryptoServices trait into an Object Mother
**Goal**: Replace `tests/Support/WithCryptoServices.php` (a trait) with a `final` `CryptoFixtures` mother whose static methods return the signer/ecdh services; update the ~22 test classes that used the trait.
**Success Criteria**: No trait used for fixtures; `composer test` green.
**Status**: Complete

## Stage 4: Add examples/
**Goal**: Ship at least one runnable example script demonstrating key signing/verifying and message (de)serialisation against the public API.
**Success Criteria**: `php examples/<script>.php` runs clean.
**Status**: Complete

## Stage 5: Error-handling audit
**Goal**: Confirm every parser of untrusted input returns (`?T`/`*Failure`) and exceptions are reserved for faults; fix any parser that throws on bad external input.
**Success Criteria**: Audit documented; any changes keep `composer test` green.
**Status**: Complete (audit: conformant; no code change)
