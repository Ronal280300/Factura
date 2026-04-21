-- ─────────────────────────────────────────────────────────────────────────────
-- Migracion 001: soporte Factura Electronica v4.4 (tipo documento, receptor,
-- CABYS, exoneracion, referencia, conversion CRC).
--
-- Idempotente para MySQL 8.0+ / MariaDB 10.3+ (usa IF NOT EXISTS).
-- Si tu version no lo soporta, borra el "IF NOT EXISTS" y maneja errores
-- manualmente (columna ya existe = ignorar).
-- ─────────────────────────────────────────────────────────────────────────────

-- ── invoices ────────────────────────────────────────────────────────────────
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS tipo_documento ENUM('FE','TE','NC','ND','FEE','FEC','REP') NOT NULL DEFAULT 'FE' AFTER clave,
  ADD COLUMN IF NOT EXISTS clave_referencia VARCHAR(60) NULL AFTER tipo_documento,

  ADD COLUMN IF NOT EXISTS receptor_nombre VARCHAR(255) NULL AFTER emisor_identificacion,
  ADD COLUMN IF NOT EXISTS receptor_identificacion VARCHAR(30) NULL AFTER receptor_nombre,
  ADD COLUMN IF NOT EXISTS receptor_tipo_identificacion VARCHAR(5) NULL AFTER receptor_identificacion,
  ADD COLUMN IF NOT EXISTS receptor_actividad_economica VARCHAR(20) NULL AFTER receptor_tipo_identificacion,

  ADD COLUMN IF NOT EXISTS total_exonerado DECIMAL(18,4) NULL AFTER total_exento,

  ADD COLUMN IF NOT EXISTS total_gravado_crc DECIMAL(18,4) NULL AFTER total_comprobante,
  ADD COLUMN IF NOT EXISTS total_exento_crc DECIMAL(18,4) NULL AFTER total_gravado_crc,
  ADD COLUMN IF NOT EXISTS total_exonerado_crc DECIMAL(18,4) NULL AFTER total_exento_crc,
  ADD COLUMN IF NOT EXISTS total_impuesto_crc DECIMAL(18,4) NULL AFTER total_exonerado_crc,
  ADD COLUMN IF NOT EXISTS total_comprobante_crc DECIMAL(18,4) NULL AFTER total_impuesto_crc,

  ADD COLUMN IF NOT EXISTS impuesto_diff DECIMAL(18,4) NULL AFTER total_comprobante_crc,

  ADD INDEX IF NOT EXISTS idx_tipo_doc (tipo_documento),
  ADD INDEX IF NOT EXISTS idx_receptor_id (receptor_identificacion),
  ADD INDEX IF NOT EXISTS idx_clave_ref (clave_referencia),
  ADD INDEX IF NOT EXISTS idx_fecha (fecha_emision);

-- ── invoice_tax_breakdown ───────────────────────────────────────────────────
ALTER TABLE invoice_tax_breakdown
  ADD COLUMN IF NOT EXISTS cabys VARCHAR(20) NULL AFTER tipo_gasto,
  ADD COLUMN IF NOT EXISTS exonerado DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER impuesto,
  ADD COLUMN IF NOT EXISTS impuesto_neto DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER exonerado,

  ADD COLUMN IF NOT EXISTS base_crc DECIMAL(18,4) NULL AFTER impuesto_neto,
  ADD COLUMN IF NOT EXISTS impuesto_crc DECIMAL(18,4) NULL AFTER base_crc,
  ADD COLUMN IF NOT EXISTS exonerado_crc DECIMAL(18,4) NULL AFTER impuesto_crc,
  ADD COLUMN IF NOT EXISTS impuesto_neto_crc DECIMAL(18,4) NULL AFTER exonerado_crc,

  ADD INDEX IF NOT EXISTS idx_cabys (cabys);

-- ── sync_runs ───────────────────────────────────────────────────────────────
ALTER TABLE sync_runs
  ADD COLUMN IF NOT EXISTS wrong_receptor INT NOT NULL DEFAULT 0 AFTER out_of_range;
