-- Find Pilates trial schedule admin upgrade.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS plus guarded ALTER/INDEX helpers.

SET @old_foreign_key_checks = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS trial_instructors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  display_name VARCHAR(100) NOT NULL,
  supported_genres VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_trial_instructors_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resource_type ENUM('pilates_studio','esthe_booth','esthe_machine','visit_area','other') NOT NULL,
  resource_name VARCHAR(100) NOT NULL,
  capacity INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_trial_resources_type_active_sort (resource_type, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_closures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  closure_type ENUM('all','pilates','self_esthe','visit','resource','instructor','maintenance','cleaning','shooting','internal') NOT NULL,
  target_instructor_id INT NULL,
  target_resource_id INT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  title VARCHAR(120) NOT NULL,
  note TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_trial_closures_period (start_date, end_date, is_active),
  KEY idx_trial_closures_target (closure_type, target_instructor_id, target_resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_occurrences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NULL,
  occurrence_date DATE NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  cleanup_end_at DATETIME NULL,
  genre VARCHAR(30) NOT NULL,
  lesson_name VARCHAR(100) NOT NULL,
  instructor_id INT NULL,
  instructor_name VARCHAR(100) NULL,
  resource_id INT NULL,
  location_name VARCHAR(100) NULL,
  booth_name VARCHAR(100) NULL,
  equipment_name VARCHAR(100) NULL,
  capacity INT NOT NULL,
  status ENUM('open','closed','hidden','cancelled','archived') NOT NULL DEFAULT 'open',
  is_exception TINYINT(1) NOT NULL DEFAULT 0,
  requires_contact TINYINT(1) NOT NULL DEFAULT 0,
  version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trial_occurrence_template_date_time (template_id, occurrence_date, start_at, end_at),
  KEY idx_trial_occurrences_date_status (occurrence_date, status),
  KEY idx_trial_occurrences_resource_time (resource_id, start_at, end_at),
  KEY idx_trial_occurrences_instructor_time (instructor_id, start_at, end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_schedule_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_trial_schedule_templates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_schedule_template_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_template_id INT NOT NULL,
  weekday TINYINT NOT NULL,
  genre VARCHAR(30) NOT NULL,
  lesson_name VARCHAR(100) NOT NULL,
  instructor_name VARCHAR(100) NULL,
  location_name VARCHAR(100) NULL,
  booth_name VARCHAR(100) NULL,
  equipment_name VARCHAR(100) NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT NOT NULL,
  status ENUM('open','closed','hidden') NOT NULL DEFAULT 'open',
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  KEY idx_trial_schedule_template_items_template (schedule_template_id, weekday, start_time),
  CONSTRAINT fk_trial_schedule_template_items_template
    FOREIGN KEY (schedule_template_id) REFERENCES trial_schedule_templates(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_import_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  import_type VARCHAR(40) NOT NULL,
  file_name VARCHAR(255) NULL,
  total_rows INT NOT NULL DEFAULT 0,
  imported_rows INT NOT NULL DEFAULT 0,
  skipped_rows INT NOT NULL DEFAULT 0,
  status ENUM('preview','imported','failed') NOT NULL DEFAULT 'preview',
  created_by VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trial_import_batches_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_import_rows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  row_number INT NOT NULL,
  row_json JSON NULL,
  status ENUM('valid','imported','skipped','error') NOT NULL DEFAULT 'valid',
  message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trial_import_rows_batch (batch_id, row_number),
  CONSTRAINT fk_trial_import_rows_batch
    FOREIGN KEY (batch_id) REFERENCES trial_import_batches(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trial_audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  actor_name VARCHAR(100) NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id VARCHAR(80) NULL,
  action VARCHAR(80) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trial_audit_logs_entity (entity_type, entity_id, created_at),
  KEY idx_trial_audit_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DELIMITER //
CREATE PROCEDURE add_column_if_missing(IN in_table_name VARCHAR(64), IN in_column_name VARCHAR(64), IN in_column_sql TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = in_table_name
       AND COLUMN_NAME = in_column_name
  ) THEN
    SET @alter_sql = CONCAT('ALTER TABLE `', in_table_name, '` ADD COLUMN ', in_column_sql);
    PREPARE alter_stmt FROM @alter_sql;
    EXECUTE alter_stmt;
    DEALLOCATE PREPARE alter_stmt;
  END IF;
END//
DELIMITER ;

CALL add_column_if_missing('trial_slot_templates', 'instructor_id', '`instructor_id` INT NULL AFTER `instructor_name`');
CALL add_column_if_missing('trial_slot_templates', 'location_name', '`location_name` VARCHAR(100) NULL AFTER `instructor_id`');
CALL add_column_if_missing('trial_slot_templates', 'booth_name', '`booth_name` VARCHAR(100) NULL AFTER `location_name`');
CALL add_column_if_missing('trial_slot_templates', 'equipment_name', '`equipment_name` VARCHAR(100) NULL AFTER `booth_name`');
CALL add_column_if_missing('trial_slot_templates', 'resource_id', '`resource_id` INT NULL AFTER `equipment_name`');
CALL add_column_if_missing('trial_slot_templates', 'cleanup_minutes', '`cleanup_minutes` INT NOT NULL DEFAULT 0 AFTER `capacity`');
CALL add_column_if_missing('trial_slot_templates', 'booking_open_at', '`booking_open_at` DATETIME NULL AFTER `status`');
CALL add_column_if_missing('trial_slot_templates', 'booking_close_at', '`booking_close_at` DATETIME NULL AFTER `booking_open_at`');
CALL add_column_if_missing('trial_slot_templates', 'cutoff_minutes', '`cutoff_minutes` INT NULL AFTER `booking_close_at`');
CALL add_column_if_missing('trial_slot_templates', 'admin_note', '`admin_note` TEXT NULL AFTER `description`');
CALL add_column_if_missing('trial_slot_templates', 'recurrence_interval_weeks', '`recurrence_interval_weeks` INT NOT NULL DEFAULT 1 AFTER `repeat_end_date`');
CALL add_column_if_missing('trial_slot_templates', 'archived_at', '`archived_at` DATETIME NULL AFTER `updated_at`');
CALL add_column_if_missing('trial_slot_templates', 'version', '`version` INT NOT NULL DEFAULT 1 AFTER `archived_at`');

CALL add_column_if_missing('trial_slot_exceptions', 'requires_contact', '`requires_contact` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');
CALL add_column_if_missing('trial_slot_exceptions', 'version', '`version` INT NOT NULL DEFAULT 1 AFTER `created_at`');

CALL add_column_if_missing('trial_bookings', 'occurrence_id', '`occurrence_id` INT NULL AFTER `id`');
CALL add_column_if_missing('trial_bookings', 'contact_required', '`contact_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `admin_note`');
CALL add_column_if_missing('trial_bookings', 'contacted_at', '`contacted_at` DATETIME NULL AFTER `contact_required`');
CALL add_column_if_missing('trial_bookings', 'assigned_staff', '`assigned_staff` VARCHAR(100) NULL AFTER `contacted_at`');
CALL add_column_if_missing('trial_bookings', 'version', '`version` INT NOT NULL DEFAULT 1 AFTER `updated_at`');

DROP PROCEDURE IF EXISTS add_index_if_missing;
DELIMITER //
CREATE PROCEDURE add_index_if_missing(IN in_table_name VARCHAR(64), IN in_index_name VARCHAR(64), IN in_index_sql TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = in_table_name
       AND INDEX_NAME = in_index_name
  ) THEN
    SET @index_sql = CONCAT('ALTER TABLE `', in_table_name, '` ADD ', in_index_sql);
    PREPARE index_stmt FROM @index_sql;
    EXECUTE index_stmt;
    DEALLOCATE PREPARE index_stmt;
  END IF;
END//
DELIMITER ;

CALL add_index_if_missing('trial_slot_templates', 'idx_trial_slot_templates_genre_status_time', 'INDEX `idx_trial_slot_templates_genre_status_time` (`genre`, `status`, `start_time`, `end_time`)');
CALL add_index_if_missing('trial_slot_templates', 'idx_trial_slot_templates_instructor', 'INDEX `idx_trial_slot_templates_instructor` (`instructor_id`, `instructor_name`)');
CALL add_index_if_missing('trial_bookings', 'idx_trial_bookings_occurrence', 'INDEX `idx_trial_bookings_occurrence` (`occurrence_id`, `status`)');
CALL add_index_if_missing('trial_bookings', 'idx_trial_bookings_contact_required', 'INDEX `idx_trial_bookings_contact_required` (`contact_required`, `booking_date`)');

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;

SET FOREIGN_KEY_CHECKS = @old_foreign_key_checks;
