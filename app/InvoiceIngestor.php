<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/InvoiceXmlParser.php';

/**
 * Persiste un comprobante electronico parseado en la BD (invoices + breakdown).
 * Reutilizable desde el pipeline IMAP (FE recibidas) y desde la subida manual
 * de FE emitidas.
 */
class InvoiceIngestor {

  /**
   * @return array ['status'=>'saved'|'duplicate'|'skipped', 'invoice_id'=>?, 'reason'=>?]
   */
  public static function ingest(
    PDO $pdo,
    array $parsed,
    string $direction,            // 'received' | 'issued'
    ?string $xmlRelativePath = null,
    ?DateTime $fromDate = null,   // opcional: descarta si fecha fuera de rango
    ?DateTime $toDate = null,
    ?array $receptorCedulas = null,  // opcional: filtro para 'received'
    bool $strictReceptor = true,
    ?string $xmlRawContent = null    // opcional: para calcular sha256
  ): array {
    if (!in_array($direction, ['received','issued'], true)) {
      throw new InvalidArgumentException("direction invalida: {$direction}");
    }
    if (empty($parsed['clave']) || empty($parsed['fecha_emision'])) {
      return ['status' => 'skipped', 'reason' => 'sin_clave_o_fecha'];
    }

    $xmlDate = new DateTime($parsed['fecha_emision']);
    if ($fromDate && $xmlDate < $fromDate) return ['status'=>'skipped','reason'=>'fuera_de_rango'];
    if ($toDate   && $xmlDate > $toDate)   return ['status'=>'skipped','reason'=>'fuera_de_rango'];

    // Para FE recibidas, validar que el receptor sea el contribuyente
    if ($direction === 'received' && $strictReceptor && $receptorCedulas) {
      $rec = preg_replace('/\D+/', '', (string)($parsed['receptor_identificacion'] ?? ''));
      if ($rec === '' || !in_array($rec, $receptorCedulas, true)) {
        return ['status' => 'skipped', 'reason' => 'receptor_ajeno'];
      }
    }
    // Para FE emitidas, validar que el emisor sea el contribuyente
    if ($direction === 'issued' && $strictReceptor && $receptorCedulas) {
      $emi = preg_replace('/\D+/', '', (string)($parsed['emisor_identificacion'] ?? ''));
      if ($emi === '' || !in_array($emi, $receptorCedulas, true)) {
        return ['status' => 'skipped', 'reason' => 'emisor_ajeno'];
      }
    }

    $xmlSha = $xmlRawContent !== null ? hash('sha256', $xmlRawContent) : null;

    $pdo->beginTransaction();
    try {
      $pdo->prepare("
        INSERT INTO invoices
          (clave, direction, tipo_documento, clave_referencia, numero_consecutivo, fecha_emision,
           emisor_nombre, emisor_nombre_comercial, emisor_identificacion,
           receptor_nombre, receptor_identificacion, receptor_tipo_identificacion, receptor_actividad_economica,
           moneda, tipo_cambio,
           total_gravado, total_exento, total_exonerado, total_impuesto, total_comprobante,
           total_gravado_crc, total_exento_crc, total_exonerado_crc, total_impuesto_crc, total_comprobante_crc,
           impuesto_diff, xml_path, xml_sha256)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ")->execute([
        $parsed['clave'],
        $direction,
        $parsed['tipo_documento'],
        $parsed['clave_referencia'],
        $parsed['numero_consecutivo'],
        $xmlDate->format('Y-m-d H:i:s'),
        $parsed['emisor_nombre'],
        $parsed['emisor_nombre_comercial'],
        $parsed['emisor_identificacion'],
        $parsed['receptor_nombre'],
        $parsed['receptor_identificacion'],
        $parsed['receptor_tipo_identificacion'],
        $parsed['receptor_actividad_economica'],
        $parsed['moneda'],
        $parsed['tipo_cambio'],
        $parsed['total_gravado'],
        $parsed['total_exento'],
        $parsed['total_exonerado'],
        $parsed['total_impuesto'],
        $parsed['total_comprobante'],
        $parsed['total_gravado_crc'],
        $parsed['total_exento_crc'],
        $parsed['total_exonerado_crc'],
        $parsed['total_impuesto_crc'],
        $parsed['total_comprobante_crc'],
        $parsed['impuesto_diff'],
        $xmlRelativePath,
        $xmlSha,
      ]);

      $invoiceId = (int)$pdo->lastInsertId();

      $insBr = $pdo->prepare(
        "INSERT INTO invoice_tax_breakdown
           (invoice_id, tipo_gasto, cabys, tarifa, base, impuesto, exonerado, impuesto_neto,
            base_crc, impuesto_crc, exonerado_crc, impuesto_neto_crc)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );
      foreach ($parsed['breakdown'] as $b) {
        $impNeto = max(0.0, (float)$b['impuesto'] - (float)$b['exonerado']);
        $insBr->execute([
          $invoiceId, $b['tipo_gasto'], $b['cabys'], $b['tarifa'],
          $b['base'], $b['impuesto'], $b['exonerado'], $impNeto,
          $b['base_crc'], $b['impuesto_crc'], $b['exonerado_crc'], $b['impuesto_neto_crc'],
        ]);
      }

      $pdo->commit();
      return ['status' => 'saved', 'invoice_id' => $invoiceId];

    } catch (Throwable $e) {
      $pdo->rollBack();
      // Detectar duplicado por UNIQUE(clave, direction)
      if (strpos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'duplicate') !== false) {
        return ['status' => 'duplicate', 'reason' => 'clave_existente'];
      }
      throw $e;
    }
  }
}
