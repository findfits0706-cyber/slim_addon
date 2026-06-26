CREATE TABLE trial_slot_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slot_type ENUM('repeat', 'single') NOT NULL,
  genre ENUM('pilates', 'self_esthe', 'visit') NOT NULL,
  lesson_name VARCHAR(100) NOT NULL,
  instructor_name VARCHAR(100),
  weekday TINYINT NULL,
  single_date DATE NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT NOT NULL,
  repeat_start_date DATE NULL,
  repeat_end_date DATE NULL,
  description TEXT,
  status ENUM('open', 'closed', 'hidden') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trial_slot_exceptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  target_date DATE NOT NULL,
  exception_type ENUM('cancel', 'change', 'substitute') NOT NULL,
  new_start_time TIME NULL,
  new_end_time TIME NULL,
  substitute_instructor_name VARCHAR(100) NULL,
  new_capacity INT NULL,
  status ENUM('open', 'closed', 'hidden') NULL,
  note TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trial_slot_exceptions_template_date (template_id, target_date),
  FOREIGN KEY (template_id) REFERENCES trial_slot_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trial_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  booking_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  genre ENUM('pilates', 'self_esthe', 'visit') NOT NULL,
  lesson_name VARCHAR(100) NOT NULL,
  instructor_name VARCHAR(100),
  customer_name VARCHAR(100) NOT NULL,
  customer_kana VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(255) NOT NULL,
  age INT NULL,
  contact_method ENUM('phone', 'email', 'either') NOT NULL DEFAULT 'either',
  experience VARCHAR(100),
  trial_history VARCHAR(100),
  concern TEXT,
  customer_note TEXT,
  status ENUM('new', 'confirmed', 'cancelled', 'visited', 'joined') NOT NULL DEFAULT 'new',
  admin_note TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_trial_bookings_slot_date (template_id, booking_date, status),
  KEY idx_trial_bookings_customer (genre, email, phone, status),
  FOREIGN KEY (template_id) REFERENCES trial_slot_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example:
-- INSERT INTO admin_users (username, password_hash, display_name)
-- VALUES ('find', '<password_hash_generated_outside_git>', 'Find 管理者');
