-- SQL to add last_log_fetch column to devices table
ALTER TABLE devices
ADD COLUMN last_log_fetch DATETIME NULL AFTER last_seen;