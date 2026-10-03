# 74. `RelayUrl` has one canonical form, shared by every implementation

## Status

Accepted

Supersedes ADR-0010. The decision that `tryFromString` canonicalises what it can and rejects what it cannot is carried forward unchanged. The rule set, the fixed-point requirement and the shared corpus are now the shared decision, nostr-adrs ADR-0002; this record holds what is particular to this package.

## Context

nostr-adrs ADR-0002 decides the canonical form of a relay URL for every implementation: which spellings are canonicalised, which are refused, that a canonical form is a fixed point, and that a corpus copied byte for byte into each implementation is the specification. `RelayUrl::tryFromString` returning `null` for more than malformed syntax reads like over-strict parsing, and that record is the reason it is not.

What remains here is how this package carries that decision: which types depend on the canonical form, where the corpus lives, and how a refusal reaches the caller.

## Decision

- `RelayUrl::tryFromString` implements nostr-adrs ADR-0002 and returns `null` for every form it refuses. The refusal is an anticipated outcome the caller must handle (ADR-0089), never a throw.
- `RelayUrl` identity is its canonical string: `equals()` compares it and `RelayUrlCollection::unique()` deduplicates by it, so two spellings of one relay are one value here and in every other implementation.
- The corpus is copied to `tests/Vectors/normalise-url.json`. `RelayUrlCorpusComplianceTest` runs every vector and checks that every canonical output is its own canonical form.
- The authority is checked before `parse_url` reads it: it must hold a host, and a port, when present, is ASCII digits only. `parse_url` reads `:+80` as port 80 and `:1e3` as port 1, and an empty authority such as `wss:///a.com` must not be read as a host, so these are refused on the text itself. An empty port (`wss://a.com:`) is dropped, as the corpus says.
- An out-of-range port is refused by `parse_url`, which returns `false` for any port above 65535; the parser itself refuses only port 0, the one out-of-range port `parse_url` returns.

## Consequences

- A rule change lands in the corpus first and then here; changing this parser alone reintroduces silent drift between implementations.
- Do not loosen the parser to accept a form the corpus refuses, and do not strip trailing slashes before trailing punctuation.
- Do not add back an upper port bound the type of `parse_url`'s result already rules out; port 0 is the bound this parser owns.
- Shared decision: nostr-adrs ADR-0002.
