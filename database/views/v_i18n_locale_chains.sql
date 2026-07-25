-- -------------------------------------------------------------------------------------------------
-- Locale Fallback Chains
--
-- Expands every locale into its fallback chain, one row per step. The locale itself is
-- depth 1, its fallback depth 2, and so on.
--
-- Anchors include inactive locales, so a disabled locale still exposes the chain it
-- falls through. Callers decide which steps may contribute a value; translation reads
-- values from active locales only.
--
-- Traversal stops after fn_i18n_max_depth() steps, and revisiting a locale already on
-- the path stops that branch. Write-time triggers on locales already reject cycles, so
-- the path guard is a second line of defence that keeps the view terminating on data
-- those triggers never saw.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE VIEW v_i18n_locale_chains AS
WITH RECURSIVE chain AS (
    SELECT
        l.id AS root_locale_id,
        l.id AS locale_id,
        l.fallback_locale_id AS next_locale_id,
        1 AS depth,
        CAST(l.id AS CHAR(255)) AS path
    FROM locales AS l

    UNION ALL

    SELECT
        c.root_locale_id,
        n.id,
        n.fallback_locale_id,
        c.depth + 1,
        CONCAT(c.path, ',', n.id)
    FROM chain AS c
    JOIN locales AS n
      ON n.id = c.next_locale_id
    WHERE c.depth < fn_i18n_max_depth()
      AND FIND_IN_SET(n.id, c.path) = 0
)
SELECT
    chain.root_locale_id,
    chain.locale_id,
    chain.depth
FROM chain;
