# Schema Deployment

```bash
php bin/console db:deploy
```

Applies everything under `database/` to the database configured for deployment (see
[database-privileges.md](database-privileges.md)). Every file contains exactly one
statement, and a file with nothing but comments is rejected rather than deployed.

## Phases

| Phase | Directory | Behaviour |
|---|---|---|
| Migrations | `database/migrations` | Tracked, applied once, in filename order |
| Routines | `database/routines` | `CREATE OR REPLACE`, redeployed every run |
| Views | `database/views` | `CREATE OR REPLACE`, redeployed every run |
| Triggers | `database/triggers` | `CREATE OR REPLACE`, redeployed every run |

Migrations are deliberately not idempotent — that is the object phases' property.
Their repeatability comes from the tracking table instead.

The output separates the two, because a run that changed nothing about the schema
still redeploys every object:

```
Migrations applied: 0
Routines refreshed: 6
Views refreshed: 2
Triggers refreshed: 4
```

## Concurrency

The whole run holds `GET_LOCK('oezcms_db_deploy_<database>')` with a 30 second wait, so
two simultaneous deploys serialise rather than interleave. The lock is released in a
`finally` block, and the server releases it anyway when the connection drops — a
deploy killed with `SIGKILL` does not leave it held.

## Migration states

Each migration is recorded in `oezcms_migration` with a SHA-256 checksum of the exact
statement that was executed.

| Status | Meaning | Next deploy |
|---|---|---|
| `completed` | Applied successfully | Skipped; a changed file aborts the deploy |
| `failed` | The statement returned an error | Retried automatically |
| `started` | In progress, or interrupted | Aborts and asks for a decision |

`failed` is retried without ceremony because one file is one statement and MariaDB 11
applies DDL atomically: an error means nothing was applied.

## When a migration is stuck in `started`

This is what an interrupted deploy leaves behind — a process killed, a connection lost,
a server restarted mid-statement. The next deploy refuses to continue and names the
migration.

It is not resolved automatically, and that is deliberate. A signal reaches the PHP
process while the statement is still running on the server, so the client cannot know
whether it completed. Marking it `failed` would assert that nothing was applied, which
is exactly the thing nobody knows at that moment. The state is ambiguous, so it is
reported as ambiguous.

Resolve it by hand:

```sql
SELECT migration, started_at, error_message
  FROM oezcms_migration
 WHERE status = 'started';
```

Read that migration file — it is one statement — and check the database for its effect:
does the table, column, index or routine exist?

**It was applied.** Record that, leaving the stored checksum untouched:

```sql
UPDATE oezcms_migration
   SET status = 'completed', completed_at = NOW(3)
 WHERE migration = '005_example.sql';
```

**It was not applied.** Remove the row so the next deploy runs it again:

```sql
DELETE FROM oezcms_migration WHERE migration = '005_example.sql';
```

## When a migration was modified after being applied

The deploy aborts naming the file. An applied migration must not change: the stored
checksum describes what actually ran, and a differing file means the database and the
repository disagree about the schema's history.

Before the first release this is expected while baseline migrations are still being
edited, and the answer is to recreate the development database:

```sql
DROP DATABASE oezcms;
CREATE DATABASE oezcms CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
```

After the first release, migrations are immutable and a change is made through a
follow-up migration.
