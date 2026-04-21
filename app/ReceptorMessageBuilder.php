<?php

/**
 * Construye el XML del Mensaje Receptor v4.4 segun los Anexos y Estructuras
 * publicados por Hacienda CR (Resolucion MH-DGT-RES-0027-2024).
 *
 * Elemento raiz: <MensajeReceptor>
 * Namespace:     https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/mensajeReceptor
 *
 * Campos clave:
 *   Clave                     — clave de 50 digitos de la FE a responder
 *   NumeroCedulaEmisor        — emisor de la FE original
 *   FechaEmisionDoc           — fecha ISO-8601 de la FE original
 *   Mensaje                   — 1 acepta | 2 acepta parcial | 3 rechaza
 *   DetalleMensaje            — texto libre
 *   MontoTotalImpuesto        — IVA de la FE que se va a acreditar
 *   CodigoActividad           — CAE del receptor
 *   CondicionImpuesto         — 01..05 (uso del bien/servicio)
 *   MontoTotalImpuestoAcreditar (opcional) — parte efectivamente acreditable
 *   MontoTotalDeGastoAplicable (opcional)  — monto que va a gasto
 *   NumeroCedulaReceptor      — cedula del contribuyente que responde
 *   NumeroConsecutivoReceptor — 20 digitos del mensaje (no de la FE)
 */
class ReceptorMessageBuilder {

  private const NS = 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/mensajeReceptor';

  /**
   * @param array $opts claves:
   *   - clave (FE)                      string 50 digitos
   *   - fecha_emision_doc (FE)          string ISO-8601
   *   - emisor_cedula                   string
   *   - receptor_cedula                 string
   *   - consecutivo_receptor            string 20 digitos
   *   - mensaje                         '1' | '2' | '3'
   *   - detalle_mensaje                 string <=160
   *   - monto_total_impuesto            float  (IVA de la FE original)
   *   - codigo_actividad                string CAE del receptor (v4.4 obligatorio)
   *   - condicion_impuesto              '01'..'05'
   *   - monto_total_impuesto_acreditar  float opcional
   *   - monto_total_gasto_aplicable     float opcional
   */
  public static function build(array $o): string {
    $required = ['clave','fecha_emision_doc','emisor_cedula','receptor_cedula',
                 'consecutivo_receptor','mensaje','codigo_actividad','condicion_impuesto'];
    foreach ($required as $k) {
      if (!isset($o[$k]) || $o[$k] === '') {
        throw new InvalidArgumentException("ReceptorMessageBuilder: falta campo '{$k}'");
      }
    }
    if (!in_array($o['mensaje'], ['1','2','3'], true)) {
      throw new InvalidArgumentException("mensaje debe ser '1', '2' o '3'");
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = false;

    $root = $dom->createElementNS(self::NS, 'MensajeReceptor');
    $dom->appendChild($root);

    self::addText($dom, $root, 'Clave',              $o['clave']);
    self::addText($dom, $root, 'NumeroCedulaEmisor', $o['emisor_cedula']);
    self::addText($dom, $root, 'FechaEmisionDoc',    $o['fecha_emision_doc']);
    self::addText($dom, $root, 'Mensaje',            $o['mensaje']);

    if (!empty($o['detalle_mensaje'])) {
      self::addText($dom, $root, 'DetalleMensaje', mb_substr($o['detalle_mensaje'], 0, 160));
    }

    if (isset($o['monto_total_impuesto'])) {
      self::addText($dom, $root, 'MontoTotalImpuesto', self::num($o['monto_total_impuesto']));
    }

    self::addText($dom, $root, 'CodigoActividad',   $o['codigo_actividad']);
    self::addText($dom, $root, 'CondicionImpuesto', $o['condicion_impuesto']);

    if (isset($o['monto_total_impuesto_acreditar'])) {
      self::addText($dom, $root, 'MontoTotalImpuestoAcreditar', self::num($o['monto_total_impuesto_acreditar']));
    }
    if (isset($o['monto_total_gasto_aplicable'])) {
      self::addText($dom, $root, 'MontoTotalDeGastoAplicable', self::num($o['monto_total_gasto_aplicable']));
    }

    self::addText($dom, $root, 'NumeroCedulaReceptor',       $o['receptor_cedula']);
    self::addText($dom, $root, 'NumeroConsecutivoReceptor',  $o['consecutivo_receptor']);

    return $dom->saveXML();
  }

  /**
   * Genera un consecutivo de 20 digitos para el mensaje receptor:
   *   [sucursal 3][terminal 5][tipoDoc 2][consecutivo 10]
   * tipoDoc = '05' (mensaje receptor)
   */
  public static function buildConsecutivoReceptor(int $secuencia, string $sucursal = '001', string $terminal = '00001'): string {
    $sucursal = str_pad($sucursal, 3, '0', STR_PAD_LEFT);
    $terminal = str_pad($terminal, 5, '0', STR_PAD_LEFT);
    $sec      = str_pad((string)$secuencia, 10, '0', STR_PAD_LEFT);
    return $sucursal . $terminal . '05' . $sec;
  }

  private static function addText(DOMDocument $dom, DOMElement $parent, string $name, $value): void {
    $el = $dom->createElementNS(self::NS, $name);
    $el->appendChild($dom->createTextNode((string)$value));
    $parent->appendChild($el);
  }

  private static function num($v): string {
    return number_format((float)$v, 5, '.', '');
  }
}
