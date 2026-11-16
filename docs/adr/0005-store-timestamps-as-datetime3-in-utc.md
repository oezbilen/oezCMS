# 0005. Store timestamps as DATETIME(3) in UTC

* Status: Accepted
* Date: 2026-07-18 (recorded 2026-11-16)

## Context

MariaDB offers two column types for points in time. `TIMESTAMP` converts between the
session's time zone and UTC on every read and write and ends on 2038-01-19. `DATETIME`
stores what it is given, unchanged, up to the year 9999.

Several planned features order events that can happen within the same second: login
attempts, sessions, audit entries.

## Decision

Points in time are stored as `DATETIME(3)` (millisecond precision) and hold UTC.
Conversion to a reader's time zone happens at presentation.

## Alternatives considered

* **`TIMESTAMP`.** The 2038 limit, and an implicit conversion that changes values when
  the session's time zone changes.
* **`DATETIME(6)`.** Microsecond precision has no consumer and costs another byte per
  column.
* **Local time.** Ambiguous for an hour every year when clocks go back.

## Consequences

* Nothing in a `DATETIME` column says it is UTC; the convention has to be upheld by
  whatever writes it.
* `DEFAULT CURRENT_TIMESTAMP(3)` returns the session's time zone. The connection does
  not set one yet, so today the convention holds only if the database server runs in UTC.
  Setting the session time zone at connect is an open task.
