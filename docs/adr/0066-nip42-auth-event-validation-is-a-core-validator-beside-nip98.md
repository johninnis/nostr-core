# 66. NIP-42 auth-event validation is a core validator beside NIP-98

## Status

Accepted

## Context

NIP-42 lets a relay ask a client to prove control of a key: the relay issues a challenge, the client signs a kind 22242 event carrying that challenge and the relay's URL, and the relay checks three things before it believes the event. The challenge tag must equal the challenge it issued, the relay tag must name this relay, and `created_at` must be close to now. Those rules are the protocol's, and they are the same for every relay.

This library already holds the exact structural sibling for NIP-98: `Nip98Validator` checks kind, timestamp tolerance and the request-binding tags of a kind 27235 event, returns a `Nip98ValidationFailure`, and takes its tolerance as a constructor argument (ADR-0038). NIP-42 validation had instead grown inside the relay library as a private verifier with a hard-coded tolerance and its own rejection enum, so the two halves of one pattern lived in two packages, and one of them compared the relay tag to the configured URL as a bare string. A client that sent `wss://Relay.Example.com/` for a relay configured as `wss://relay.example.com` was refused, although `RelayUrl` canonicalises exactly that and was already in hand.

Signature validity is not one of the three rules. A relay validates every inbound event's structure and signature before it looks at what the event says, and NIP-42 has nothing to add there, so folding a signature check into this validator would either duplicate that work or tempt a caller to skip it.

## Decision

`Nip42Validator` lives in `Application/Service` beside `Nip98Validator`, behind `Nip42ValidatorInterface`. It takes a clock and a timestamp tolerance, defaulting to ten minutes, and `validate(Event, Challenge, RelayUrl)` returns `?Nip42ValidationFailure`: wrong kind, timestamp outside tolerance, challenge mismatch or relay mismatch, each with a `message()`.

The gates run in that order, matching `Nip98Validator`: kind first because it is a single integer comparison, then the timestamp, because a stale event is the commonest refusal and the cheapest to detect, and only then the two binding tags.

Each binding tag must name exactly one value. An event carrying two `challenge` tags or two `relay` tags that disagree is refused, as `Nip98Validator` refuses duplicate `u`, `method` and `payload` tags: an event that claims two answers has not answered. The same tag repeated with the same value is one claim, not two, and passes. The relay tag is parsed with `RelayUrl::tryFromString` and compared with `equals()`, so canonically equal URLs match. The challenge arrives as a `Challenge` (ADR-0070), which cannot be empty and compares in constant time.

The validator does not verify the signature; that remains the event validator's job, run first.

## Consequences

- A relay built on this library validates NIP-42 the way it validates NIP-98, with the tolerance configurable in the same place, and no relay carries its own copy of the three rules.
- The relay library keeps only what is its own: whether the authenticated key is allowed to authenticate at all is policy, not protocol, and stays there.
- Do not add a signature check here for completeness. An event that reaches this validator has already been validated as an event, and a caller that has not done that has a bigger problem than NIP-42. A test pins this: an event whose signature does not verify still passes NIP-42 validation.
- Do not reorder the gates to read in specification order. The order is a cost decision, and the failures are independent, so the one a caller sees for a doubly-wrong event is the cheapest one to have found.
- The failure messages are wire-facing prose. A relay prefixes them with `auth-required:` when it writes the OK reply, so they name the rule that failed and nothing about the relay's configuration.
