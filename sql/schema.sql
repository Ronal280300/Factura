-- ─────────────────────────────────────────────────────────────────────────────
-- Schema IVA Sync — compatible con Factura Electrónica v4.4 (Hacienda CR)
-- Para instalaciones existentes, ejecutar ademas sql/migrations/001_v44.sql
-- ─────────────────────────────────────────────────────────────────────────────

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
  tipo_documento ENUM('FE','TE','NC','ND','FEE','FEC','REP') NOT NULL DEFAULT 'FE',
  clave_referencia VARCHAR(60) NULL,                        -- para NC/ND: clave de la FE original
  numero_consecutivo VARCHAR(30) NULL,
  fecha_emision DATETIME NOT NULL,

  -- Emisor
  emisor_nombre VARCHAR(255) NULL,
  emisor_nombre_comercial VARCHAR(255) NULL,
  emisor_identificacion VARCHAR(30) NULL,

  -- Receptor (obligatorio para deducir IVA en v4.4)
  receptor_nombre VARCHAR(255) NULL,
  receptor_identificacion VARCHAR(30) NULL,
  receptor_tipo_identificacion VARCHAR(5) NULL,             -- 01,02,03,04,05,06
  receptor_actividad_economica VARCHAR(20) NULL,            -- CAE v4.4

  -- Moneda (montos originales en la moneda del XML)
  moneda VARCHAR(10) NULL,
  tipo_cambio DECIMAL(18,6) NULL,

  total_gravado DECIMAL(18,4) NULL,
  total_exento DECIMAL(18,4) NULL,
  total_exonerado DECIMAL(18,4) NULL,
  total_impuesto DECIMAL(18,4) NULL,
  total_comprobante DECIMAL(18,4) NULL,

  -- Montos ya convertidos a CRC (para reportes consolidados)
  total_gravado_crc DECIMAL(18,4) NULL,
  total_exento_crc DECIMAL(18,4) NULL,
  total_exonerado_crc DECIMAL(18,4) NULL,
  total_impuesto_crc DECIMAL(18,4) NULL,
  total_comprobante_crc DECIMAL(18,4) NULL,

  -- Integridad: diferencia entre suma de lineas y ResumenFactura
  impuesto_diff DECIMAL(18,4) NULL,

  xml_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_tipo_doc (tipo_documento),
  INDEX idx_receptor_id (receptor_identificacion),
  INDEX idx_clave_ref (clave_referencia),
  INDEX idx_fecha (fecha_emision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_tax_breakdown (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  tipo_gasto ENUM('bien','servicio') NOT NULL DEFAULT 'bien',
  cabys VARCHAR(20) NULL,                                   -- codigo CABYS de la linea
  tarifa DECIMAL(5,2) NOT NULL,                             -- 13.00, 10.00, 4.00, 2.00, 1.00, 0.00
  base DECIMAL(18,4) NOT NULL DEFAULT 0,
  impuesto DECIMAL(18,4) NOT NULL DEFAULT 0,
  exonerado DECIMAL(18,4) NOT NULL DEFAULT 0,               -- IVA exonerado en la linea
  impuesto_neto DECIMAL(18,4) NOT NULL DEFAULT 0,           -- impuesto - exonerado (lo efectivamente pagado)

  base_crc DECIMAL(18,4) NULL,
  impuesto_crc DECIMAL(18,4) NULL,
  exonerado_crc DECIMAL(18,4) NULL,
  impuesto_neto_crc DECIMAL(18,4) NULL,

  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  INDEX (invoice_id),
  INDEX (tarifa),
  INDEX (tipo_gasto),
  INDEX (cabys)
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
  wrong_receptor INT NOT NULL DEFAULT 0,                    -- XMLs descartados por no pertenecer al contribuyente
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
