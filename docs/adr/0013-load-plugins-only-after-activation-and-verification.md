# 0013. Load plugins only after activation and verification

* Status: Proposed
* Date: 2026-11-18

## Context

oezCMS is meant to be extended by plugins. A plugin is PHP code, templates and database
objects, and its PHP runs in the same process as the core: it can read the process
environment, open its own database connection, read files and reach the network. No
mechanism inside that process can take this away from it.

Nothing of the plugin system exists yet. Plugins will register routes, event listeners and
templates, and there is no router, dispatcher or view layer to register them with
(roadmap: after phase 6). What can be decided now is everything that does not depend on
those: where plugins come from, what is loaded and when, how a plugin is known to be
unchanged, and where its database objects may go. These decisions shape every later one,
and they are the ones a security problem would be traced back to.

Two invariants were already committed to before this record: no PHP from a directory the
web server can write to (SEC-40), and declared permissions (SEC-41). This record turns
them into a design and states what they can and cannot protect against.

## Decision

### Trust model

A plugin that has been installed and enabled is trusted code. Its PHP has every right the
runtime process has. The protections below decide **which** code is allowed to run and
detect when it changes; they do not contain code that is already running. Containing
untrusted plugins would need a process boundary, which is not part of this design.

### Threat model

| Threat | Countered by |
|---|---|
| An attacker who exploits the web runtime (injection, code execution in core or a plugin) tries to persist by changing plugin code | Plugin files and their parent directories are not writable by the runtime (SEC-40); changed files are not loaded (SEC-42) |
| The same attacker tries to switch on a plugin that is present but disabled | The activation registry is a file the runtime cannot write, not a table it can (SEC-46) |
| An operator mistake: wrong permissions, a symlink into a writable place, a half-copied directory, an incompatible version | The loader refuses instead of loading (SEC-40, SEC-42, SEC-44) |
| A plugin bug that reaches into core services or core database objects | Capabilities limit the official API (SEC-41); the deploy refuses objects outside the plugin's namespace (SEC-47) |
| A disabled plugin running code anyway, through autoloading or a bootstrap include | Disabled plugins are never registered with an autoloader (SEC-45) |
| A disabled plugin still acting through the database, by a trigger or a scheduled event | Disabling removes the plugin's runtime objects (SEC-48) |
| Malicious code installed by the operator | **Out of scope.** Installed code is trusted; only process isolation would help |
| An attacker with operator or root access | **Out of scope** |

A checksum proves that files are unchanged since activation. It does not prove where they
came from. Reviewing a plugin before installing it is the operator's responsibility.

### Location and layout

Plugins live in `plugins/` only, outside the document root. They are placed there by the
operator, by deployment or over SSH. There is no installation, activation or update
through HTTP, now or later.

```
plugins/
├── enabled.json            activation registry
└── example/
    ├── plugin.json         manifest
    ├── src/                PHP, PSR-4 root of the plugin's namespace
    ├── database/
    │   ├── migrations/
    │   ├── routines/
    │   ├── views/
    │   └── triggers/
    └── resources/          templates, translations, assets
```

`plugins/`, everything below it and `enabled.json` belong to the deploy user and are not
writable by the runtime user. A plugin contains no `vendor/` directory; a library a plugin
needs becomes a dependency of the core and is locked with it (SEC-07).

### Names

A plugin's name matches `^[a-z][a-z0-9]{1,23}$` and equals its directory name. It
contains no underscore, because the name becomes part of database identifiers: with
underscores, a plugin `shop` could create `plugin_shop_orders_items`, which reads as the
namespace of a plugin `shop_orders`. The length leaves room within MariaDB's 64 characters
for the object names that follow it. Names of core modules (`i18n`, …) and `core`,
`oezcms` are reserved.

The name is validated before it is used in a path, an identifier or a class name.

### Manifest

`plugin.json` is data. It is read and validated without executing any plugin code.

```json
{
    "schema_version": 1,
    "name": "example",
    "version": "1.0.0",
    "description": "Example plugin",
    "core": "^1.0",
    "namespace": "OezCMS\\Plugins\\Example",
    "entrypoint": "OezCMS\\Plugins\\Example\\Plugin",
    "capabilities": ["i18n.translate"],
    "dependencies": {
        "plugins": {},
        "extensions": ["json"]
    }
}
```

* Unknown fields are rejected; `schema_version` allows the format to change later.
* `version` is a semantic version, `core` a version constraint the running core must
  satisfy.
* `namespace` is `OezCMS\Plugins\<Name>`, where `<Name>` is the plugin name with its first
  letter in upper case. `entrypoint` is a class inside it.
