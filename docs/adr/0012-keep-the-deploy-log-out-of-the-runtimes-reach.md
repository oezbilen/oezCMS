# 0012. Keep the deploy log out of the runtime's reach

* Status: Accepted
* Date: 2026-11-17

## Context

Schema changes can run under a separate deploy account, so that the runtime account
needs no DDL rights ([database-privileges.md](../deployment/database-privileges.md)).
The deploy log ([0007](0007-track-migrations-as-a-state-machine.md)) lived in the
application database, and the runtime's grant on `oezcms.*` covered it. MariaDB cannot
subtract a single table from a database-level grant. The documentation claimed the
runtime had no access to the log; it had full access.

A runtime able to write the log, through an injection or a code execution flaw, could
delete a row so that the next deploy applies a migration again, or rewrite a checksum so
that a modified migration goes unnoticed.

## Decision

With separate migration credentials, the deploy log lives in a database of its own,
named by `DB_MIGRATION_LOG_DATABASE`, on which the runtime holds no privilege. The
setting is then required, and it must differ from `DB_NAME`. Its value is placed into SQL
text and passes the identifier allow-list.

Without separate credentials the log stays in the application database. The runtime is
then the deploy account, and a second database would protect nothing.

The advisory lock stays keyed on the application database, which is what a deploy
changes.

## Alternatives considered

* **Correcting the documentation and accepting the limit.** Honest, but it would leave
  the deploy log writable by exactly the account the split exists to restrict.
* **Table-level grants for the runtime.** Every new table would need its own grant,
  either from an operator after each deploy or from a deploy account holding
  `GRANT OPTION`, which widens the account the split is meant to narrow.
* **A separate log database in every installation.** Uniform, but it would exclude
  hosting with a single database and protect nothing where only one account exists.

## Consequences

* An installation with separate accounts needs a second database, and the deploy
  account needs `CREATE, SELECT, INSERT, UPDATE, DELETE` on it.
* Moving the log means existing databases start with an empty one. Before the first
  release they are recreated.
* The recovery queries in the runbook name the qualified table when the log has a
  database of its own.
* The integration tests need a second throwaway database (`TEST_DB_LOG_NAME`).
