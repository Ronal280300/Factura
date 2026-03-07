CREATE TABLE IF NOT EXISTS processed_emails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_uid VARCHAR(64) NOT NULL UNIQUE,
  received_at DATETIME NULL,
  processed_at DATETIME NOT NULL,
  status ENUM('done','error') NOT NULL DEFAULT 'done',
  error TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(60) NOT NULL UNIQUE,
  numero_consecutivo VARCHAR(30) NULL,
  fecha_emision DATETIME NOT NULL,
  emisor_nombre VARCHAR(255) NULL,
  emisor_nombre_comercial VARCHAR(255) NULL,
  emisor_identificacion VARCHAR(30) NULL,
  moneda VARCHAR(10) NULL,
  tipo_cambio DECIMAL(18,6) NULL,

  total_gravado DECIMAL(18,4) NULL,
  total_exento DECIMAL(18,4) NULL,
  total_impuesto DECIMAL(18,4) NULL,
  total_comprobante DECIMAL(18,4) NULL,

  -- Control manual por el usuario
  excluida TINYINT(1) NOT NULL DEFAULT 0,
  override_tipo ENUM('bien','servicio') NULL DEFAULT NULL,

  xml_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_tax_breakdown (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  tipo_gasto ENUM('bien','servicio') NOT NULL DEFAULT 'bien',
  tarifa DECIMAL(5,2) NOT NULL,           -- 13.00, 10.00, 4.00, 2.00, 1.00, 0.00
  base DECIMAL(18,4) NOT NULL DEFAULT 0,
  impuesto DECIMAL(18,4) NOT NULL DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  INDEX (invoice_id),
  INDEX (tarifa),
  INDEX (tipo_gasto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status ENUM('pending','running','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  from_date DATE NOT NULL,
  to_date DATE NOT NULL,
  total_messages INT NOT NULL DEFAULT 0,
  processed_messages INT NOT NULL DEFAULT 0,
  found_xml INT NOT NULL DEFAULT 0,
  new_invoices INT NOT NULL DEFAULT 0,
  duplicates INT NOT NULL DEFAULT 0,
  out_of_range INT NOT NULL DEFAULT 0,
  errors INT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_run_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sync_run_id INT NOT NULL,
  message_uid VARCHAR(64) NOT NULL,
  email_date DATETIME NULL,
  from_email VARCHAR(255) NULL,
  subject VARCHAR(255) NULL,
  status ENUM('pending','processing','done','error','skipped') NOT NULL DEFAULT 'pending',
  error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sync_run_id) REFERENCES sync_runs(id) ON DELETE CASCADE,
  INDEX (sync_run_id, status),
  UNIQUE KEY uniq_run_uid (sync_run_id, message_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- MIGRACION para instalaciones existentes (MySQL 8.0+):
-- Ejecutar una sola vez si invoice_tax_breakdown ya existe sin tipo_gasto:
--
--   ALTER TABLE invoice_tax_breakdown
--     ADD COLUMN IF NOT EXISTS tipo_gasto ENUM('bien','servicio') NOT NULL DEFAULT 'bien' AFTER invoice_id,
--     ADD INDEX IF NOT EXISTS idx_tipo_gasto (tipo_gasto);
--
-- Para MySQL 5.x / MariaDB (verificar antes si la columna existe):
--   ALTER TABLE invoice_tax_breakdown
--     ADD COLUMN tipo_gasto ENUM('bien','servicio') NOT NULL DEFAULT 'bien' AFTER invoice_id;
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MIGRACION v1.2: columnas de control manual en invoices
-- Ejecutar si la tabla invoices ya existe sin estas columnas:
--
--   ALTER TABLE invoices
--     ADD COLUMN excluida TINYINT(1) NOT NULL DEFAULT 0,
--     ADD COLUMN override_tipo ENUM('bien','servicio') NULL DEFAULT NULL;
-- ─────────────────────────────────────────────────────────────────────────────