* `capabilities` come from an allow-list the core defines.
* `dependencies.plugins` maps plugin names to version constraints; cycles are refused.

### Integrity

When a plugin is enabled, a digest of its directory is recorded in the registry:

1. Collect every entry below the plugin directory. Anything other than a regular file or
   a directory, symbolic links included, is refused.
2. Take each file's path relative to the plugin directory, with `/` as separator, and
   sort the paths by byte value.
3. For each file, in that order, append `<relative path> NUL <SHA-256 of its content in
   hex> LF` to one string. File contents are hashed as they are, without normalisation.
4. The digest is the SHA-256 of that string.

The manifest is included like every other file. The registry is not part of any plugin
directory and therefore not part of any digest.

A plugin whose digest no longer matches the registry is not loaded, and a disabled plugin
whose files changed cannot be enabled again. Nothing re-pins a digest implicitly: only
`plugin:upgrade`, run by the operator, records a new one.

### Activation registry

`plugins/enabled.json` records, per plugin, its state, version and digest. It is written
only by console commands, run as the deploy user, and only ever replaced as a whole:
written to a temporary file in the same directory, flushed to disk, then renamed over the
old one. An interrupted write leaves the previous registry intact.

The registry is a file and not a table, because the runtime can write to every table of
the application database ([0012](0012-keep-the-deploy-log-out-of-the-runtimes-reach.md)).

### Database objects

A plugin's database objects fall into two kinds, the same two the core's deploy already
distinguishes:

* **Migrations** change the schema or the data for good: tables, columns, indexes, seed
  rows. They run once and are recorded.
* **Runtime objects** are what the plugin needs while it is active: functions,
  procedures, views, triggers and scheduled events. They hold no data and are recreated
  from the plugin's files whenever it is deployed.

Both carry the plugin name where a core object carries its module name
([0002](0002-name-schema-objects-by-type-and-module.md)):

| Object | Pattern |
|---|---|
| Table | `plugin_<name>_<table>` |
| Function, procedure, view | `fn_<name>_…`, `sp_<name>_…`, `v_<name>_…` |
| Trigger | `trg_plugin_<name>_<table>_<timing>_<event>` |
| Event | `evt_<name>_…` |

The deploy gains a phase for events when the first one is needed, in the core or in a
plugin; the core's naming for events is settled then.

Plugin migrations are recorded in the core's deploy log under `plugin/<name>/<file>`. Core
migrations keep their bare file names: a file name cannot contain `/`, so the two cannot
collide. Everything the log guarantees for the core therefore holds for plugins as well
([0007](0007-track-migrations-as-a-state-machine.md)): each migration runs once, its
checksum is kept and a later change to it is refused, a failure is recorded and resumed,
and nothing is ever rolled back automatically.

The deploy refuses a plugin file that creates, alters or drops an object outside the
plugin's namespace, or that contains `DEFINER`, `GRANT` or `REVOKE`. This guards against
mistakes. It is not a security boundary: SQL can reach other objects in ways a file check
cannot see, and the plugin's PHP can reach them anyway.

### Lifecycle

| State | Meaning |
|---|---|
| Present | The directory exists; the registry does not mention it. Nothing is loaded. |
| Enabled | Validated, verified, migrations applied, runtime objects deployed, recorded in the registry. Loaded. |
| Deactivating | Recorded as being disabled. Not loaded; its runtime objects may still partly exist. |
| Disabled | Not loaded; no runtime objects exist. Its tables, data and migration records remain. |

**Disabling is not uninstalling.** It removes only what the plugin needs to be active,
never its tables, its data or the record of its migrations.

`plugin:enable`, for a present or a disabled plugin:

1. Validate the manifest, the name, the layout and the file system rules; check
   compatibility with the core and the plugin's dependencies.
2. Compute the digest. For a disabled plugin it must equal the recorded one.
3. Apply the plugin's pending migrations. Migrations already recorded as completed are
   skipped, so enabling again never creates a table a second time.
4. Deploy the runtime objects: routines, then views, then triggers. If this fails, the
   runtime objects already created are dropped again.
5. Record the plugin as enabled.

`plugin:disable`:

1. Record the plugin as deactivating. From the next request on, it is not loaded; requests
   already running finish as they started.
2. Drop its runtime objects. They are found in the database by the plugin's namespace, not
   read from its files or its code, so this works even when the files are missing or
   broken.
3. Record the plugin as disabled.

If step 2 fails partway, the plugin stays deactivating and is still not loaded. Running
`plugin:disable` again completes it.

