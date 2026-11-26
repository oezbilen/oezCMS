# 0018. Keep media texts per locale

* Status: Proposed
* Date: 2026-11-26

## Context

A media entry has a title and, for images, an alternative text. Alt text describes the
image for someone who cannot see it, so it is written in a language for readers of that
language. The schema already knows locales and their fallback chains
(`v_i18n_locale_chains`).

The translation tables, `translation_keys` and `translation_values`, hold interface
strings. Their keys are technical identifiers that the code names, such as
`navigation.home`.

## Decision

Title and alt text live in `media_texts`. The table has one row per media entry and locale,
held by a unique key on `(media_id, locale_id)`.

* The foreign key to `media` cascades, because a text means nothing without its entry.
* The foreign key to `locales` restricts, as every reference to a locale does.

A read for a locale tries that locale first and then its fallback chain, taken from
`v_i18n_locale_chains` rather than walked again. A read with no text along the chain
returns none and does not raise.

The dependency runs one way only: Media's schema refers to I18n's, and I18n knows nothing
of media. Neither has PHP that names the other.

## Alternatives considered

* **`title` and `alt_text` as columns of `media`.** One language only. Adding a second one
  later means restructuring a table that already holds data.
* **Translation keys per entry.** Every import would create keys in `translation_keys`,
  mixing content with interface strings. The keys would be assembled from ids rather than
  named by code, and removing an entry would have to find them by string.
* **Deferring texts to a later phase.** It postpones the same schema change and adds a data
  migration to it.

## Consequences

* An entry can carry texts in some locales and not in others. Which ones are missing can
  be answered by a query.
* Deleting a locale that has media texts is refused, as it is for translations.
* Media becomes the first schema module that depends on another one. The direction is
  recorded here, so that a reference back from I18n stands out in review.
