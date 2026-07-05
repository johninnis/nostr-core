# 52. `Nip05Identifier` rejects a local part outside the allowed character set rather than normalising it

## Status

Accepted

## Context

A NIP-05 identifier is `<local-part>@<domain>`. The specification constrains the local part to the characters `a-z0-9-_.` — a lowercase set, with no uppercase permitted — and says nothing about normalising a non-conforming value. The domain, by contrast, is a DNS hostname and is genuinely case-insensitive, so it is lower-cased on the way in.

An identifier arriving with an upper-case local part (`Alice@example.com`) therefore sits on a fork. One option is to *repair* it — lower-case the local part so it matches — which looks friendly and mirrors the domain handling right beside it. The other is to *reject* it, because upper-case is not in the permitted set.

Repairing is unsafe here for the same reason repair is unsafe elsewhere in this package. A `.well-known/nostr.json` document keys its `names` object by the exact local part; verification is a direct lookup against that key. If the library silently lower-cases `Alice` to `alice`, it queries and matches a name the user did not write, and two distinct wire spellings collapse to one value the spec never said were equal. The safe, spec-faithful behaviour is to treat an out-of-charset local part as malformed input and refuse to construct the value.

## Decision

`Nip05Identifier::tryFromString` validates the trimmed local part against `^[a-z0-9._-]+$` and returns `null` if it does not match. The local part is **not** lower-cased or otherwise transformed; an upper-case or otherwise out-of-charset local part is rejected at the boundary. The domain is still lower-cased, because a hostname is case-insensitive by its own specification and the local part is not.

## Consequences

- An identifier whose local part contains characters outside `a-z0-9-_.` (including any upper-case letter) is refused as a returned `null`, the same anticipated-outcome stance the package takes for other malformed wire input.
- Verification against a spec-compliant `names` object is a sound exact-match lookup, because the local part is never silently rewritten into a different key.
- The asymmetry between the case-preserved local part and the lower-cased domain is deliberate: the domain is case-insensitive by its own spec, the local part is a fixed lowercase set.
- A host that must interoperate with producers emitting mixed-case local parts would add a *lenient* parse at a higher layer that lower-cases before constructing — never inside `tryFromString`, which must not fabricate a value the input did not carry. Do not "help" by lower-casing the local part here.