The registry always changes last, or, when disabling, first to a state that stops loading.
A failure before that leaves the plugin where it was.

All commands:

| Command | Does |
|---|---|
| `plugin:list` | Lists present plugins and their state |
| `plugin:validate <name>` | Checks manifest, name, layout and file system rules |
| `plugin:verify <name>` | Compares the directory with the recorded digest |
| `plugin:status <name>` | Shows state, version, digest and whether it currently loads |
| `plugin:enable <name>` | As described above |
| `plugin:disable <name>` | As described above |
| `plugin:upgrade <name>` | For an enabled or disabled plugin whose files were replaced: validates, applies new migrations, redeploys runtime objects if enabled, records the new version and digest |
| `plugin:uninstall <name> --drop-data` | For a disabled plugin: drops its tables, its migration records and its registry entry. Without the flag it refuses. |

### Loading

At start-up the loader reads the registry and, for each enabled plugin only:

1. refuses to load any plugin if the process runs as root;
2. checks the file system rules: no entry and no parent directory up to the project root
   writable by the process, real paths inside the plugin directory, no symbolic links;
3. checks the digest;
4. registers the plugin's namespace with an autoloader for its `src/` directory;
5. instantiates the entrypoint and hands it a container limited to its declared
   capabilities.

A plugin that fails a check is not loaded and the failure is reported; the application
starts without it. A plugin in any other state than enabled is never registered with any
autoloader, so none of its code can run.

Between the check and the `include` there is a window in which a file could be swapped.
It is closed by the ownership rules, not by the check; the check exists to turn a wrong
permission into a visible error.

### Capabilities

A capability names a part of the official core API, such as reading translations. A plugin
receives only the services behind the capabilities it declared; asking for anything else
fails. This keeps the API a plugin depends on explicit and reviewable. It is not a sandbox:
the plugin can still call PHP functions and open connections of its own.

### Boundaries

A plugin uses `Core`, the published `Api` of modules and the published `Api` of the
plugins it depends on, nothing deeper
([module-boundaries.md](../development/module-boundaries.md)). The core never depends on a
plugin.

## Alternatives considered

* **Plugins as Composer packages**, in `vendor/` or moved to `plugins/` by an installer. An
  installer is itself a Composer plugin and would need `allow-plugins`, which is disabled
  on purpose. Composer would also register the plugin's classes with the global autoloader,
  enabled or not, and `autoload.files` would execute code of a disabled plugin before any
  check runs. Composer remains the tool for the core's own dependencies.
* **Discovery by scanning `plugins/`.** Whatever happened to be in the directory would run,
  including a half-copied or forgotten plugin.
* **The activation state in a database table.** The runtime can write it, so an injection
  could switch on a plugin that is present but disabled.
* **A database account per plugin.** The plugin runs in a process that holds the runtime's
  credentials, so a restricted account does not restrict it.
* **Validating plugin SQL completely.** Dynamic SQL in procedures and executable comments
  make that unreliable, and a check that looks complete while it is not is worse than an
  honest guard against mistakes.
* **Underscores in plugin names**, as in `^[a-z][a-z0-9_]{1,47}$`. They make database
  namespaces ambiguous, and 48 characters leave almost no room for object names.
* **Keeping disabled plugins' runtime objects.** Their triggers would keep firing and
  their events keep running while the plugin is supposedly off.
* **A stored list of deployed objects to drop on disabling.** It can drift from what the
  database actually contains. The namespace is enforced on every deploy, so the database
  itself is the authoritative list.
* **A `core/` prefix for core migrations in the deploy log.** It adds symmetry but no
  information, since plugin entries are already unambiguous, and it would invalidate every
  existing log entry.
* **Signed plugins.** Would prove origin, which a digest cannot. Not rejected; deferred
  until there is a distribution channel to sign for.

## Consequences

* Installing a plugin is a privileged operator action on the server, never a click.
* A replaced file disables a plugin until the operator confirms the change with
  `plugin:upgrade`, which makes a silent modification visible and an intended one explicit.
* A disabled plugin does nothing, neither in PHP nor in the database. Disabling never
  loses data; only `plugin:uninstall --drop-data` does.
* Enabling a plugin again is cheap and safe: its migrations are not repeated, its runtime
  objects are recreated from the same files.
* The core has to provide a capability allow-list, a limited container, an autoloader for
  plugin namespaces and an events phase in the deploy.
* The architecture test has to cover `plugins/` once the first plugin exists.
* Open until HTTP exists: how plugins register routes, listeners and templates, and what
  checking the digest on every request costs. A cache for the result must not weaken
  SEC-42.
