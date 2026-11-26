-- -------------------------------------------------------------------------------------------------
-- Admit Only Accepted Media Types And Sizes
--
-- PHP detects a file's type from its content and measures it; the database decides
-- whether it is accepted (ADR 0017). A type that is not active accepts no new files, and
-- no file may exceed the limit of its type.
--
-- The lookups are scalar subqueries, so an unknown type leaves both values NULL, passes
-- both tests and is then refused by fk_media_files_type.
--
-- The trigger runs with its definer's rights (ADR 0014). It only reads media_types and
-- refuses, which the runtime account may do anyway.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_media_files_before_insert
BEFORE INSERT ON media_files
FOR EACH ROW
BEGIN
    DECLARE v_is_active BOOLEAN DEFAULT NULL;
    DECLARE v_max_bytes INT UNSIGNED DEFAULT NULL;

    SET v_is_active = (SELECT t.is_active FROM media_types AS t WHERE t.id = NEW.media_type_id);
    SET v_max_bytes = (SELECT t.max_bytes FROM media_types AS t WHERE t.id = NEW.media_type_id);

    IF v_is_active = FALSE THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A media type that is not active accepts no new files';
    END IF;

    IF NEW.size_bytes > v_max_bytes THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'A file exceeds the size limit of its media type';
    END IF;
END;
