# PSR Interfaces

## The rule

A new subsystem in an area covered by a PSR starts against that interface, never against
an interface of our own invention. The cost of getting this wrong is not the interface —
it is every adapter written later to bridge one that should never have existed.

Adopting an interface is not the same as adopting an implementation. `psr/*` packages
ship interfaces only, and each decision below says separately whether we write the
implementation or take one.

Already in use across the project: **PSR-4** (autoloading) and **PSR-12** (coding style,
enforced by `composer cs:check`).

## Adopted

### PSR-11 — Container

`OezCMS\Core\Container` implements `Psr\Container\ContainerInterface`. Plugins receive the
container through that interface, so a third-party container could later be placed behind
it without a single plugin noticing.

Two exceptions, as the standard requires them to be distinguishable:

| Case | Class | Interface |
|---|---|---|
| Nothing registered under this id | `ServiceNotFoundException` | `NotFoundExceptionInterface` |
| Registered but unbuildable (e.g. a cycle) | `ContainerException` | `ContainerExceptionInterface` |

`ServiceNotFoundException` extends `ContainerException`, so catching the general case
still catches the specific one.

**Identifiers stay class-strings.** PSR-11 accepts any string and our container answers an
unknown one correctly, but everything this project registers is keyed by a class name —
that is what makes `get()` return a known type instead of `mixed`. Where a service has no
natural class to be keyed by, wrap it (`MigrationDatabase`) rather than inventing a string
key. The `method.childParameterType` suppression in `phpstan.neon` exists for exactly this
and is pinned to a count of one.

## Committed, not yet applicable

Nothing below exists in the codebase yet. These are decisions recorded so they are not
made again under time pressure.

| Standard | Area | Implementation |
|---|---|---|
| PSR-3 | Logging | Ours, or Monolog |
| PSR-7 + PSR-17 | HTTP messages and factories | **Adopt** — `nyholm/psr7` |
| PSR-15 | Middleware | Ours |
| PSR-14 | Events | Ours |
| PSR-16 | Cache | Ours, or `symfony/cache` |

### PSR-3 — Logging

When logging arrives, every service that logs takes `LoggerInterface` in its constructor.
`psr/log` ships `AbstractLogger` and `NullLogger`, so a minimal implementation of our own
is small; the interface is what matters, not who wrote the writer behind it.

### PSR-7 and PSR-17 — HTTP

PSR-7 alone only describes messages; **creating** them needs PSR-17 factories, which is
easy to miss when planning for PSR-7 by name.

This is the one place where we adopt rather than write. Correct PSR-7 means immutable
messages with `with*()` semantics, stream bodies and uploaded-file handling — a project in
itself, and a well-tested one already exists.

### PSR-15 — Middleware

Two small interfaces, `MiddlewareInterface` and `RequestHandlerInterface`, both built on
PSR-7. Request handling should be a middleware stack from the first commit: retrofitting
one into request handling that grew without it is the expensive case, and authentication,
CSRF and localisation all belong there.

### PSR-14 — Events

The plugin extension point, and therefore the one to get right.

**PSR-14 has no event names.** Events are objects and listeners are matched by type, so a
plugin API built around string event names is not PSR-14 and cannot be made into it later
without breaking every plugin written against it. This has to be decided before the first
hook exists, not after.

### PSR-16 — Cache

PSR-16 (`SimpleCache`) for our own code: `get`/`set`/`delete` with a TTL, which is what a
CMS cache needs. PSR-6 only if a dependency demands a pool, and then as a second
implementation rather than as a replacement.
