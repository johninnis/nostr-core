# 127. NIP-42 is checked by a domain checker at a given instant, and the validator adds the clock

## Status

Accepted

Supersedes ADR-0066. Its decision on what is checked is carried forward unchanged: the kind, a timestamp tolerance defaulting to ten minutes, the `challenge` tag and the `relay` tag by canonical form, in that cost order, each binding tag as one claim, and no signature check. What is revised is where the rules live, as ADR-0110 revised it for NIP-98, and what the event is checked against. This record holds the complete current decision.

## Context

NIP-42 lets a relay ask a client to prove control of a key: the relay issues a challenge, the client signs a kind 22242 event carrying that challenge and the relay's URL, and the relay checks that the challenge tag equals the challenge it issued, the relay tag names this relay, and `created_at` is close to now. Those rules are the protocol's and the same for every relay (nostr-adrs ADR-0026).

`Nip42Validator` took a clock and the timestamp tolerance, and read the clock inside its timestamp rule. Judging an auth event is a pure function of the event, what it answers and an instant; reading the clock is I/O. `Nip98Validator` held the same two jobs and was split for that reason (ADR-0110): its rules and tolerance became the domain's `Nip98EventChecker`, and the validator kept the clock. NIP-42 had stayed on the old shape, so the two auth protocols were built two ways.

A checker at an instant takes the event, the challenge, the relay URL and the instant: four arguments. The challenge and the relay URL are not two unrelated inputs. They are what the relay issued, a challenge sent from one relay, and the event is valid only as an answer to that pair, as a NIP-98 event is valid only for the `Nip98Request` it authorises.

Signature validity is not one of the rules. A relay validates every inbound event's structure and signature before it looks at what the event says, so a signature check here would duplicate that work or tempt a caller to skip it.

## Decision

- `RelayChallenge` (`Domain\ValueObject\Protocol`) is the challenge a relay issued: its `RelayUrl` and its `Challenge` (ADR-0070).
- `Nip42EventChecker` (`Domain\Service`) takes the timestamp tolerance, default 600 seconds, and `check(event, relayChallenge, at)` returns the first `Nip42ValidationFailure` or `null`. A negative tolerance is a configuration fault and the constructor throws `InvalidArgumentException`; zero is a window of exactly the given instant. It implements `Nip42EventCheckerInterface`, beside it in `Domain\Service`: it has no collaborator, but its tolerance is configuration and `Nip42Validator` is composed around it, which is ADR-0098's rule for a configured pure service.
- `Nip42Validator` (`Application\Service`) takes that interface and the clock, and `validate(event, relayChallenge)` checks the event at the clock's instant.
- The gates run in this order: the kind, then the timestamp, then the `challenge` tag, then the `relay` tag. Each binding tag must state one value: two that disagree are refused, the same value repeated is one claim. The relay tag is parsed with `RelayUrl::tryFromString` and compared with `equals()`, so canonically equal URLs match; the challenge compares in constant time.
- Neither verifies the signature; that remains the event validator's job, run first.
- The client builds its answer from the same value: `RumourFactory::createAuth(relayChallenge)` writes the `relay` and `challenge` tags from the `RelayChallenge` it answers, so the pair is carried as one value on both sides.

## Consequences

- A host builds `new Nip42Validator(new Nip42EventChecker($tolerance), $clock)` and passes `new RelayChallenge($relayUrl, $challenge)`, as it builds `Nip98Validator` around `Nip98EventChecker`.
- The checker can be used at a fixed instant without a clock.
- Breaking: `Nip42Validator` no longer takes a tolerance, and `validate` takes a `RelayChallenge` where it took a `Challenge` and a `RelayUrl`; `RumourFactory::createAuth` likewise takes a `RelayChallenge`.
- Do not add a signature check for completeness. A test pins this: an event whose signature does not verify still passes the NIP-42 check.
- Do not reorder the gates to read in specification order. The order is a cost decision: a single integer comparison, then the commonest refusal, a stale event, and only then the tag comparisons.
- The failure messages are wire-facing prose. A relay prefixes them with `auth-required:`, so they name the rule that failed and nothing about the relay's configuration.
- Shared decisions: nostr-adrs ADR-0025, ADR-0026 and ADR-0014.
