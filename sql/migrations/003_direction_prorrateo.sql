-- ─────────────────────────────────────────────────────────────────────────────
-- Migracion 003: Fase 3 — FE emitidas (direction) + prorrateo anual.
--
-- 1) Cambia la UNIQUE(clave) por UNIQUE(clave, direction) para permitir que
--    una misma clave exista en ambos sentidos (raro pero posible en
--    autofacturacion o intercambio entre empresas del mismo grupo).
-- 2) Agrega invoices.direction con default 'received' (todas las existentes).
-- 3) Crea tabla prorrateo_anual.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS direction ENUM('received','issued') NOT NULL DEFAULT 'received' AFTER clave,
  ADD INDEX IF NOT EXISTS idx_direction (direction),
  ADD INDEX IF NOT EXISTS idx_emisor_id (emisor_identificacion);

-- Sustituye UNIQUE(clave) por UNIQUE(clave, direction). En MySQL esto no es
-- idempotente nativamente; se hace en dos pasos tolerantes a errores.
-- Si el indice no existe, DROP fallara; el script usa IF EXISTS.
ALTER TABLE invoices DROP INDEX clave;
-- ↑ OJO: si tu version lanza error por indice no existente, comentar la linea
-- anterior. Luego crear el nuevo:
ALTER TABLE invoices
  ADD UNIQUE KEY IF NOT EXISTS uniq_clave_direction (clave, direction);

-- Prorrateo
CREATE TABLE IF NOT EXISTS prorrateo_anual (
  anio SMALLINT NOT NULL PRIMARY KEY,
  ratio_provisional DECIMAL(8,6) NOT NULL DEFAULT 1.000000,
  ratio_definitivo  DECIMAL(8,6) NULL,
  ventas_gravadas   DECIMAL(18,4) NULL,
  ventas_exentas    DECIMAL(18,4) NULL,
  ventas_no_sujetas DECIMAL(18,4) NULL,
  total_ventas      DECIMAL(18,4) NULL,
  calculado_en      DATETIME NULL,
  notas TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
