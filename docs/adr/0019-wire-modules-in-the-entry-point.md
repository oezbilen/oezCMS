# 0019. Wire modules in the entry point

* Status: Proposed
* Date: 2026-11-26

## Context

Media is the first module with PHP code, so for the first time something outside `Core`
has services to register. `Core` may use nothing of ours above it
([module-boundaries.md](../development/module-boundaries.md)), so `Kernel::boot()` cannot
name a module. Every service is registered by hand and nothing is discovered
([0010](0010-keep-a-container-of-our-own.md)).

`bin/console` already composes the application: it boots the kernel and constructs every
command itself. The layer check reads `src/` only, and `bin/` is not a layer.

A module needs configuration of its own, `MEDIA_STORAGE_PATH` to begin with. The kernel
builds `Config` from a fixed list of `APP_*` and `DB_*` keys and fails early on a bad
value.

## Decision

Each module has one registration class, `Modules\X\XModule`, with a static
`register(Container $container): void`. It registers the module's services as lazy
factories. A service other layers use is registered under its interface in
`Modules\X\Api`.

The entry point calls `register()` of every module right after `Kernel::boot()`, in a fixed
order, before anything is resolved. Today that entry point is `bin/console`. It is the only
place that names a module's registration class, and commands receive only `Api` interfaces
from the container.

A module reads its own variables from the `Environment` the kernel registers, inside the
factory that needs them, and refuses a bad value with an error that names the key.
`Config` stays limited to the core's keys.

When phase 6 adds the front controller, the list of modules moves into one file that both
entry points include, so the two cannot drift apart. Plugins are wired as
[0013](0013-load-plugins-only-after-activation-and-verification.md) describes, with a
container limited to their capabilities. A module is part of this repository and gets the
full container.

## Alternatives considered

* **The kernel registers the modules.** `Core` would have to know them, which the layer
  rules forbid.
* **Discovery,** such as a scan of `src/Modules/` or a list in `composer.json`. It is the
  implicit wiring that 0010 turned down.
* **Modules as plugins.** The activation, the digest and the capabilities of 0013 guard
  against code from outside the repository. Modules are not, and none of that machinery
  exists yet.
* **Module keys in `Config`, read by the kernel.** The kernel would know every module's
  configuration, which is knowing the module.
* **Checking module configuration at boot.** A missing `MEDIA_STORAGE_PATH` would then
  break every command, `db:deploy` on a fresh installation included. Checking lazily, at
  the first use, works the same way as the database connection, which is not opened before
  a command needs it.

## Consequences

* Adding a module means one `register()` call in the entry point, plus its schema files.
* A configuration error in a module surfaces at the first command that uses it, still
  before that command does any work. A command that does not use the module is not
  affected.
* An integration test of an entry point has to register the same modules. The shared list
  of phase 6 makes that one place.
* The inside of a module is still not constrained. `XModule` is the only class every
  module has.
