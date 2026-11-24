# 0014. Run routines and views with the caller's rights

* Status: Accepted
* Date: 2026-11-20

## Context

The runtime and the deploy account are separate, so that the runtime holds no DDL rights
and nothing beyond data access and routine calls in `oezcms`
([database-privileges.md](../deployment/database-privileges.md)). MariaDB creates
routines and views with `SQL SECURITY DEFINER` unless told otherwise. Such an object runs
with the rights of the account that created it, the deploy account, no matter who calls
it, and the runtime holds `EXECUTE` on every routine in the schema.

Today no routine or view does anything the runtime could not do directly. The split held
by coincidence rather than by construction: the first routine doing more would have
handed the runtime that power, and nothing would have noticed.

Triggers and events have no such choice. They always run with their definer's rights.

## Decision

Every routine and view declares `SQL SECURITY INVOKER` and runs with the rights of the
account that calls it. A routine can then do nothing its caller could not do directly,
and the account split holds whatever a routine does.

This applies to plugin objects as much as to the core schema. An integration test checks
every routine and view the deploy creates, so a missing clause fails CI instead of
depending on review.

Triggers stay as MariaDB defines them. A trigger runs as the deploy account, so its body
does only what the runtime may do anyway: read and check rows of the schema's own tables
and refuse with `SIGNAL`. There are no events yet; the change that introduces the first
one answers the same question for events.

## Alternatives considered

* **Keep definer rights and review each routine.** This is the state being replaced. It
  depends on every author and reviewer remembering a default that is invisible in the
  file.
* **A dedicated definer account holding only what the runtime holds.** Routines would no
  longer run as the deploy account, but a second account would have to be kept in step
  with the runtime's grants, and a routine would still not run as its caller.
* **Definer rights as a deliberate gate.** Routines as the only way to reach tables the
  runtime may not touch directly is a valid design, but it needs table-level grants for
  the runtime, and nothing in the schema calls for it. A routine that ever does gets a
  decision of its own.
* **Refuse files without the clause in `db:deploy`.** A text check on SQL has to
  understand comments and executable comments, and it would repeat the integration test,
  which checks what the server actually created.

## Consequences

* The runtime needs direct privileges on everything a routine or view touches. Its grant
  (`SELECT, INSERT, UPDATE, DELETE, EXECUTE ON oezcms.*`) provides them. Narrowing that
  grant later narrows what its routine calls can do as well.
* A routine or view created with definer rights fails `SqlSecurityIntegrationTest`.
* Triggers remain the one place where the deploy account's rights apply, which is why
  their bodies are limited to checks and refusals.
* Triggers still depend on their definer: dropping the deploy account breaks every
  trigger it created.
