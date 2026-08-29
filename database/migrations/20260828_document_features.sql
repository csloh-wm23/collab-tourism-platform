USE jomcommunicate;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DELIMITER //
CREATE PROCEDURE add_column_if_missing(IN p_table VARCHAR(64),IN p_column VARCHAR(64),IN p_definition TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND COLUMN_NAME=p_column) THEN
    SET @migration_sql=CONCAT('ALTER TABLE `',REPLACE(p_table,'`','``'),'` ADD COLUMN `',REPLACE(p_column,'`','``'),'` ',p_definition);
    PREPARE migration_statement FROM @migration_sql;
    EXECUTE migration_statement;
    DEALLOCATE PREPARE migration_statement;
  END IF;
END//
DELIMITER ;

CALL add_column_if_missing('users','failed_login_attempts','TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER preferred_language');
CALL add_column_if_missing('users','locked_until','DATETIME NULL AFTER failed_login_attempts');
CALL add_column_if_missing('tourist_profiles','allergy_notes','VARCHAR(500) NULL AFTER dietary_notes');
CALL add_column_if_missing('tourist_profiles','emergency_details','VARCHAR(500) NULL AFTER emergency_contact');
CALL add_column_if_missing('tourist_profiles','default_destination','VARCHAR(120) NULL AFTER emergency_details');
CALL add_column_if_missing('businesses','service_details','TEXT NULL AFTER description');
CALL add_column_if_missing('businesses','payment_methods','VARCHAR(500) NULL AFTER service_details');
CALL add_column_if_missing('businesses','menu_details','TEXT NULL AFTER payment_methods');
CALL add_column_if_missing('businesses','facility_details','TEXT NULL AFTER menu_details');
CALL add_column_if_missing('businesses','is_public','TINYINT(1) NOT NULL DEFAULT 1 AFTER qr_slug');
CALL add_column_if_missing('business_phrases','suggested_reply','VARCHAR(500) NULL AFTER translated_text');
CALL add_column_if_missing('records','source_language','VARCHAR(12) NULL AFTER content');
CALL add_column_if_missing('records','target_language','VARCHAR(12) NULL AFTER source_language');
CALL add_column_if_missing('records','scenario','VARCHAR(40) NULL AFTER target_language');
CALL add_column_if_missing('records','confidence','DECIMAL(4,3) NULL AFTER scenario');
CALL add_column_if_missing('records','is_favorite','TINYINT(1) NOT NULL DEFAULT 0 AFTER confidence');
CALL add_column_if_missing('translation_reports','source_language','VARCHAR(12) NULL AFTER translated_text');
CALL add_column_if_missing('translation_reports','target_language','VARCHAR(12) NULL AFTER source_language');
CALL add_column_if_missing('translation_reports','scenario','VARCHAR(40) NULL AFTER target_language');
CALL add_column_if_missing('translation_reports','confidence','DECIMAL(4,3) NULL AFTER scenario');
ALTER TABLE translation_reports MODIFY source_text VARCHAR(1000) NULL;
ALTER TABLE translation_reports MODIFY translated_text VARCHAR(1000) NULL;
CALL add_column_if_missing('translation_reports','source_hash','CHAR(64) NULL AFTER translated_text');
CALL add_column_if_missing('translation_reports','term_label','VARCHAR(120) NULL AFTER issue_type');

