# 54. The Compliance suite is a purpose group that cuts across the isolation levels

## Status

Accepted

## Context

Test suites are otherwise organised strictly by isolation level — Unit (test doubles only, no external tech), Integration (drives a real implementation: FFI/libsecp256k1, a real engine), and the fast inner loop runs `test-unit`. A third PHPUnit suite, `Compliance`, does not fit that axis. It groups the tests that assert conformance to an external specification and its official vectors: BIP-340 signing/verification vectors, the NIP-44 v2 vector file, ECDH cross-implementation parity, and the wire-parser round-trip and totality sweeps.

Members of this suite sit at different isolation levels. `WireParserRoundTripComplianceTest` and `WireParserTotalityComplianceTest` drive pure parsing with test doubles (unit isolation); `Bip340ComplianceTest`, `EcdhParityComplianceTest`, and `Nip44EncryptionComplianceTest` drive the real native library (integration isolation). Grouping them by *purpose* therefore cuts across the isolation axis, which reads as a taxonomy mistake: two of these tests would, by the isolation rule, belong in the Unit suite, and `test-unit` does not run them.

## Decision

Keep `Compliance` as a distinct suite defined by what the tests *are for* — pinning conformance to an external standard and its published vectors — rather than by what they drive. The suite is run in full by `test` and `test-compliance` in CI; it is deliberately not a subset of `test-unit`.

Conformance vectors are a category a reader looks for as a unit: "where is our proof that we match BIP-340 / NIP-44?" is answered by one suite, regardless of whether a given vector set needs the native library. Splitting them across Unit and Integration by isolation level would scatter the specification-conformance story and make it harder to see, at a glance, that a spec is covered. The cost — that the two pure-parsing conformance tests are not in the fast `test-unit` loop — is small, because `test` (the ship gate) and CI run the whole suite on every push.

## Consequences

- `Compliance` is organised by purpose, not isolation, and this is intended. A contributor looking for spec-conformance coverage finds it in one place.
- The two unit-isolation members (`WireParser*ComplianceTest`) are excluded from `test-unit`; they still run under `test` and `test-compliance` in CI, so they are never unguarded.
- A new conformance-vector test joins `Compliance` regardless of whether it needs the native library. A test that is *not* about external-spec conformance is filed by isolation level as usual.
- Do not "tidy" the pure-parsing conformance tests into the Unit suite to make suites match isolation levels — it fragments the conformance story the Compliance grouping exists to keep whole.
