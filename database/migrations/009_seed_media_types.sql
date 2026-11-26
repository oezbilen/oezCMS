-- -------------------------------------------------------------------------------------------------
-- Seed Data: Media Types
--
-- The types media starts with: the common web image formats and PDF. Still images get
-- 20 MiB, GIF 10 MiB because a larger one is almost always an animation better kept as
-- video, and PDF 50 MiB.
--
-- SVG is left out on purpose. It may contain script, and accepting it needs a decision
-- of its own (ADR 0017).
--
-- ORDER BY keeps the ids reproducible, as in the locale seed.
-- -------------------------------------------------------------------------------------------------

INSERT INTO media_types (mime_type, max_bytes)
SELECT seed.mime_type, seed.max_bytes
FROM (
    SELECT 'image/jpeg' AS mime_type, 20971520 AS max_bytes, 10 AS sort_order
    UNION ALL SELECT 'image/png',       20971520, 20
    UNION ALL SELECT 'image/webp',      20971520, 30
    UNION ALL SELECT 'image/avif',      20971520, 40
    UNION ALL SELECT 'image/gif',       10485760, 50
    UNION ALL SELECT 'application/pdf', 52428800, 60
) AS seed
ORDER BY seed.sort_order;
