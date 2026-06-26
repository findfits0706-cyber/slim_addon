-- Foreign keys are intentionally omitted to match the existing schema style.
-- The admission repository refreshes child rows inside the same transaction.

CREATE TABLE admissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id VARCHAR(64) NOT NULL,
  idempotency_key VARCHAR(128) NULL,
  application_status VARCHAR(32) NOT NULL DEFAULT 'new',
  slim_status VARCHAR(32) NOT NULL DEFAULT 'not_started',
  use_type VARCHAR(32) NOT NULL,
  main_member_status VARCHAR(32) NOT NULL DEFAULT '',
  main_member_number VARCHAR(64) NOT NULL DEFAULT '',
  slim_member_number VARCHAR(64) NOT NULL DEFAULT '',
  actual_procedure_date DATE NULL,
  start_date DATE NULL,
  course_key VARCHAR(64) NOT NULL DEFAULT '',
  main_membership_key VARCHAR(64) NOT NULL DEFAULT '',
  addon_key VARCHAR(64) NOT NULL DEFAULT '',
  surname VARCHAR(80) NOT NULL DEFAULT '',
  given_name VARCHAR(80) NOT NULL DEFAULT '',
  surname_kana VARCHAR(80) NOT NULL DEFAULT '',
  given_name_kana VARCHAR(80) NOT NULL DEFAULT '',
  birth DATE NULL,
  gender VARCHAR(16) NOT NULL DEFAULT '',
  phone_type VARCHAR(16) NOT NULL DEFAULT '',
  phone VARCHAR(32) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  postal_code VARCHAR(16) NOT NULL DEFAULT '',
  prefecture VARCHAR(32) NOT NULL DEFAULT '',
  city_area VARCHAR(120) NOT NULL DEFAULT '',
  street_address VARCHAR(120) NOT NULL DEFAULT '',
  building VARCHAR(120) NOT NULL DEFAULT '',
  emergency_name VARCHAR(120) NOT NULL DEFAULT '',
  emergency_relationship VARCHAR(80) NOT NULL DEFAULT '',
  emergency_phone VARCHAR(32) NOT NULL DEFAULT '',
  guardian_name VARCHAR(120) NOT NULL DEFAULT '',
  original_payload JSON NOT NULL,
  normalized_payload JSON NOT NULL,
  fee_snapshot JSON NOT NULL,
  mail_status JSON NULL,
  admin_note TEXT,
  version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admissions_application_id (application_id),
  UNIQUE KEY uq_admissions_idempotency_key (idempotency_key),
  KEY idx_admissions_created_at (created_at),
  KEY idx_admissions_status (application_status, slim_status),
  KEY idx_admissions_start_date (start_date),
  KEY idx_admissions_phone_email (phone, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admission_sensitive (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id BIGINT UNSIGNED NOT NULL,
  health_payload JSON NOT NULL,
  terms_snapshot JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admission_sensitive_admission (admission_id),
  KEY idx_admission_sensitive_admission (admission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admission_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id BIGINT UNSIGNED NOT NULL,
  preference_order TINYINT UNSIGNED NOT NULL,
  preferred_date DATE NULL,
  preferred_time VARCHAR(32) NOT NULL DEFAULT '',
  UNIQUE KEY uq_admission_preferences_order (admission_id, preference_order),
  KEY idx_admission_preferences_date (preferred_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admission_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id BIGINT UNSIGNED NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255) NOT NULL DEFAULT '',
  mime_type VARCHAR(100) NOT NULL DEFAULT '',
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admission_photos_admission (admission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
