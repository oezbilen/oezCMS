# 0007. Track migrations as a state machine

* Status: Accepted
* Date: 2026-07-20 (recorded 2026-11-16)
* See also: [0012](0012-keep-the-deploy-log-out-of-the-runtimes-reach.md), where the log lives

## Context

The first tracking table recorded only that a migration had been applied. It could not
tell an interrupted run from a failed one, did not notice when an applied migration's
file was edited afterwards, and nothing stopped two deploys from running at once.

## Decision

* Each migration has one row in `oezcms_migration` with a status (`started`,
  `completed`, `failed`) and the SHA-256 checksum of the exact string that was executed,
  line endings normalised.
* A run inserts `started`, executes, then sets `completed`. A statement error sets
  `failed` with the server's message, and the next deploy retries it.
* A row left in `started` aborts the next deploy and needs a human decision.
* A `completed` migration whose checksum no longer matches its file aborts the deploy.
* The whole run holds `GET_LOCK('oezcms_db_deploy_<database>')`.

The procedures are in [schema-deployment.md](../deployment/schema-deployment.md).

## Alternatives considered

* **Hashing the file instead of the executed string.** The checksum would describe
  something other than what ran.
* **Turning `started` into `failed` when the process receives a signal.** The statement
  keeps running on the server after the signal reaches PHP, so nobody knows whether it
  was applied. `failed` claims it was not; `started` says exactly what is known.

## Consequences

* Retrying `failed` automatically is safe only because a file holds exactly one statement
  and MariaDB 11 applies DDL atomically: an error means nothing was applied. The
  one-statement rule is therefore load-bearing, not stylistic.
* An interrupted deploy needs someone to check the database; the runbook says how.
* Editing an applied migration is detected. Before the first release the answer is to
  recreate the development database; afterwards migrations are immutable.
* The lock is released by the server when the connection drops, so a killed deploy does
  not leave it held.
