# Architecture Decision Records

An architecture decision record (ADR) states one decision, the situation that forced it,
the alternatives that were turned down and what follows from it. The documents elsewhere
in `docs/` describe how the system is now; a record here says why it became so.

## When to write one

A decision gets a record when at least one of these holds:

* it is expensive to reverse,
* it constrains more than one module,
* it turns down a reasonable alternative that someone would otherwise propose again.

The record is written in the same branch as the change it decides, so both are reviewed
together. A decision taken before anything is built (an architecture that has no code yet)
is merged with the status `Proposed`.

## Rules

* One decision per record. Numbers are sequential and never reused.
* An accepted record is not rewritten. Only its status line and links change. Changing
  the decision means a new record that supersedes the old one, and both stay.
* A topic document links to the records behind it instead of repeating their reasoning.
* Decisions taken before this directory existed are recorded afterwards with their
  original date and say so.

Status values: `Proposed`, `Accepted`, `Rejected`, `Superseded by NNNN`.

New records start from [the template](template.md).

## Records

| #    | Decision                                                             | Status   |
|------|----------------------------------------------------------------------|----------|
| 0001 | [Record architecture decisions](0001-record-architecture-decisions.md) | Accepted |
| 0002 | [Name schema objects by type and module](0002-name-schema-objects-by-type-and-module.md) | Accepted |
| 0003 | [Store identifiers as ASCII binary columns](0003-store-identifiers-as-ascii-binary-columns.md) | Accepted |
| 0004 | [Size surrogate keys to the table](0004-size-surrogate-keys-to-the-table.md) | Accepted |
| 0005 | [Store timestamps as DATETIME(3) in UTC](0005-store-timestamps-as-datetime3-in-utc.md) | Accepted |
| 0006 | [Reference seed rows by natural key](0006-reference-seed-rows-by-natural-key.md) | Accepted |
| 0007 | [Track migrations as a state machine](0007-track-migrations-as-a-state-machine.md) | Accepted |
| 0008 | [Keep domain rules in the database](0008-keep-domain-rules-in-the-database.md) | Accepted |
| 0009 | [Organise code along one axis](0009-organise-code-along-one-axis.md) | Accepted |
| 0010 | [Keep a container of our own](0010-keep-a-container-of-our-own.md) | Accepted |
| 0011 | [Parse .env with a strict parser of our own](0011-parse-env-with-a-strict-parser-of-our-own.md) | Accepted |
