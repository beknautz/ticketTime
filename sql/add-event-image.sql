-- Add event banner image column
ALTER TABLE events
    ADD COLUMN event_image VARCHAR(255) NULL DEFAULT NULL AFTER event_description;
