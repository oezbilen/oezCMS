# 0009. Organise code along one axis

* Status: Accepted
* Date: 2026-07-28 (recorded 2026-11-16)

## Context

`src/` held two namespaces, `Core` and `Console`, with one dependency between them. A
review proposed a layered tree (`Domain`, `Application`, `Infrastructure`) alongside
feature modules (`Modules/I18n`, `Modules/Users`, …). Directories alone enforce nothing:
a file can import from anywhere regardless of where it sits.

## Decision

Code is ordered along one axis: `Core`, the delivery layers `Console` and `Http`, and
`Modules/<X>`. Each module publishes `Modules\X\Api`, the only part other code may use.
The dependency rules are checked by a hand-written architecture test in the normal test
run. The rules themselves are in
[module-boundaries.md](../development/module-boundaries.md).

## Alternatives considered

* **Layers and modules side by side.** Every class gets two plausible homes — does i18n
  logic belong under `Domain/I18n` or `Modules/I18n/Domain`? When two answers are
  defensible, code lands wherever its author was looking, which is how dumping grounds
  form.
* **Domain layers inside each module.** Much of what a domain layer would hold lives in
  the schema ([0008](0008-keep-domain-rules-in-the-database.md)). The result would be
  mostly empty folders, and an empty folder with an authoritative name attracts whatever
  needs a home.
* **`deptrac` or `phpat` from the start.** Both parse properly, and both cost a
  dependency, a configuration format and, for deptrac, a second CI step. At four layers
  that is not worth it.

## Consequences

* The inside of a module is not constrained yet; the pattern is to be read off two or
  three real modules.
* Cycles between modules are not checked until a second module exists.
* A class name assembled as a string is invisible to the check.
* When the rules outgrow a single test file, `deptrac` or `phpat` replaces it.
