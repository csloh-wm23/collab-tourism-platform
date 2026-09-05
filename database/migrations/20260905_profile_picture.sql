USE jomcommunicate;

-- Store only an application-relative path. Uploaded image files remain outside
-- the database and can be replaced without changing account records.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL AFTER preferred_language;
