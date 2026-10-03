# 108. Overlap between content references is checked by an offset set whose work is counted, not timed

## Status

Accepted

## Context

`ContentReferenceExtractor::extract` runs one pattern per reference type over an event's content and keeps a match only when it overlaps no match kept before it, so a `nostr:` URI suppresses the bare entity nested inside it. The overlap check once intersected each match's span with the set of every offset claimed so far, with the arguments in the order that iterates the claimed set: quadratic in the match count, and a 64KiB event of adjacent references took over five seconds.

The fix put the short span first, and a test guarded it by timing the extractor on 2000 and on 16000 matches and bounding the ratio. A timing test is not deterministic: machine speed and load move the ratio, the bound had to sit between a measured linear range and a measured quadratic one, and a loaded host could fail it. A built-in such as `array_intersect_key` cannot be observed from outside, so no deterministic test could see which way round its arguments were.

## Decision

- Overlap is checked by `ClaimedOffsets`, a small mutable set local to one extraction. `claim(position, length)` checks each offset of the span against the set and, if none is taken, claims them; it never iterates the offsets already claimed.
- `ClaimedOffsets` counts every offset it checks, and `offsetsExamined()` reports the count. Its test claims 16000 adjacent spans and asserts the count is exactly their total length: the work is measured, so the test is deterministic and fails for any implementation whose cost grows with what is already claimed.
- It is a `final` class with mutable state rather than a `readonly` value object, because an immutable set copied on every claim would itself be quadratic. It lives in `Domain/Service` beside its one caller, is created inside `extract`, and never escapes it.

## Consequences

- The linear bound on overlap detection is pinned by an exact count. A scratch copy that scans the claimed set examines over a billion offsets for 16000 spans where the test expects 144000.
- The count is part of the class's surface only so the test can read it; nothing else uses it.
- Do not move the overlap check back into the extractor, or replace it with a built-in whose cost a test cannot see. Do not reintroduce a wall-clock test for it.
