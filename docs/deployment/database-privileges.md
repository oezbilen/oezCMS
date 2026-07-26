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
```

Host, port, database name and charset are shared — it is the same database, only a
different login. The two migration values are a pair: configuring one without the
other is rejected at boot rather than silently combined with a runtime value.

`bin/console db:deploy` uses the migration account. Everything else uses the runtime
account.

## Accounts

```sql
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

The runtime is deliberately not granted anything on `oezcms_migration`: it never reads
or writes the deployment log. Restricting that table to the deploy account is the
cheapest available check that the separation actually holds.

### Deployment

```sql
GRANT SELECT, INSERT, UPDATE, DELETE,
      CREATE, ALTER, DROP, INDEX, REFERENCES,
      CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      CREATE VIEW, SHOW VIEW,
      TRIGGER, EVENT
    ON oezcms.* TO 'oezcms_deploy'@'localhost';
```

`DROP` and `ALTER ROUTINE` are needed because every object file uses
`CREATE OR REPLACE`, which drops the existing object first. `REFERENCES` is needed for
foreign keys, `EVENT` for scheduled events. `SELECT`, `INSERT` and `UPDATE` cover the
`oezcms_migration` log and the seed migrations.

Granting `ALL PRIVILEGES ON oezcms.*` to the deploy account is a defensible
simplification — it needs nearly all of them — but it then also carries rights the
deployment never exercises.

Neither account needs `GRANT OPTION`, and neither needs any privilege outside the
`oezcms` schema. `GET_LOCK`, which serialises concurrent deployments, requires no
privilege.

## Routine definer rights

MariaDB creates stored routines with `SQL SECURITY DEFINER` by default, so a routine
created by `oezcms_deploy` runs with that account's rights no matter who calls it.
Today this changes nothing in practice: no routine performs DDL, and every table a
routine touches is one the runtime may touch directly.

It does weaken the guarantee, though. A future routine doing something the runtime is
not allowed to do would be callable by the runtime anyway, because `EXECUTE` is granted
broadly. Declaring `SQL SECURITY INVOKER` on routines would close that, at the cost of
requiring callers to hold direct privileges on everything a routine reads.

Note also that the definer account must keep existing: dropping `oezcms_deploy` makes
every routine it created fail on invocation.

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
