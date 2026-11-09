# 149. Every parameter carrying secret bytes, a password or a plaintext is marked sensitive

## Status

Accepted

## Context

An exception captures the arguments of every frame above its throw point unless `zend.exception_ignore_args` is on, and PHP's built-in default is off. A secret passed as a `string` is therefore pinned in the trace, reaches whatever logs the exception, and, because the trace holds a reference, also escapes the wipe of that string. A peer can provoke such an exception by tampering with a payload.

`sodium_memzero` and `expose()` do nothing for this window, and neither does `zero()`. Requiring hosts to turn the setting on would leave the library's secrets dependent on a deployment detail nothing checks.

## Decision

Every parameter that carries raw secret bytes, secret hex, a password or a plaintext is marked `#[SensitiveParameter]`, closures handed to `expose()` included. A secret may instead arrive through a `Closure` that returns it. `innis/coding-standards` enforces this.

## Consequences

- A trace holds a placeholder in place of the argument, whatever `zend.exception_ignore_args` says.
- A new secret-accepting `string` parameter must be marked, or take a `Closure`.
- Marking a parameter that turns out not to be secret costs nothing, so a parameter is marked when in doubt.
