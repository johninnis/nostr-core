# 148. Secret bytes sit in a wipeable cell inside PHP's opaque holder

## Status

Accepted

## Context

`SecretKeyMaterial` held its bytes in a plain property. `var_dump`, `print_r`, `var_export`, an array cast and `serialize` all read properties, so a key passed to a debug line, a logger's context or a session store wrote out its raw bytes, and `serialize` then `unserialize` gave back a working key.

`__debugInfo` covers only `var_dump` and `print_r`, and `__serialize` only `serialize`; `var_export` and an array cast honour neither. Those magic methods would leave a hole and two mechanisms to maintain.

PHP has a built-in class for this: `SensitiveParameterValue` shows no properties to any of those readers and refuses serialisation. But it holds its value in a read-only slot, and a string directly inside it cannot be wiped: `sodium_memzero` zeroes a string only when its refcount is exactly one, and reading the string out of the holder makes a second reference.

## Decision

The bytes sit in a `SecretCell`, a small mutable object, inside a `SensitiveParameterValue`. The holder hides them from every reader and refuses serialisation; the cell lets `zero()` wipe the string in place through its only reference. `SecretKeyMaterial` is therefore a `final readonly` class, the mutation it needs living in the cell. A clone shares the cell, so zeroing either zeroes both.

## Consequences

- A key written to a debug line, a logger or a session store exposes nothing or fails loudly, by one mechanism PHP itself maintains.
- A key cannot be stored by serialising it. Store an `ncryptsec`.
- Reflection can still reach the bytes: this guards against accident, not against code running in the process.
- There is one more small type, public only because PHP has no nested classes; nothing but `SecretKeyMaterial` constructs it.
- Do not hold the bytes in a plain property, or add `__debugInfo` or `__serialize` in place of the holder. Do not put the string directly in the holder: it could not then be wiped.
