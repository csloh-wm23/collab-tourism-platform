-- Run once on an existing database. Duplicate inboxes cause this statement to
-- fail safely: resolve ownership manually, never merge/delete accounts here.
ALTER TABLE users
  ADD COLUMN email_identity VARCHAR(190) GENERATED ALWAYS AS (
    CASE WHEN SUBSTRING_INDEX(LOWER(TRIM(email)), '@', -1) IN ('gmail.com', 'googlemail.com')
    THEN CONCAT(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER(TRIM(email)), '@', 1), '+', 1), '.', ''), '@gmail.com')
    ELSE LOWER(TRIM(email)) END
  ) STORED,
  ADD UNIQUE KEY uq_users_email_identity (email_identity);
