CREATE TABLE companies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  document VARCHAR(18) NOT NULL UNIQUE,
  timezone VARCHAR(60) NOT NULL DEFAULT 'America/Bahia',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schedules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, type ENUM('fixed','flexible','12x36','custom') DEFAULT 'fixed', work_start TIME NOT NULL, break_start TIME NULL, break_end TIME NULL, work_end TIME NOT NULL, weekly_minutes INT DEFAULT 2640, tolerance_minutes INT DEFAULT 10, night_start TIME DEFAULT '22:00:00', night_end TIME DEFAULT '05:00:00', active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_schedules_company FOREIGN KEY(company_id) REFERENCES companies(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employee_schedule_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id BIGINT UNSIGNED NOT NULL, schedule_id BIGINT UNSIGNED NOT NULL, starts_on DATE NOT NULL, ends_on DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_assignment_employee_date(employee_id,starts_on,ends_on)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE adjustments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, employee_id BIGINT UNSIGNED NOT NULL, work_date DATE NOT NULL, kind ENUM('missing_punch','delay','absence','medical','manual_punch','compensation') NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', adjusted_value TEXT NULL, reason TEXT NOT NULL, created_by BIGINT UNSIGNED NOT NULL, approved_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, approved_at DATETIME NULL, INDEX idx_adjustment_company_status(company_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE company_settings (company_id BIGINT UNSIGNED PRIMARY KEY, daily_tolerance_minutes INT DEFAULT 10, overtime_weekday_percent DECIMAL(5,2) DEFAULT 50, overtime_holiday_percent DECIMAL(5,2) DEFAULT 100, night_additional_percent DECIMAL(5,2) DEFAULT 20, night_hour_minutes INT DEFAULT 52, closing_day TINYINT UNSIGNED DEFAULT 25, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE devices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, manufacturer VARCHAR(100) NULL, model VARCHAR(100) NULL, serial_number VARCHAR(100) NULL, ip_address VARCHAR(45) NULL, status ENUM('online','offline','disabled') DEFAULT 'offline', last_sync_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE period_closures (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, status ENUM('open','closed') DEFAULT 'open', closed_by BIGINT UNSIGNED NULL, closed_at DATETIME NULL, UNIQUE KEY uq_period(company_id,starts_on,ends_on)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','rh','gestor','auditor') NOT NULL DEFAULT 'rh',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  registration VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  cpf VARCHAR(14) NULL,
  department VARCHAR(100) NOT NULL,
  job_title VARCHAR(100) NOT NULL,
  schedule_name VARCHAR(100) NOT NULL DEFAULT '44h semanais',
  status ENUM('active','inactive','vacation','leave') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_employee_registration (company_id, registration),
  CONSTRAINT fk_employees_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE punches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  punched_at DATETIME NOT NULL,
  source ENUM('rep','agent','manual','import') NOT NULL DEFAULT 'agent',
  nsr VARCHAR(50) NULL,
  original_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_punch_hash (original_hash),
  INDEX idx_punch_employee_date (employee_id, punched_at),
  CONSTRAINT fk_punches_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_punches_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id VARCHAR(80) NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_company_date (company_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
