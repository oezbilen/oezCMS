# 0010. Keep a container of our own

* Status: Accepted
* Date: 2026-07-28 (recorded 2026-11-16)

## Context

Plugins will receive the service container, and PSR-11 is the interface they already
know. Established implementations exist (PHP-DI, league/container). PSR-11's `get()`
returns `mixed`.

## Decision

`Core\Container` stays and implements PSR-11. Identifiers are class names: `get()` is
annotated with a template type, so every call site receives the concrete class.
Registration is explicit: a factory through `set()` or a finished object through
`instance()`.

## Alternatives considered

* **PHP-DI or league/container.** Their `get()` returns `mixed`. At PHPStan level 10 that
  means an assertion at every call site, or a typed wrapper around them, which is the
  kind of adapter PSR-11 is supposed to make unnecessary.
* **Autowiring by reflection.** In a plugin system, implicitly constructing whatever a
  constructor names is not auditable. Explicit registration shows what a plugin adds.

## Consequences

* The container is maintained here. Features such as transient services are built when
  something needs them.
* A third-party container can still be placed behind the interface later without any
  plugin noticing.
* The narrowing exists only in the annotation, so one PHPStan suppression is needed. It
  is pinned to a count of one in `phpstan.neon`.
