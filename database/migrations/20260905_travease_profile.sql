USE jomcommunicate;

-- Profile management accepts the same 120-character emergency contact limit
-- enforced by the application and used by fresh installations.
ALTER TABLE tourist_profiles
  MODIFY emergency_contact VARCHAR(120) NULL;
