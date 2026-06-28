# 38. `Nip98Validator` carries its timestamp tolerance as a fourth constructor argument

## Status

Accepted

## Context

`Nip98Validator` is constructed with three collaborators — a signature service, a replay guard, and a
clock — plus a fourth argument, `int $timestampTolerance`, defaulted to 60 seconds. A fourth
constructor argument is a design signal: it usually means a unit has grown too many responsibilities,
and the standing fixes are to split the unit or to group genuinely cohesive arguments into a value
object. Side by side with the rest of the package — where services take one to three collaborators and
the only other four-argument constructor is recorded — this fourth argument reads like an oversight.

The tolerance is not a fourth collaborator. NIP-98 authorises an HTTP request by checking, among other
things, that the auth event's `created_at` is within a tolerance window of now; the width of that window
is the one policy knob the validator exposes. It is a scalar configuration value with a sensible
default, consumed on a single code path (the timestamp check) and used to derive one further value (the
replay TTL is twice the tolerance). It is neither I/O nor a dependency a test substitutes.

The two ways to remove the argument both make the design worse:

- **Bundle it into a parameter object.** Wrapping a single scalar in a value object purely to lower the
  argument count hides the design rather than fixing it — there is no cohesive group here, only one
  number, and a one-field DTO adds a type and a layer for nothing.
- **Split the validator.** The timestamp window is one of several gates the validator applies in
  sequence (kind, timestamp, URL, method, payload, signature, replay); extracting the timestamp gate to
  carry its own tolerance would fracture a single validation command across classes and force callers to
  assemble it from parts.

## Decision

`Nip98Validator` takes its timestamp tolerance as a defaulted fourth constructor argument. The argument
is accepted as it stands: it is irreducible configuration with a default, not a fourth collaborator and
not a cohesive group to fold into a value object, and the validator is not split to lower the count. A
host that needs a different window passes one; a host that does not gets the 60-second default.

## Consequences

- A reviewer applying "more than three arguments is a design signal" will read the constructor as a
  smell to fix. It is not: the fourth argument is a configuration scalar, and the two available
  "fixes" (a one-field parameter object, or splitting the validator) each trade a clear design for a
  worse one. A one-line fence on the constructor points here.
- The tolerance stays a constructor argument rather than a hard-coded constant, so the timestamp window
  is configurable at the composition root — a host validating attacker-supplied auth events can widen
  or narrow it without forking the validator.
- If the validator ever grows a second independent configuration knob, the two together may form a
  cohesive policy value object; at that point this record is superseded. A single knob does not.
