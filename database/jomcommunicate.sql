CREATE DATABASE IF NOT EXISTS jomcommunicate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jomcommunicate;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('tourist','business','editor','admin') NOT NULL DEFAULT 'tourist',
  status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  preferred_language VARCHAR(12) NOT NULL DEFAULT 'en',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tourist_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  emergency_contact VARCHAR(60) NULL,
  dietary_notes VARCHAR(500) NULL,
  accessibility_notes VARCHAR(500) NULL,
  large_text TINYINT(1) NOT NULL DEFAULT 0,
  voice_playback TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_tourist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS businesses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL,
  address VARCHAR(500) NOT NULL,
  description TEXT NULL,
  verification_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  qr_slug VARCHAR(100) NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_business_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_phrases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  source_language VARCHAR(12) NOT NULL DEFAULT 'en',
  target_language VARCHAR(12) NOT NULL DEFAULT 'ms',
  source_text VARCHAR(500) NOT NULL,
  translated_text VARCHAR(500) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_phrase_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  record_type ENUM('translation','phrase','report','business','emergency','consent') NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_records_user_created (user_id, created_at),
  INDEX idx_records_type_created (record_type, created_at),
  CONSTRAINT fk_record_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consent_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  consent_type VARCHAR(80) NOT NULL,
  is_granted TINYINT(1) NOT NULL,
  policy_version VARCHAR(30) NOT NULL,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS translation_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_user_id INT UNSIGNED NULL,
  source_text VARCHAR(1000) NOT NULL,
  translated_text VARCHAR(1000) NOT NULL,
  issue_type VARCHAR(80) NOT NULL,
  notes VARCHAR(1000) NULL,
  status ENUM('open','reviewing','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(160) NOT NULL,
  area VARCHAR(80) NOT NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
