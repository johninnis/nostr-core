# 3. Value objects keep `getX()` methods, not property hooks

## Status

Accepted

## Context

PHP 8.4 property hooks and asymmetric visibility (`public private(set)`) let a *property* carry the computation, validation, and write-control that previously needed a `getX()` / `setX()` method, so the idiomatic 8.4 move is usually to expose a property and drop the accessor. Applied to this codebase's value objects, that would replace each `getX()` with a bare or hooked property.

## Decision

Value objects keep `getX()` methods. Property hooks and asymmetric visibility are not used to replace getters.

1. **`readonly` forbids hooks.** A property hook requires a non-readonly property, and a `final readonly class` makes every property readonly — so inside these value objects a hook is not available at all. A value computed on read (the `?? calculateId()` fallback in `Event::getId()`, the type-guarded reads over `Nip11Info`'s raw payload) can therefore only be a method. The language gives an immutable object exactly one tool for a computed read, and it is a method.
2. **A full migration is impossible, and a partial one is worse than none.** Because the computed, raw-array-backed, and interface-bound accessors (for example those satisfying `PaymentReceiptInterface`) must stay methods, converting only the trivial pass-throughs would split the public API into two access styles (`$event->pubkey` next to `$info->getName()`). A uniform `getX()` surface is the only internally consistent option.
3. **No behavioural or type-safety gain.** Unlike the return-vs-throw decision (ADR-0001), getter-to-property is purely syntactic: it would rewrite many hundreds of call sites across the ecosystem for no change in behaviour or analyser coverage.

## Consequences

- The value-object surface is uniformly `getX()`; entity lifecycle state likewise stays behind a `getX()` method, mutated through named transformations rather than a publicly-readable `private(set)` property.
- Setters do not arise. Value objects are immutable and transform by returning a new instance (`withTags(...)`); the few entities with genuine lifecycle state (`Event`, `Subscription`) mutate only through named transformations.
- Do not "modernise" individual getters into property hooks — it is unavailable on readonly properties and would fracture the access style.
