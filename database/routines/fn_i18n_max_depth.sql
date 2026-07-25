-- -------------------------------------------------------------------------------------------------
-- Locale Fallback Depth
--
-- Maximum number of locales considered when resolving a fallback chain, counting the
-- requested locale itself. Every read path shares this value, so the chain a
-- translation walks and the chain fn_i18n_locale_resolve follows cannot drift apart.
--
-- The cap is an unconditional termination guarantee for reads. Write-time triggers
-- already reject cycles, but a read must terminate regardless of how the data was
-- produced, so this never signals; it only stops.
--
-- When the settings module lands this becomes a lookup. The bound must then be
-- validated where the setting is written, never signalled from here: a read path that
-- raises turns a misconfiguration into a broken page instead of a degraded one.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_i18n_max_depth()
RETURNS TINYINT UNSIGNED
DETERMINISTIC
NO SQL
COMMENT 'Maximum number of locales considered when resolving a fallback chain'
BEGIN
    RETURN 3;
END;
