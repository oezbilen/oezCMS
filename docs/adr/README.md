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
