# Schema Conventions

How tables, columns and database objects are shaped. How they reach a database is in
[schema-deployment.md](../deployment/schema-deployment.md); why each rule is what it is
is in the linked [decision records](../adr/README.md).

## Tables

* Names are snake_case and plural: `locales`, `translation_keys`.
* Domain tables carry no prefix. Framework internals are prefixed `oezcms_`, plugin tables
  `plugin_<name>_` ([0002](../adr/0002-name-schema-objects-by-type-and-module.md)).
* Every table states its engine, character set and collation, and has a comment:

  ```sql
  ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci
    COMMENT='…';
  ```

Known deviation: the deploy tracking table is `oezcms_migration`, singular.

## Keys

* The primary key is `id INT UNSIGNED NOT NULL AUTO_INCREMENT`. A lookup table whose size
  is bounded by nature may use a smaller type (`locales.id` is `SMALLINT UNSIGNED`)
  ([0004](../adr/0004-size-surrogate-keys-to-the-table.md)).
* A foreign key column is named after what it references (`locale_id`,
  `translation_key_id`, `fallback_locale_id`) and has exactly the referenced column's type.
* Referential actions are always written out. `RESTRICT` is the rule; `CASCADE` is used
  only where a child row means nothing without its parent (`translation_values` on
  `translation_keys`).
* Entities exposed through an API additionally get `public_id UUID`, and only that leaves
  the system. No such entity exists yet.

## Columns

| Kind | Shape |
|---|---|
| Identifier (code, domain, technical key) | `VARCHAR(n) CHARACTER SET ascii COLLATE ascii_bin` ([0003](../adr/0003-store-identifiers-as-ascii-binary-columns.md)) |
| Display text and content | table default (`utf8mb4_uca1400_ai_ci`) |
| Boolean | `is_<name> BOOLEAN NOT NULL DEFAULT …` plus `CHECK (is_<name> IN (0,1))` |
| Creation time | `created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)` |
| Modification time | `updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)` |
| Soft deletion | `deleted_at DATETIME(3) DEFAULT NULL` |
| Manual ordering | `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0` |

`BOOLEAN` is `TINYINT(1)` and accepts any small integer, which is what the check is for.

Times are UTC ([0005](../adr/0005-store-timestamps-as-datetime3-in-utc.md)). Every
connection sets its session time zone to `+00:00`, so `CURRENT_TIMESTAMP(3)` returns UTC
whatever zone the server runs in. A time produced in PHP and written to the database must
be UTC as well; nothing does so yet.

Soft deletion hides a row; it does not release its identity. Unique keys stay
unconditional, and restoring means clearing `deleted_at`, not inserting again.

## Constraint and index names

| Kind | Pattern | Example |
|---|---|---|
| Unique key | `uk_<table>_<purpose>` | `uk_locales_code` |
| Index | `idx_<table>_<purpose>` | `idx_locales_fallback` |
| Check | `chk_<table>_<purpose>` | `chk_locales_code` |
| Foreign key | `fk_<table>_<purpose>` | `fk_locales_fallback_locale` |

## Database objects

| Object | Pattern | Example |
|---|---|---|
| Function | `fn_<module>_<name>` | `fn_i18n_translate` |
| Procedure | `sp_<module>_<name>` | `sp_i18n_set_default_locale` |
| View | `v_<module>_<name>` | `v_i18n_locale_chains` |
| Trigger | `trg_<table>_<timing>_<event>` | `trg_locales_before_update` |

* Parameters are prefixed `p_`, local variables `v_`. Procedure parameters always state
  `IN` or `OUT`.
* A parameter that receives an identifier declares the identifier column's character set
  and collation. A mismatch either fails with a collation error or silently bypasses the
  index.
* A procedure returns data to PHP as a result set, never through `OUT`:
  `Database::callProcedure()` reads result sets. `OUT` is for routines calling routines
  (`sp_i18n_lock_locale_id_from_code`).
* A procedure that opens its own transaction says so in its header and must not be
  called inside `Database::transaction()` (`sp_i18n_set_default_locale`).

## Files

* Migrations: `database/migrations/NNN_description.sql`, applied once, in filename order
  ([0007](../adr/0007-track-migrations-as-a-state-machine.md)).
* Routines, views, triggers: `database/<phase>/<object name>.sql`, written as
  `CREATE OR REPLACE`, no number. The file is the object's current state; git is its
  history.
* One file holds exactly one statement and opens with a comment block saying what the
  object is for and why it is shaped the way it is.
* Seed data is a migration. It refers to other rows by natural key, never by a literal id
  ([0006](../adr/0006-reference-seed-rows-by-natural-key.md)).
* Until the first release, baseline migrations may be edited and development databases
  are recreated. From the first release on, migrations are immutable.

## Where an invariant goes

| Invariant | Mechanism | Example |
|---|---|---|
| A property of the row as stored | `CHECK` | `chk_translation_keys_system_not_deleted` |
| A transition, or a deletion | `BEFORE` trigger, `SIGNAL SQLSTATE '45000'` with a message naming the rule | `trg_translation_keys_before_update` |
| At most one row with a flag set | unique key over a generated `NULLIF(flag, 0)` column | `locales.default_flag` |
| A bound on a read | cap and degrade, never signal | `fn_i18n_max_depth()` |

A check validates the resulting row; it never sees the transition that produced it, and it
never sees a `DELETE`. That is why transitions and deletions need triggers.

Limits to keep in mind:

* MariaDB rejects a check that references an `AUTO_INCREMENT` column (error 1901).
* Triggers fire neither for `TRUNCATE` nor for rows removed by a cascading parent. The
  privilege split in [database-privileges.md](../deployment/database-privileges.md) is
  what closes those paths.
* A read path never raises: a misconfiguration should degrade a page, not break it.
