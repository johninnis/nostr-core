# 10. `RelayUrl::fromString` canonicalises, and rejects what it cannot canonicalise

## Status

Accepted

## Context

A URL parser is expected to reject only syntactically malformed input. `RelayUrl::fromString` returns `null` for more than that — a path that repeats the host, a doubled slash, a percent-encoded space, an out-of-range port — which reads like over-strict parsing or a set of accidental rejections.

It is neither. `RelayUrl` identity is by *canonical form*: `equals()` compares the normalised URL string, and `RelayUrlCollection::unique()` deduplicates by it. Two wire spellings of the same relay must therefore collapse to one value, or a relay list carries the same relay twice and a connection pool opens two sockets to it. That requirement is what `fromString` serves, and it is why the parser does two things a bare syntax check does not.

## Decision

`fromString` canonicalises the differences that are unambiguous, and rejects the ones it cannot resolve without guessing.

- **It canonicalises unambiguous variance.** Scheme and host are lower-cased, a default port (`443` for `wss`, `80` for `ws`) is dropped, and a trailing `/` (and trailing `,.;!`) is stripped. These are spellings that provably denote the same relay, so they are normalised to one form rather than rejected — `equals()` and `unique()` then treat them as equal.
- **It rejects ambiguous corruption rather than repair it.** A percent-encoded space, a doubled slash, a path that repeats the host, an out-of-range port: each is a mangling that shows up in real relay lists (copy-paste artefacts, concatenation bugs), and each could be "repaired" more than one way. A wrong repair does not fail loudly — it silently produces a *valid URL for a different relay*. Refusing to guess is the safe choice: the value that is never constructed is caught at the boundary as a returned `null` (an anticipated outcome per ADR-0003) that the caller must handle, whereas a mis-repaired one would flow downstream as a confident pointer to the wrong server.

The strictness is therefore load-bearing for identity, not incidental: it is the difference between a canonical value that deduplicates correctly and a family of distinct-but-equivalent values that do not.

## Consequences

- `equals()` and `RelayUrlCollection::unique()` are sound because the surviving values are canonical: equivalent spellings are already collapsed, and the forms that could not be collapsed safely never exist as values.
- Mangled and duplicated-authority URLs from real relay lists are refused at the boundary rather than flowing through as a wrong-but-valid relay.
- Rejection is a returned `null`, not a throw.
- Tests pin both the canonicalisations and the specific rejections. Do not "loosen" the parser to accept these forms — it reintroduces distinct-but-equivalent `RelayUrl`s that break `equals()`/`unique()`, and risks repairing a corrupt URL into a different relay.
