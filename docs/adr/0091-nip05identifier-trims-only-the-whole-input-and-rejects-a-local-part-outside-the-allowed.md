# 91. `Nip05Identifier` trims only the whole input, and rejects a local part outside the allowed character set rather than normalising it

## Status

Accepted

Supersedes ADR-0052. Its decision — the local part is validated against `^[a-z0-9._-]+$` and never lower-cased or otherwise rewritten, while the domain is lower-cased — is carried forward. What is revised is the trim: ADR-0052 validated "the trimmed local part", trimming each side of the `@` on its own, so `alice @ example.com` parsed as `alice@example.com`. Only the ends of the whole input are trimmed now, and the domain must be ASCII before it is lower-cased. These rules are the shared decision, nostr-adrs ADR-0068; this record holds what is particular to this package.

## Context

nostr-adrs ADR-0068 decides for every implementation how a NIP-05 identifier is parsed: the ends of the whole input trimmed by ECMAScript's `String.prototype.trim` set and nothing else, a split at the first `@`, a local part matched against `a-z0-9-_.` exactly as written, and a domain refused if it holds any non-ASCII character before it is lower-cased.

Two PHP defaults would each break that agreement. `trim()` removes only ASCII whitespace and NUL, and a PCRE `\s` under `/u` also removes U+0085 and U+180E, which ECMAScript keeps. And `mb_strtolower` folds U+212A KELVIN SIGN into `k`, so lower-casing before checking would read an unregistrable domain as a real one.

## Decision

`Nip05Identifier::tryFromString` implements nostr-adrs ADR-0068 and returns `null` for every identifier it refuses:

- The trim is an explicit character class of the ECMAScript set, applied once to the whole input; input that is not valid UTF-8 is refused.
- The local part is validated against `^[a-z0-9._-]+$` and kept exactly as written.
- The domain is checked for non-ASCII characters before it is lower-cased, then required to be a hostname of two or more labels whose last label is not a number: neither all decimal digits nor `0x` followed by hexadecimal digits. The WHATWG URL parser reads a host that ends in such a label as an IPv4 address in any of its spellings (`127.1`, `0x7f.1`, `127.0x1`), so refusing that shape refuses every IPv4 literal, where matching only the dotted-decimal form let the others through.

## Consequences

- Verification against a `names` object is an exact-match lookup, because the local part is never rewritten into a different key.
- Do not replace the trim class with `trim()` or `\s`, and do not lower-case the domain before the ASCII check; each changes which inputs parse.
- Refusing IPv4 literals is syntax, not SSRF protection: a hostname can still resolve to a private or loopback address, and refusing that destination is the HTTP adapter's check on the resolved address (ADR-0090).
- A host that must accept mixed-case or loosely spaced input repairs it before calling `tryFromString`, which must not fabricate a value the input did not carry.
- Shared decision: nostr-adrs ADR-0068.
