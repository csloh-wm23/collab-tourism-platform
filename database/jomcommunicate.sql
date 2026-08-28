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
  failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tourist_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  accessibility_notes VARCHAR(500) NULL,
  dietary_notes VARCHAR(500) NULL,
  allergy_notes VARCHAR(500) NULL,
  emergency_contact VARCHAR(120) NULL,
  emergency_details VARCHAR(500) NULL,
  default_destination VARCHAR(120) NULL,
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
  service_details TEXT NULL,
  payment_methods VARCHAR(500) NULL,
  menu_details TEXT NULL,
  facility_details TEXT NULL,
  verification_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  qr_slug VARCHAR(100) NULL UNIQUE,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
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
  suggested_reply VARCHAR(500) NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_phrase_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  question VARCHAR(500) NOT NULL,
  answer VARCHAR(1000) NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_faq_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  term VARCHAR(120) NOT NULL,
  explanation VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_business_term (business_id, term),
  CONSTRAINT fk_term_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS malaysian_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  term VARCHAR(120) NOT NULL UNIQUE,
  explanation VARCHAR(500) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General'
) ENGINE=InnoDB;

INSERT IGNORE INTO malaysian_terms (term, explanation, category) VALUES
('Tapau','Malaysian usage meaning food packed to take away.','Food'),
('Mamak','A casual Malaysian Indian Muslim eatery, often open late.','Food'),
('Surau','A small Muslim prayer room commonly found in public places.','Culture'),
('Nasi campur','Rice served with a selection of dishes chosen by the customer.','Food');

CREATE TABLE IF NOT EXISTS phrase_packs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destination VARCHAR(120) NOT NULL,
  scenario ENUM('restaurant','hotel','transport','shopping','medical','emergency','culture') NOT NULL,
  language_code VARCHAR(12) NOT NULL,
  source_text VARCHAR(500) NOT NULL,
  translated_text VARCHAR(500) NOT NULL,
  suggested_reply VARCHAR(500) NULL,
  cultural_tip VARCHAR(500) NULL,
  is_offline_ready TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pack_destination_scenario (destination, scenario)
) ENGINE=InnoDB;

INSERT INTO phrase_packs (destination,scenario,language_code,source_text,translated_text,suggested_reply,cultural_tip) VALUES
('Malaysia','restaurant','ms','Does this contain peanuts?','Adakah makanan ini mengandungi kacang tanah?','Tidak, makanan ini tidak mengandungi kacang tanah.','Say “terima kasih” to thank service staff.'),
('Malaysia','restaurant','ms','I need halal food without pork or alcohol.','Saya perlukan makanan halal tanpa khinzir atau alkohol.','Ya, makanan ini halal.','Ask before ordering because preparation methods can differ.'),
('Malaysia','hotel','ms','I have a reservation under this name.','Saya mempunyai tempahan atas nama ini.','Boleh saya lihat pengenalan anda?','Hotels commonly request identification at check-in.'),
('Malaysia','transport','ms','Does this train go to KL Sentral?','Adakah tren ini pergi ke KL Sentral?','Ya, turun di KL Sentral.','Queue behind the marked line while waiting.'),
('Malaysia','shopping','ms','Can I pay by card?','Boleh saya bayar dengan kad?','Ya, kad diterima.','Some small traders accept cash or QR payment only.'),
('Malaysia','medical','ms','I need a doctor and I am allergic to this medicine.','Saya perlukan doktor dan saya alah kepada ubat ini.','Saya akan hubungi bantuan perubatan.','Call 999 for a serious emergency.'),
('Malaysia','emergency','ms','Please call emergency services.','Sila hubungi perkhidmatan kecemasan.','Saya akan telefon 999 sekarang.','Malaysia emergency number: 999.');

CREATE TABLE IF NOT EXISTS user_destination_packs (
  user_id INT UNSIGNED NOT NULL,
  destination VARCHAR(120) NOT NULL,
  added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, destination),
  CONSTRAINT fk_pack_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  record_type ENUM('translation','phrase','report','business','emergency','consent') NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  source_language VARCHAR(12) NULL,
  target_language VARCHAR(12) NULL,
  scenario VARCHAR(40) NULL,
  confidence DECIMAL(4,3) NULL,
  is_favorite TINYINT(1) NOT NULL DEFAULT 0,
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
  INDEX idx_consent_user_type_recorded (user_id, consent_type, recorded_at),
  CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS translation_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_user_id INT UNSIGNED NULL,
  source_text VARCHAR(1000) NOT NULL,
  translated_text VARCHAR(1000) NOT NULL,
  source_language VARCHAR(12) NULL,
  target_language VARCHAR(12) NULL,
  scenario VARCHAR(40) NULL,
  confidence DECIMAL(4,3) NULL,
  issue_type VARCHAR(80) NOT NULL,
  notes VARCHAR(1000) NULL,
  status ENUM('open','reviewing','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_report_analysis (scenario, target_language, issue_type, created_at),
  CONSTRAINT fk_report_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(80) NOT NULL,
  language_code VARCHAR(12) NULL,
  scenario VARCHAR(40) NULL,
  location_label VARCHAR(120) NULL,
  business_type VARCHAR(80) NULL,
  term_label VARCHAR(120) NULL,
  confidence DECIMAL(4,3) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_analytics_created (created_at),
  INDEX idx_analytics_dimensions (event_type, language_code, scenario)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_interactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  language_code VARCHAR(12) NULL,
  question_label VARCHAR(250) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_business_interaction (business_id, created_at),
  CONSTRAINT fk_interaction_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
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
