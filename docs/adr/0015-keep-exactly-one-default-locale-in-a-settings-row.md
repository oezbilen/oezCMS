# 0015. Keep exactly one default locale in a settings row

* Status: Accepted
* Date: 2026-11-25

## Context

The default locale is the last-resort translation fallback: `fn_i18n_translate` appends
it behind every fallback chain. It was a flag on `locales`, limited to one row by a unique
key over a generated `NULLIF(is_default, 0)` column. Triggers kept the flagged locale
active and refused deleting it.

The unique key guarantees at most one default, not exactly one. Any account with `UPDATE`
on `locales` could clear the flag, and once it was cleared the two triggers had nothing
left to protect:

```sql
UPDATE locales SET is_default = FALSE WHERE code = 'en';
UPDATE locales SET is_active = FALSE WHERE code = 'en';
DELETE FROM locales WHERE code = 'en';
```

The second and third statements are what the triggers existed to refuse. The rule was
only half in the database ([0008](0008-keep-domain-rules-in-the-database.md)).

A row trigger cannot close this. `sp_i18n_set_default_locale` has to clear the old flag
before it sets the new one, because the unique key is checked row by row, so a trigger
refusing a cleared flag would refuse the switch as well. For the same reason the
procedure opened a transaction of its own, which barred calling it from inside an
application transaction.

## Decision

The default locale moves into `locale_settings`, a table that holds exactly one row:

* The primary key is pinned to `1` by a check, so a second row has nowhere to go.
* A seed migration inserts the row, and `trg_locale_settings_before_delete` refuses
  removing it. `default_locale_id` is `NOT NULL`, so the row cannot be emptied instead.
* The foreign key references `(id, is_active)` on `locales`, through a unique key on that
  pair, and the referencing state column is pinned to `TRUE` by a check. The default is
  then always an active locale. Deactivating or deleting the default would leave the
  reference pointing at a key that no longer exists, so the foreign key refuses both.

`is_default`, its generated column, its unique key and the triggers built around the
flag are removed. Switching the default is a single `UPDATE` of the settings row, so
there is no moment without a default. `sp_i18n_set_default_locale` keeps its signature,
needs no transaction of its own and may be called from inside one. Its lookup only turns
an unknown or inactive code into a readable refusal; the foreign key is what enforces the
rule, including against a concurrent deactivation.

The baseline migrations are edited rather than followed by a migration that converts
the flag. No release exists yet, and
[schema-conventions.md](../database/schema-conventions.md#files) allows exactly that.
Development databases are recreated.

## Alternatives considered

* **A trigger refusing a cleared flag.** It would refuse the switch itself, as described
  above, unless the switch were split differently. No order of the two writes avoids a
  moment with zero or two defaults.
* **Column privileges, with the switch only through the procedure.** Since
  [0014](0014-run-routines-and-views-with-the-callers-rights.md), routines run with their
  caller's rights, so a runtime without `UPDATE` on the column could not call the
  procedure either. It would need a definer-rights exception and table and column grants
  in place of the grant on `oezcms.*`.
* **Keep at most one default and document it.** The read path does degrade to the
  technical key without a default, but the deletion and deactivation guards would stay
  removable by a single `UPDATE`.
* **Wait for the settings of phase 8.** A general key-value settings table cannot carry
  a typed foreign key, so it could not hold this rule. This is an invariant of the
  translation model, not a preference.
* **A migration converting the flag.** It would keep the baseline unchanged at the cost
  of a schema history that never existed in a release.

## Consequences

* There is no state without a default. A test that deleted or cleared it now expects
  the refusal.
* `fn_i18n_translate` reads the default from `locale_settings`.
* Deactivating or deleting the default is refused by
  `fk_locale_settings_active_default_locale`. The error names the constraint, not a rule
  text as a `SIGNAL` would.
* `sp_i18n_lock_locale_id_from_code` had no other caller and is removed, and so is
  `trg_locales_before_delete`, whose job the foreign key took over.
* Development databases have to be recreated: the baseline migrations change, and
  `db:deploy` does not drop objects whose files were removed.
* Settings for other parts of the system are not decided here. The fallback depth may
  join this table in phase 8 or move elsewhere.
