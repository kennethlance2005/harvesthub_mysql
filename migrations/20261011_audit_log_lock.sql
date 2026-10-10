-- =========================================================
-- Make AUDIT_LOG append-only: nobody (not even an administrator using the
-- app or phpMyAdmin) can change or delete an entry once it is written.
--
-- Run AFTER 20261011_audit_log_and_roles.sql. Kept separate because some
-- hosted databases restrict CREATE TRIGGER; if this file fails there, the
-- main migration still works and the app still never edits the log.
-- =========================================================

DROP TRIGGER IF EXISTS audit_log_no_update;
DROP TRIGGER IF EXISTS audit_log_no_delete;

DELIMITER $$

CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON AUDIT_LOG
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be changed.';
END$$

CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON AUDIT_LOG
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be deleted.';
END$$

DELIMITER ;
