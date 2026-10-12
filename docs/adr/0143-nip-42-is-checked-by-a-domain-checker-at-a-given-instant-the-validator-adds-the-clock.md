# 143. NIP-42 is checked by a domain checker at a given instant, the validator adds the clock, and a relay answers a claim failure `invalid:` unless the challenge was replaced

## Status

Accepted

Supersedes ADR-0127. Carried forward unchanged: `RelayChallenge` is the challenge a relay issued with its relay; `Nip42EventChecker` holds the rules and the timestamp tolerance, default 600 seconds, behind `Nip42EventCheckerInterface`, and checks an event at a given instant, and `Nip42Validator` adds the clock; the gates run the kind, the timestamp, the `challenge` tag and the `relay` tag, in that cost order, each binding tag as one claim, the relay tag compared in canonical form and the challenge in constant time; neither checks the signature; `RumourFactory::createAuth` writes its answer from the `RelayChallenge`; the failure messages name the rule that failed and nothing about the relay's configuration. Revised: a `challenge` tag that is absent, empty or disagreeing is its own failure, `ChallengeUnstated`, apart from `ChallengeMismatch`, a stated challenge that is not the one issued; and each failure names the prefix a relay answers it with, `Nip42ValidationFailure::reasonPrefix()`, `auth-required` for `ChallengeMismatch` and `invalid` for every other, where ADR-0127 had a relay prefix every failure with `auth-required:`. This record holds the complete current decision.

## Context

NIP-42 lets a relay ask a client to prove control of a key: the relay issues a challenge, the client signs a kind 22242 event carrying that challenge and the relay's URL, and the relay checks that the challenge tag equals the challenge it issued, the relay tag names this relay, and `created_at` is close to now. Those rules are the protocol's and the same for every relay (nostr-adrs ADR-0026).

Judging an auth event is a pure function of the event, what it answers and an instant; reading the clock is I/O. `Nip98Validator` held both jobs and was split for that reason (ADR-0110): its rules and tolerance became the domain's `Nip98EventChecker`, and the validator kept the clock. NIP-42 follows the same shape.

A checker at an instant takes the event, the challenge, the relay URL and the instant: four arguments. The challenge and the relay URL are not two unrelated inputs. They are what the relay issued, a challenge sent from one relay, and the event is valid only as an answer to that pair, as a NIP-98 event is valid only for the `Nip98Request` it authorises.

Signature validity is not one of the rules. A relay validates every inbound event's structure and signature before it looks at what the event says, so a signature check here would duplicate that work or tempt a caller to skip it.

The prefix a failure is answered under tells the client what to do next. A client reads `auth-required:` on its `AUTH` as "answer the current challenge" and waits for one (nostr-adrs ADR-0030). ADR-0127 had a relay prefix every failure with `auth-required:`, so a skewed clock or a `relay` tag naming another address sent the client to wait for a challenge that never comes.

## Decision

- `RelayChallenge` (`Domain\ValueObject\Protocol`) is the challenge a relay issued: its `RelayUrl` and its `Challenge` (ADR-0070).
- `Nip42EventChecker` (`Domain\Service`) takes the timestamp tolerance, default 600 seconds, and `check(event, relayChallenge, at)` returns the first `Nip42ValidationFailure` or `null`. A negative tolerance is a configuration fault and the constructor throws `InvalidArgumentException`; zero is a window of exactly the given instant. It implements `Nip42EventCheckerInterface`, beside it in `Domain\Service`: it has no collaborator, but its tolerance is configuration and `Nip42Validator` is composed around it, which is ADR-0098's rule for a configured pure service.
- `Nip42Validator` (`Application\Service`) takes that interface and the clock, and `validate(event, relayChallenge)` checks the event at the clock's instant.
- The gates run in this order: the kind, then the timestamp, then the `challenge` tag, then the `relay` tag. Each binding tag must state one value: two that disagree are refused, the same value repeated is one claim. The relay tag is parsed with `RelayUrl::tryFromString` and compared with `equals()`, so canonically equal URLs match; the challenge compares in constant time.
- Neither verifies the signature; that remains the event validator's job, run first.
- The client builds its answer from the same value: `RumourFactory::createAuth(relayChallenge)` writes the `relay` and `challenge` tags from the `RelayChallenge` it answers, so the pair is carried as one value on both sides.
- A `challenge` tag that is absent, empty, or states two values that disagree is `ChallengeUnstated`: the event answers no challenge at all. `ChallengeMismatch` is one stated challenge that is not the one issued: the client answered a challenge the relay has since replaced. A `relay` tag that is absent, unparseable, disagreeing or another relay's is `RelayMismatch`.
- The failure messages are wire-facing prose naming the rule that failed, and say nothing about the relay's configuration. `Nip42ValidationFailure::reasonPrefix()` is the prefix a relay answers the failure with: `auth-required` for `ChallengeMismatch`, because the client holds, or is about to receive, the current challenge; and `invalid` for every other failure (`WrongKind`, `TimestampOutsideTolerance`, `ChallengeUnstated`, `RelayMismatch`), because answering the current challenge again cannot fix it. The rule lives on the failure so that every relay built on this package answers alike.

## Consequences

- A host builds `new Nip42Validator(new Nip42EventChecker($tolerance), $clock)` and passes `new RelayChallenge($relayUrl, $challenge)`, as it builds `Nip98Validator` around `Nip98EventChecker`.
- The checker can be used at a fixed instant without a clock.
- A client told `invalid:` settles its parked work at once with the relay's reason, instead of waiting out its auth timeout.
- Do not add a signature check for completeness. A test pins this: an event whose signature does not verify still passes the NIP-42 check.
- Do not reorder the gates to read in specification order. The order is a cost decision: a single integer comparison, then the commonest refusal, a stale event, and only then the tag comparisons.
- Do not answer every failure with `auth-required:`: only a replaced challenge is fixed by answering again.
- Shared decisions: nostr-adrs ADR-0014, ADR-0025 and ADR-0026.
