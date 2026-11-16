# 0002. Name schema objects by type and module

* Status: Accepted
* Date: 2026-07-19 (recorded 2026-11-16)

## Context

Core, modules and plugins all create objects in one schema, and nothing but the name
keeps them apart. Tables and views share a namespace in MariaDB, so a view and a table
cannot carry the same name, and in a query nothing shows which of the two is being read.
Functions and procedures have namespaces of their own, and grants distinguish them
(`EXECUTE ON FUNCTION` and `EXECUTE ON PROCEDURE`).

## Decision

* Tables are snake_case and plural. Domain tables have no prefix, framework internals use
  `oezcms_`, plugin tables `plugin_<name>_`.
* Every other object is named `<type>_<module>_<name>`: `fn_`, `sp_`, `v_`. Triggers are
  named after their table and firing point: `trg_<table>_<timing>_<event>`.
* Constraints and indexes carry their kind and table: `uk_`, `idx_`, `chk_`, `fk_`.

## Alternatives considered

* **A prefix on every table**, as some CMSs use to share a database with other
  applications. Sharing a database is not supported here, and the prefix would appear in
  every query for no gain.
* **Names without a type part.** A view would then be indistinguishable from a table at
  the point of use, and a module's view could collide with another module's table.

## Consequences

* The module part is the namespace that keeps plugins apart; plugin objects use their
  plugin name there.
* A name says what an object is and who owns it without looking it up.
* `oezcms_migration`, created after this decision, is singular and therefore deviates
  from it.
