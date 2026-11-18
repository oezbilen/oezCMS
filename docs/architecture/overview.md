# Architecture Overview

What the system is made of and how it starts. The dependency rules between its parts are
in [module-boundaries.md](../development/module-boundaries.md), the conventions of the
schema in [schema-conventions.md](../database/schema-conventions.md), and the reasons in
the [decision records](../adr/README.md).

## Principles

* **Database-first.** Wherever the database can hold a rule, it holds it: constraints and
  triggers guard the invariants, functions and views compute the domain's answers.
  `fn_i18n_translate` *is* the translation fallback rule. PHP connects, configures, deploys
  the schema and delivers results ([0008](../adr/0008-keep-domain-rules-in-the-database.md)).
* **One axis.** Code is ordered by layer and module, not additionally by technical role
  ([0009](../adr/0009-organise-code-along-one-axis.md)).
* **Explicit wiring.** Every service is registered by hand. Nothing is discovered or
  autowired ([0010](../adr/0010-keep-a-container-of-our-own.md)).
* **Fail early, by name.** A wrong configuration value is reported with its key before
  anything depends on it, never later as a driver error.

## Building blocks

| Part | Where | Role |
|---|---|---|
| Environment | `Core\Environment` | Reads `.env` in a strict format and the process environment; the process environment wins |
| Config | `Core\Config` | Immutable, typed view of the configuration (`app.*`, `database.*`) |
| Container | `Core\Container` | PSR-11, keyed by class names, lazy factories |
| Kernel | `Core\Kernel` | Builds Environment, Config and Container and registers the database services |
| Database | `Core\Database` | PDO wrapper: queries, nested transactions through savepoints, stored procedure calls |
| MigrationDatabase | `Core\MigrationDatabase` | The same database, reached through the migration account |
| Console | `Console\*` | `bin/console`, command registry, `version`, `db:deploy` |
| Schema | `database/` | Migrations, routines, views, triggers |

## Start-up

```
bin/console
  └─ Kernel::boot()
       ├─ Environment   .env loaded if present, process environment takes precedence
       ├─ Config        APP_ENV and APP_DEBUG validated here
       └─ Container
            ├─ Environment, Config
            ├─ Database            lazy, runtime account
            └─ MigrationDatabase   lazy, migration account
  └─ Application(commands, app.debug)
       └─ Command  →  exit code 0 success, 1 failure, 2 usage
```

1. `Kernel::boot()` loads the environment and builds the configuration. `APP_ENV` must be
   one of `production`, `staging`, `development`, `testing`; production with debug
   enabled is refused.
2. The database services are registered as factories. A connection is opened when a
   command first asks for one, so `version` never connects. The connection values are
   validated by `MariaDbConnectionFactory` right before that first connect.
3. If anything fails before the application runs, `bin/console` prints `Error: <message>`
   on stderr and exits with 1. The stack trace follows only when `APP_DEBUG` is set.

## Two database accounts

The runtime uses `Database` and never issues DDL. `db:deploy` uses `MigrationDatabase`,
which may be a different login with the rights to change the schema. Without separate
migration credentials both are the same account. With them, the deploy log moves into a
database of its own that the runtime holds no privilege on. Details and grants:
[database-privileges.md](../deployment/database-privileges.md).

## Errors

Each subsystem throws its own exception: `EnvironmentException`, `ContainerException`
(with `ServiceNotFoundException` for an unknown id), `DatabaseException`,
`ConsoleException`. A `DatabaseException` carries the SQL and its parameters for
debugging, but never puts parameter values into its message.

Rules enforced by the database arrive as `DatabaseException` with `SQLSTATE 45000` and a
message naming the rule.

## Not built yet

* **HTTP.** There is no web entry point, router or request handling. The PSR interfaces it
  will use are fixed in [psr-interfaces.md](../development/psr-interfaces.md).
* **Modules.** `src/Modules/` does not exist yet. i18n lives entirely in the schema and
  has no PHP class.
* **Plugins.** The design is proposed in
  [ADR 0013](../adr/0013-load-plugins-only-after-activation-and-verification.md): operator-installed
  in `plugins/`, explicitly activated, verified before loading. No code exists yet.
