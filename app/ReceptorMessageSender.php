<?php

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/HaciendaClient.php';
require_once __DIR__ . '/ReceptorMessageBuilder.php';
require_once __DIR__ . '/SignatureProvider.php';

/**
 * Orquesta el envio de un Mensaje Receptor para una factura: construye el XML,
 * lo firma, lo sube a Hacienda y persiste el resultado en receptor_messages.
 *
 * Reutilizable desde endpoints HTTP y tareas en lote.
 */
class ReceptorMessageSender {

  private PDO $pdo;
  private array $cfg;
  private array $hcfg;
  private ?HaciendaClient $client = null;
  private ?SignatureProvider $signer = null;

  public function __construct(PDO $pdo, array $cfg) {
    $this->pdo  = $pdo;
    $this->cfg  = $cfg;
    $this->hcfg = $cfg['hacienda'] ?? [];
  }

  /**
   * @param int    $invoiceId
   * @param string $mensaje            '1'|'2'|'3'
   * @param array  $opts               ['detalle'=>..., 'condicion_impuesto'=>..., 'monto_acreditar'=>..., 'monto_gasto'=>...]
   * @return array Fila resultante de receptor_messages.
   * @throws Exception
   */
  public function send(int $invoiceId, string $mensaje, array $opts = []): array {
    if (!in_array($mensaje, ['1','2','3'], true)) {
      throw new InvalidArgumentException("mensaje debe ser '1','2' o '3'");
    }

    // Cargar factura
    $st = $this->pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $st->execute([$invoiceId]);
    $inv = $st->fetch();
    if (!$inv) throw new Exception('Factura no encontrada');
    if (!in_array($inv['tipo_documento'], ['FE','FEE','FEC'], true)) {
      throw new Exception("Tipo {$inv['tipo_documento']} no requiere Mensaje Receptor");
    }

    // Idempotencia
    $prev = $this->pdo->prepare("SELECT * FROM receptor_messages
                                 WHERE invoice_id = ? AND estado_hacienda IN ('enviado','aceptado')
                                 ORDER BY id DESC LIMIT 1");
    $prev->execute([$invoiceId]);
    if ($row = $prev->fetch()) {
      return $row + ['reused' => true];
    }

    // Consecutivo receptor
    $seq = (int)$this->pdo->query("SELECT COUNT(*) FROM receptor_messages")->fetchColumn() + 1;
    $consecutivo = ReceptorMessageBuilder::buildConsecutivoReceptor($seq);

    // CAE
    $cae = $inv['receptor_actividad_economica'] ?: ($this->cfg['receptor_codigo_actividad'] ?? '');
    if (!$cae) throw new Exception('CAE del receptor desconocido. Configurar receptor_codigo_actividad o reprocesar la factura.');

    $condicion = (string)($opts['condicion_impuesto'] ?? $this->hcfg['condicion_impuesto_default'] ?? '01');
    $detalle   = trim((string)($opts['detalle'] ?? ''))
              ?: ['1'=>'Aceptado','2'=>'Aceptado parcial','3'=>'Rechazado'][$mensaje];

    // XML
    $xml = ReceptorMessageBuilder::build([
      'clave'                         => $inv['clave'],
      'fecha_emision_doc'             => str_replace(' ', 'T', $inv['fecha_emision']) . '-06:00',
      'emisor_cedula'                 => $inv['emisor_identificacion'],
      'receptor_cedula'               => $inv['receptor_identificacion'],
      'consecutivo_receptor'          => $consecutivo,
      'mensaje'                       => $mensaje,
      'detalle_mensaje'               => $detalle,
      'monto_total_impuesto'          => (float)($inv['total_impuesto_crc'] ?: $inv['total_impuesto']),
      'codigo_actividad'              => $cae,
      'condicion_impuesto'            => $condicion,
      'monto_total_impuesto_acreditar'=> $opts['monto_acreditar'] ?? null,
      'monto_total_gasto_aplicable'   => $opts['monto_gasto']     ?? null,
    ]);

    // Insertar registro pendiente
    $this->pdo->prepare("
      INSERT INTO receptor_messages
        (invoice_id, clave, consecutivo_receptor, mensaje, codigo_actividad, condicion_impuesto,
         monto_total_impuesto_acreditar, monto_total_gasto_aplicable, detalle_mensaje, estado_hacienda)
      VALUES (?,?,?,?,?,?,?,?,?, 'pendiente')
    ")->execute([
      $invoiceId, $inv['clave'], $consecutivo, $mensaje, $cae, $condicion,
      $opts['monto_acreditar'] ?? null,
      $opts['monto_gasto']     ?? null,
      $detalle ?: null,
    ]);
    $mrId = (int)$this->pdo->lastInsertId();

    // Firmar
    try {
      $xmlFirmado = $this->signer()->sign($xml);
      $this->pdo->prepare("UPDATE receptor_messages SET xml_firmado = ?, estado_hacienda = 'firmado' WHERE id = ?")
                ->execute([$xmlFirmado, $mrId]);
    } catch (Throwable $e) {
      $this->pdo->prepare("UPDATE receptor_messages SET estado_hacienda = 'error', error = ? WHERE id = ?")
                ->execute([$e->getMessage(), $mrId]);
      throw new Exception('Firma fallo: ' . $e->getMessage());
    }

    // Subir a Hacienda
    try {
      [$code, $resp] = $this->client()->upload(
        $inv['clave'],
        str_replace(' ', 'T', $inv['fecha_emision']) . '-06:00',
        ['tipoIdentificacion' => '02', 'numeroIdentificacion' => $inv['emisor_identificacion']],
        ['tipoIdentificacion' => $inv['receptor_tipo_identificacion'] ?: '02', 'numeroIdentificacion' => $inv['receptor_identificacion']],
        $xmlFirmado
      );
      if ($code >= 200 && $code < 300) {
        $this->pdo->prepare("UPDATE receptor_messages SET estado_hacienda='enviado', fecha_envio=NOW() WHERE id=?")
                  ->execute([$mrId]);
      } else {
        $this->pdo->prepare("UPDATE receptor_messages SET estado_hacienda='error', error=? WHERE id=?")
                  ->execute(["Hacienda HTTP {$code}: {$resp}", $mrId]);
        throw new Exception("Envio rechazado por Hacienda (HTTP {$code}): {$resp}");
      }
    } catch (Throwable $e) {
      $this->pdo->prepare("UPDATE receptor_messages SET estado_hacienda='error', error=? WHERE id=?")
                ->execute([$e->getMessage(), $mrId]);
      throw $e;
    }

    return $this->pdo->query("SELECT * FROM receptor_messages WHERE id = {$mrId}")->fetch();
  }

  private function client(): HaciendaClient {
    if (!$this->client) $this->client = new HaciendaClient($this->hcfg);
    return $this->client;
  }

  private function signer(): SignatureProvider {
    if (!$this->signer) $this->signer = SignatureProviderFactory::fromConfig($this->hcfg);
    return $this->signer;
  }
}
