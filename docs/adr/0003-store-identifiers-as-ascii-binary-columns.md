# 0003. Store identifiers as ASCII binary columns

* Status: Accepted
* Date: 2026-07-19 (recorded 2026-11-16)

## Context

Locale codes, translation domains and translation keys are technical identifiers. They
are compared exactly, looked up by value and never shown to a reader as prose.

The table default `utf8mb4_uca1400_ai_ci` is case- and accent-insensitive: under it,
`de` and `DE` are the same value, so are `resume` and `résumé`, and a unique key would
treat them as duplicates while a lookup would match either. utf8mb4 also reserves up to
four bytes per character in an index, which for `translation_key VARCHAR(255)` is 1020
bytes against 255.

## Decision

Identifier columns are declared `CHARACTER SET ascii COLLATE ascii_bin` at column level.
Tables keep `utf8mb4_uca1400_ai_ci` for display and content columns. Routine parameters
that receive an identifier declare the same character set and collation. Check
constraints define the permitted format, and since `ascii_bin` compares case-sensitively,
they also enforce lower case.

## Alternatives considered

* **`utf8mb4_bin` as the default of identifier tables**, the first design. It made the
  display columns of those tables (`native_name`) binary as well, so they would sort by
  code point instead of by language rules.
* **Keeping the case-insensitive default and normalising with `LOWER()`.** That leaves
  correctness to every writer, and the collisions remain possible.

## Consequences

* Identifiers cannot contain non-ASCII characters, by design.
* A routine parameter declared without the matching collation either fails with
  "Illegal mix of collations" or makes the comparison bypass the index, so every such
  parameter states it.
* A miscased identifier is rejected rather than quietly matched.
