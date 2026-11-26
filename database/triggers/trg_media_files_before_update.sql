-- -------------------------------------------------------------------------------------------------
-- Keep Stored Files Unchanged
--
-- A row of media_files describes bytes on disk that are named after their own hash and
-- never written again (ADR 0016). Changing any column would make the row describe
-- something other than the file, so every update is refused. A changed image is a new
-- file under a new hash, and the media entry is pointed at it.
-- -------------------------------------------------------------------------------------------------

CREATE OR REPLACE TRIGGER trg_media_files_before_update
BEFORE UPDATE ON media_files
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A stored file cannot be changed';
END;
