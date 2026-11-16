# 0006. Reference seed rows by natural key

* Status: Accepted
* Date: 2026-07-28 (recorded 2026-11-16)

## Context

The first locale seed wrote its own primary keys and pointed every locale's fallback at
the literal id `1`, meaning English. That number is whatever `AUTO_INCREMENT` handed out,
not something the schema promises: an earlier failed insert or a different
`auto_increment_increment` changes it. If the row with id 1 were not English, 22 locales
would silently fall back to the wrong one. Tests had absorbed the same assumption
("English is 1, German is 2").

## Decision

Seeds never write ids. A reference to another row is resolved by joining on its natural
key (`code`) inside the seeding statement. A row that others depend on is seeded first,
in its own migration.

## Alternatives considered

* **Explicit ids.** They turn an accident of insertion order into a fact the data
  depends on.
* **A scalar subquery inside `INSERT … VALUES`.** Not accepted for the target table;
  `INSERT … SELECT` with a join is.

## Consequences

* A missing reference makes the seed insert nothing, which shows in its row count,
  rather than inserting rows that point at the wrong place.
* Tests look up ids by code instead of assuming them.
* MariaDB has no derived-column-list syntax, so the first branch of a seed's union names
  the columns.