CREATE TABLE IF NOT EXISTS business_profile_translations (
  business_id INT UNSIGNED NOT NULL, language_code VARCHAR(12) NOT NULL,
  description TEXT NULL, service_details TEXT NULL, payment_methods VARCHAR(500) NULL,
  menu_details TEXT NULL, facility_details TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(business_id,language_code),
  CONSTRAINT fk_profile_translation_business FOREIGN KEY(business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO business_profile_translations(business_id,language_code,description,service_details,payment_methods,menu_details,facility_details)
SELECT id,'en',description,service_details,payment_methods,menu_details,facility_details FROM businesses;

CREATE TABLE IF NOT EXISTS business_faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, business_id INT UNSIGNED NOT NULL,
  question VARCHAR(500) NOT NULL, answer VARCHAR(1000) NOT NULL, language_code VARCHAR(12) NOT NULL DEFAULT 'en',
  is_published TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_faq_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS business_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, business_id INT UNSIGNED NOT NULL,
  term VARCHAR(120) NOT NULL, explanation VARCHAR(500) NOT NULL, language_code VARCHAR(12) NOT NULL DEFAULT 'en', is_published TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_business_term (business_id, term),
  CONSTRAINT fk_term_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CALL add_column_if_missing('business_terms','is_published','TINYINT(1) NOT NULL DEFAULT 1 AFTER explanation');
CALL add_column_if_missing('business_faqs','language_code',"VARCHAR(12) NOT NULL DEFAULT 'en' AFTER answer");
CALL add_column_if_missing('business_terms','language_code',"VARCHAR(12) NOT NULL DEFAULT 'en' AFTER explanation");
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
INSERT INTO phrase_packs (destination,scenario,language_code,source_text,translated_text,suggested_reply,cultural_tip)
SELECT seed.* FROM (
SELECT 'Malaysia' AS destination,'restaurant' AS scenario,'ms' AS language_code,'Please show me the vegetarian options.' AS source_text,'Sila tunjukkan pilihan vegetarian.' AS translated_text,'Ini ialah pilihan vegetarian kami.' AS suggested_reply,'Confirm sauces and stock separately when discussing dietary needs.' AS cultural_tip UNION ALL
SELECT 'Malaysia','hotel','ms','What time is check-out?','Pukul berapa waktu daftar keluar?','Waktu daftar keluar ialah pukul dua belas tengah hari.','Keep your room key and identification available.' UNION ALL
SELECT 'Malaysia','hotel','ms','Is breakfast included with my room?','Adakah sarapan termasuk dengan bilik saya?','Ya, sarapan disediakan di tingkat bawah.','Ask the front desk about meal times and locations.' UNION ALL
SELECT 'Malaysia','transport','ms','Which platform should I use?','Saya perlu menggunakan platform yang mana?','Sila gunakan platform dua.','Check the service name as well as the platform number.' UNION ALL
SELECT 'Malaysia','transport','ms','Please tell me when we reach this stop.','Sila beritahu saya apabila kita sampai di hentian ini.','Baik, saya akan beritahu anda.','Show the written destination if pronunciation is difficult.' UNION ALL
SELECT 'Malaysia','shopping','ms','How much does this cost?','Berapakah harga barang ini?','Harganya dua puluh ringgit.','Prices are normally displayed in Malaysian ringgit.' UNION ALL
SELECT 'Malaysia','shopping','ms','Can I exchange this item?','Bolehkah saya menukar barang ini?','Boleh, sila tunjukkan resit anda.','Keep the receipt and ask about the exchange policy.' UNION ALL
SELECT 'Malaysia','medical','ms','Where is the nearest clinic?','Di manakah klinik yang terdekat?','Klinik itu terletak di jalan sebelah.','Bring identification and a list of current medicines.' UNION ALL
SELECT 'Malaysia','medical','ms','These are my symptoms and current medicines.','Ini ialah gejala dan ubat yang sedang saya ambil.','Sila terangkan bila gejala itu bermula.','Use the emergency assistant for severe or urgent symptoms.' UNION ALL
SELECT 'Malaysia','emergency','ms','I am at this location and need an ambulance.','Saya berada di lokasi ini dan memerlukan ambulans.','Bantuan sedang dalam perjalanan.','Share a landmark or written address when possible.' UNION ALL
SELECT 'Malaysia','emergency','ms','Please contact this emergency person.','Sila hubungi orang kecemasan ini.','Baik, saya akan menghubungi mereka.','Keep emergency contact details in the journey profile.'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM phrase_packs p WHERE p.destination=seed.destination AND p.scenario=seed.scenario AND p.source_text=seed.source_text);
CREATE TABLE IF NOT EXISTS user_destination_packs (
  user_id INT UNSIGNED NOT NULL, destination VARCHAR(120) NOT NULL, added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id,destination), CONSTRAINT fk_pack_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS user_saved_packs (
  user_id INT UNSIGNED NOT NULL, destination VARCHAR(120) NOT NULL,
  scenario VARCHAR(40) NOT NULL, language_code VARCHAR(12) NOT NULL,
  added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(user_id,destination,scenario,language_code),
  CONSTRAINT fk_saved_pack_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO user_saved_packs(user_id,destination,scenario,language_code)
SELECT user_id,destination,'restaurant','ms' FROM user_destination_packs;
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

DROP PROCEDURE IF EXISTS add_column_if_missing;
