# Database Privileges

oezCMS can deploy schema changes through a different database account than the one
serving requests. The runtime never issues DDL, so it does not need the rights to do
so, and an SQL injection or code execution flaw then reaches only what the runtime
legitimately does.

The separation is optional. With no migration credentials configured, both use the
same account and nothing changes.

## Configuration

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=oezcms
DB_USERNAME=oezcms_runtime
DB_PASSWORD=…

DB_MIGRATION_USERNAME=oezcms_deploy
DB_MIGRATION_PASSWORD=…
DB_MIGRATION_LOG_DATABASE=oezcms_log
```

Host, port, database name and charset are shared — it is the same database, only a
different login. The two migration values are a pair: configuring one without the
other is rejected rather than silently combined with a runtime value.

With the pair configured, `DB_MIGRATION_LOG_DATABASE` is required as well. It names the
database that holds the deploy log, and it must differ from `DB_NAME`
([ADR 0012](../adr/0012-keep-the-deploy-log-out-of-the-runtimes-reach.md)). Without
separate credentials it may be left out, and the log stays in the application database:
the runtime is then the deploy account, so there is nothing to keep apart.

`bin/console db:deploy` uses the migration account. Everything else uses the runtime
account.

## Accounts

```sql
CREATE DATABASE oezcms_log CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;

CREATE USER 'oezcms_runtime'@'localhost' IDENTIFIED BY '…';
CREATE USER 'oezcms_deploy'@'localhost' IDENTIFIED BY '…';
```

### Runtime

```sql
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE
    ON oezcms.* TO 'oezcms_runtime'@'localhost';
```

`EXECUTE` is required because reads go through stored functions such as
`fn_i18n_translate`.

The runtime is granted nothing on `oezcms_log`. It never reads or writes the deploy log,
and a runtime able to change it could make the next deploy apply a migration twice or
miss a modified one. The log cannot simply be excluded inside `oezcms`: a grant on
`oezcms.*` covers every table in it, and MariaDB has no way to subtract one table from a
database-level grant. That is why it lives in a database of its own.

### Deployment

```sql
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, ALTER, DROP, INDEX, REFERENCES,
      CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      CREATE VIEW, SHOW VIEW,
      TRIGGER, EVENT
    ON oezcms.* TO 'oezcms_deploy'@'localhost';

GRANT CREATE, SELECT, INSERT, UPDATE, DELETE
    ON oezcms_log.* TO 'oezcms_deploy'@'localhost';
```

`DROP` and `ALTER ROUTINE` are needed because every object file uses
`CREATE OR REPLACE`, which drops the existing object first. `REFERENCES` is needed for
foreign keys, `EVENT` for scheduled events. `SELECT`, `INSERT` and `UPDATE` on `oezcms`
cover the seed migrations.

On `oezcms_log`, `CREATE` lets the first deploy create the log table, `SELECT`, `INSERT`
and `UPDATE` keep it, and `DELETE` is what the
[recovery procedure](schema-deployment.md#when-a-migration-is-stuck-in-started) needs.

Granting `ALL PRIVILEGES ON oezcms.*` to the deploy account is a defensible
simplification — it needs nearly all of them — but it then also carries rights the
deployment never exercises.

Neither account needs `GRANT OPTION`, and neither needs any privilege outside `oezcms`
and, for the deploy account, `oezcms_log`. `GET_LOCK`, which serialises concurrent
deployments, requires no privilege.

## Definer rights

MariaDB creates routines and views with `SQL SECURITY DEFINER` by default, so an object
created by `oezcms_deploy` would run with that account's rights no matter who calls it.
Every routine and view in the schema therefore declares `SQL SECURITY INVOKER` and runs
with the caller's rights, so the runtime can do nothing through them that it could not
do directly ([ADR 0014](../adr/0014-run-routines-and-views-with-the-callers-rights.md)).
The runtime needs direct privileges on everything they touch, which its grant on
`oezcms.*` provides.

Triggers cannot be declared that way and always run as their definer. Their bodies are
therefore limited to checking rows and refusing with `SIGNAL`. The definer account must
keep existing: dropping `oezcms_deploy` makes every trigger it created fail.

## Verifying

```sql
SHOW GRANTS FOR 'oezcms_runtime'@'localhost';
SHOW GRANTS FOR 'oezcms_deploy'@'localhost';
```

A runtime account that can still create tables has not been restricted:

```sql
-- Expected to fail as oezcms_runtime.
CREATE TABLE privilege_check (id INT);
```

Nor should it see the deploy log:

```sql
-- Expected to fail as oezcms_runtime.
SELECT COUNT(*) FROM oezcms_log.oezcms_migrations;
```

## See also

[database-connection.md](database-connection.md) — transport, TLS and timeout settings.
