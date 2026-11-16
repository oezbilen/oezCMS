# 0004. Size surrogate keys to the table

* Status: Accepted
* Date: 2026-07-18 (recorded 2026-11-16)

## Context

Every table needs a primary key, and InnoDB stores it in every secondary index as the
pointer back to the row. The key's width is therefore paid once per index, and again in
every table that references it.

## Decision

* The primary key is `id INT UNSIGNED AUTO_INCREMENT`: up to 4,294,967,295 rows.
* A lookup table whose size is bounded by nature may use a smaller type
  (`locales.id SMALLINT UNSIGNED`).
* A foreign key column has exactly the type of the column it references.
* Entities exposed through an API additionally carry `public_id UUID`. The internal id is
  never exposed.

## Alternatives considered

* **`BIGINT` everywhere.** Twice the width in every key and index, for a ceiling that the
  tables of a CMS do not approach.
* **A UUID as primary key.** Random values scatter inserts across the clustered index
  and widen every secondary index.
* **Exposing the auto-increment id.** It reveals row counts and invites enumeration.

## Consequences

* A table that could plausibly exceed four billion rows (an audit log, for example)
  decides its key type on its own and says so.
* Widening a key later means altering the primary key and every column that references
  it.
