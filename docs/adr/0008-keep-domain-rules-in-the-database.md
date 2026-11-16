# 0008. Keep domain rules in the database

* Status: Accepted
* Date: 2026-07-05 (recorded 2026-11-16)

## Context

The rules of a CMS — which locale falls back to which, which translation keys are
protected, which locale is the default — have to hold for everyone who writes data: the
application, a migration, an administrator with an SQL client, a plugin, a second
application reading the same tables. A rule kept in PHP holds only for code that goes
through that PHP.

MariaDB 11 can carry such rules itself: check constraints, triggers, stored routines,
recursive queries inside views, and atomic DDL.

## Decision

Wherever the database can hold a rule, it holds it. Invariants live in constraints and
triggers, domain answers in functions and views. PHP connects, configures, deploys the
schema and delivers the results. A PHP class is written for what the database cannot do:
files, HTTP, cryptography, external services.

## Alternatives considered

* **The rules in a PHP domain model, the database as storage.** Every path around that
  model bypasses the rules, and the common such paths are a migration and a manual fix.
* **The rules in both places.** The two copies drift, and the weaker one is the one that
  gets trusted.

## Consequences

* Domain logic is SQL. It is tested by integration tests against a real MariaDB; unit
  tests cannot reach it.
* The project is bound to MariaDB 11 (its check semantics, `uca1400` collations, native
  `UUID`, recursive views). Portability to other databases is given up.
* A module may have little or no PHP: i18n has none.
* Routines run with their definer's rights by default, which weakens the account split;
  see [database-privileges.md](../deployment/database-privileges.md).
* Turning a refusal from the database into a message for a person is a job for the
  delivery layer.
