-- Foreign keys are intentionally omitted to match the existing schema style.
-- Store only hashes for pairing codes, access tokens, and photo tokens.

CREATE TABLE extension_pairing_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_hash CHAR(64) NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  requester_hash CHAR(64) NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_extension_pairing_codes_hash (code_hash),
  KEY idx_extension_pairing_codes_admin_created (admin_user_id, created_at),
  KEY idx_extension_pairing_codes_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE extension_pairing_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requester_hash CHAR(64) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_extension_pairing_attempts_requester (requester_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE extension_access_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  installation_id VARCHAR(128) NOT NULL,
  extension_version VARCHAR(64) NOT NULL DEFAULT '',
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_extension_access_tokens_hash (token_hash),
  KEY idx_extension_access_tokens_admin (admin_user_id, revoked_at, expires_at),
  KEY idx_extension_access_tokens_installation (installation_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE extension_photo_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  admission_id BIGINT UNSIGNED NOT NULL,
  photo_id BIGINT UNSIGNED NOT NULL,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  installation_id VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_extension_photo_tokens_hash (token_hash),
  KEY idx_extension_photo_tokens_admission (admission_id, created_at),
  KEY idx_extension_photo_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE extension_api_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(128) NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  installation_id VARCHAR(128) NULL,
  action VARCHAR(80) NOT NULL,
  result_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_extension_api_events_request (request_id),
  KEY idx_extension_api_events_admin (admin_user_id, created_at),
  KEY idx_extension_api_events_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
