-- Foreign keys are intentionally omitted to match the existing schema style.
-- The admission repository regenerates not-started child rows inside one transaction.

CREATE TABLE admission_slim_operations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id BIGINT UNSIGNED NOT NULL,
  sequence_no TINYINT UNSIGNED NOT NULL,
  operation_key VARCHAR(96) NOT NULL,
  operation_type VARCHAR(64) NOT NULL,
  page_type VARCHAR(64) NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  course_code VARCHAR(32) NOT NULL,
  business_label VARCHAR(160) NOT NULL,
  slim_option_texts JSON NOT NULL,
  reason_id VARCHAR(32) NULL,
  reason_label VARCHAR(80) NULL,
  payment_cycle VARCHAR(32) NULL,
  payment_cycle_label VARCHAR(80) NULL,
  application_date DATE NULL,
  start_date DATE NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error_code VARCHAR(80) NOT NULL DEFAULT '',
  last_error_summary VARCHAR(255) NOT NULL DEFAULT '',
  readiness_errors JSON NULL,
  started_at DATETIME NULL,
  started_by BIGINT UNSIGNED NULL,
  filled_at DATETIME NULL,
  filled_by BIGINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  completed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admission_slim_operations_sequence (admission_id, sequence_no),
  KEY idx_admission_slim_operations_admission_status (admission_id, status),
  KEY idx_admission_slim_operations_page (page_type),
  KEY idx_admission_slim_operations_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admission_slim_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(128) NOT NULL,
  admission_id BIGINT UNSIGNED NOT NULL,
  operation_id BIGINT UNSIGNED NULL,
  actor_admin_id BIGINT UNSIGNED NULL,
  extension_installation_id VARCHAR(128) NULL,
  action VARCHAR(80) NOT NULL,
  result_json JSON NOT NULL,
  page_profile_version VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admission_slim_events_request (request_id),
  KEY idx_admission_slim_events_admission (admission_id, created_at),
  KEY idx_admission_slim_events_operation (operation_id),
  KEY idx_admission_slim_events_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admission_locks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id BIGINT UNSIGNED NOT NULL,
  owner_admin_id BIGINT UNSIGNED NULL,
  installation_id VARCHAR(128) NOT NULL DEFAULT '',
  acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_admission_locks_admission (admission_id),
  KEY idx_admission_locks_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
