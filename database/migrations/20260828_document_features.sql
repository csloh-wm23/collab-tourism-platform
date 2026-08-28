USE jomcommunicate;

ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER preferred_language;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_login_attempts;
ALTER TABLE tourist_profiles ADD COLUMN IF NOT EXISTS allergy_notes VARCHAR(500) NULL AFTER dietary_notes;
ALTER TABLE tourist_profiles ADD COLUMN IF NOT EXISTS emergency_details VARCHAR(500) NULL AFTER emergency_contact;
ALTER TABLE tourist_profiles ADD COLUMN IF NOT EXISTS default_destination VARCHAR(120) NULL AFTER emergency_details;
ALTER TABLE businesses ADD COLUMN IF NOT EXISTS service_details TEXT NULL AFTER description;
ALTER TABLE businesses ADD COLUMN IF NOT EXISTS payment_methods VARCHAR(500) NULL AFTER service_details;
ALTER TABLE businesses ADD COLUMN IF NOT EXISTS menu_details TEXT NULL AFTER payment_methods;
ALTER TABLE businesses ADD COLUMN IF NOT EXISTS facility_details TEXT NULL AFTER menu_details;
ALTER TABLE businesses ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER qr_slug;
ALTER TABLE business_phrases ADD COLUMN IF NOT EXISTS suggested_reply VARCHAR(500) NULL AFTER translated_text;
ALTER TABLE records ADD COLUMN IF NOT EXISTS source_language VARCHAR(12) NULL AFTER content;
ALTER TABLE records ADD COLUMN IF NOT EXISTS target_language VARCHAR(12) NULL AFTER source_language;
ALTER TABLE records ADD COLUMN IF NOT EXISTS scenario VARCHAR(40) NULL AFTER target_language;
ALTER TABLE records ADD COLUMN IF NOT EXISTS confidence DECIMAL(4,3) NULL AFTER scenario;
ALTER TABLE records ADD COLUMN IF NOT EXISTS is_favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER confidence;
ALTER TABLE translation_reports ADD COLUMN IF NOT EXISTS source_language VARCHAR(12) NULL AFTER translated_text;
ALTER TABLE translation_reports ADD COLUMN IF NOT EXISTS target_language VARCHAR(12) NULL AFTER source_language;
ALTER TABLE translation_reports ADD COLUMN IF NOT EXISTS scenario VARCHAR(40) NULL AFTER target_language;
ALTER TABLE translation_reports ADD COLUMN IF NOT EXISTS confidence DECIMAL(4,3) NULL AFTER scenario;

CREATE TABLE IF NOT EXISTS business_faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, business_id INT UNSIGNED NOT NULL,
  question VARCHAR(500) NOT NULL, answer VARCHAR(1000) NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_faq_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS business_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, business_id INT UNSIGNED NOT NULL,
  term VARCHAR(120) NOT NULL, explanation VARCHAR(500) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_business_term (business_id, term),
  CONSTRAINT fk_term_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS malaysian_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, term VARCHAR(120) NOT NULL UNIQUE,
  explanation VARCHAR(500) NOT NULL, category VARCHAR(80) NOT NULL DEFAULT 'General'
) ENGINE=InnoDB;
INSERT IGNORE INTO malaysian_terms (term,explanation,category) VALUES
('Tapau','Malaysian usage meaning food packed to take away.','Food'),
('Mamak','A casual Malaysian Indian Muslim eatery, often open late.','Food'),
('Surau','A small Muslim prayer room commonly found in public places.','Culture'),
('Nasi campur','Rice served with a selection of dishes chosen by the customer.','Food');
CREATE TABLE IF NOT EXISTS phrase_packs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, destination VARCHAR(120) NOT NULL,
  scenario ENUM('restaurant','hotel','transport','shopping','medical','emergency','culture') NOT NULL,
  language_code VARCHAR(12) NOT NULL, source_text VARCHAR(500) NOT NULL, translated_text VARCHAR(500) NOT NULL,
  suggested_reply VARCHAR(500) NULL, cultural_tip VARCHAR(500) NULL, is_offline_ready TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_pack_destination_scenario (destination, scenario)
) ENGINE=InnoDB;
INSERT INTO phrase_packs (destination,scenario,language_code,source_text,translated_text,suggested_reply,cultural_tip)
SELECT * FROM (
SELECT 'Malaysia','restaurant','ms','Does this contain peanuts?','Adakah makanan ini mengandungi kacang tanah?','Tidak, makanan ini tidak mengandungi kacang tanah.','Say “terima kasih” to thank service staff.' UNION ALL
SELECT 'Malaysia','restaurant','ms','I need halal food without pork or alcohol.','Saya perlukan makanan halal tanpa khinzir atau alkohol.','Ya, makanan ini halal.','Ask before ordering because preparation methods can differ.' UNION ALL
SELECT 'Malaysia','hotel','ms','I have a reservation under this name.','Saya mempunyai tempahan atas nama ini.','Boleh saya lihat pengenalan anda?','Hotels commonly request identification at check-in.' UNION ALL
SELECT 'Malaysia','transport','ms','Does this train go to KL Sentral?','Adakah tren ini pergi ke KL Sentral?','Ya, turun di KL Sentral.','Queue behind the marked line while waiting.' UNION ALL
SELECT 'Malaysia','shopping','ms','Can I pay by card?','Boleh saya bayar dengan kad?','Ya, kad diterima.','Some small traders accept cash or QR payment only.' UNION ALL
SELECT 'Malaysia','medical','ms','I need a doctor and I am allergic to this medicine.','Saya perlukan doktor dan saya alah kepada ubat ini.','Saya akan hubungi bantuan perubatan.','Call 999 for a serious emergency.' UNION ALL
SELECT 'Malaysia','emergency','ms','Please call emergency services.','Sila hubungi perkhidmatan kecemasan.','Saya akan telefon 999 sekarang.','Malaysia emergency number: 999.'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM phrase_packs LIMIT 1);
CREATE TABLE IF NOT EXISTS user_destination_packs (
  user_id INT UNSIGNED NOT NULL, destination VARCHAR(120) NOT NULL, added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id,destination), CONSTRAINT fk_pack_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS analytics_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_type VARCHAR(80) NOT NULL, language_code VARCHAR(12) NULL,
  scenario VARCHAR(40) NULL, location_label VARCHAR(120) NULL, business_type VARCHAR(80) NULL,
  term_label VARCHAR(120) NULL, confidence DECIMAL(4,3) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_analytics_created(created_at), INDEX idx_analytics_dimensions(event_type,language_code,scenario)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS business_interactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, business_id INT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General', language_code VARCHAR(12) NULL,
  question_label VARCHAR(250) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_business_interaction(business_id,created_at),
  CONSTRAINT fk_interaction_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
