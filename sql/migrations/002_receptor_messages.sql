-- ─────────────────────────────────────────────────────────────────────────────
-- Migracion 002: tabla receptor_messages (Mensaje Receptor para Hacienda).
-- Idempotente (usa CREATE TABLE IF NOT EXISTS).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS receptor_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  clave VARCHAR(60) NOT NULL,
  consecutivo_receptor VARCHAR(20) NOT NULL,

  mensaje ENUM('1','2','3') NOT NULL,
  codigo_actividad VARCHAR(20) NULL,
  condicion_impuesto VARCHAR(5) NULL,
  monto_total_impuesto_acreditar DECIMAL(18,4) NULL,
  monto_total_gasto_aplicable    DECIMAL(18,4) NULL,
  detalle_mensaje VARCHAR(255) NULL,

  xml_firmado LONGTEXT NULL,
  xml_respuesta LONGTEXT NULL,

  estado_hacienda ENUM('pendiente','firmado','enviado','aceptado','rechazado','error') NOT NULL DEFAULT 'pendiente',
  error TEXT NULL,

  fecha_envio DATETIME NULL,
  fecha_respuesta DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_clave_receptor (consecutivo_receptor),
  INDEX idx_clave (clave),
  INDEX idx_estado (estado_hacienda),
  INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
