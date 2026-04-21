-- ─────────────────────────────────────────────────────────────────────────────
-- Migracion 004: Fase 4 — autenticacion, auditoria e integridad XML.
-- Idempotente.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','accountant','viewer') NOT NULL DEFAULT 'accountant',
  full_name VARCHAR(190) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  failed_attempts INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  ip VARCHAR(45) NULL,
  action VARCHAR(64) NOT NULL,
  target VARCHAR(190) NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS xml_sha256 CHAR(64) NULL AFTER xml_path;
