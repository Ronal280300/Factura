<?php

/**
 * Parser de comprobantes electronicos de Costa Rica (v4.2 / v4.3 / v4.4).
 *
 * Extrae:
 *  - Tipo de documento (FE/TE/NC/ND/FEE/FEC/REP) desde el elemento raiz.
 *  - Datos generales (Clave, FechaEmision, NumeroConsecutivo).
 *  - Emisor y Receptor completos (incluye CodigoActividad v4.4).
 *  - Moneda + tipo de cambio; convierte montos a CRC.
 *  - LineaDetalle con CABYS, multi-impuestos por linea, exoneraciones.
 *  - InformacionReferencia (para NC/ND que ajustan una FE original).
 *  - Breakdown agregado por tipo_gasto + tarifa + CABYS(primer digito).
 *  - Diferencia entre suma de lineas y ResumenFactura/TotalImpuesto (control).
 */
class InvoiceXmlParser {

  /** Mapeo nombre raiz XML -> codigo interno tipo_documento. */
  private const ROOT_TO_TYPE = [
    'FacturaElectronica'             => 'FE',
    'TiqueteElectronico'             => 'TE',
    'NotaCreditoElectronica'         => 'NC',
    'NotaDebitoElectronica'          => 'ND',
    'FacturaElectronicaExportacion'  => 'FEE',
    'FacturaElectronicaCompra'       => 'FEC',
    'ReciboElectronicoPago'          => 'REP',
  ];

  /**
   * Clasifica bien vs servicio usando el primer digito del CABYS.
   *
   * Catalogo CABYS 2025 (Hacienda CR):
   *   Secciones 1..6 → BIENES (productos agropecuarios, minerales, alimentos,
   *                   textiles, productos metalicos, maquinaria, etc.)
   *   Secciones 7..9 → SERVICIOS (comercio, transporte, hospedaje, financieros,
   *                   profesionales, gubernamentales, etc.)
   *
   * Fallback heuristico (sin CABYS) preservado para XMLs antiguos:
   *  - Tarifa 10% → servicio (solo hoteleria/restaurantes).
   *  - Tarifa 2%/4% → bien (tasas reducidas aplican solo a bienes).
   *  - Resto → se inspecciona UnidadMedida (Sp/Os/Hr/Dia/Mes → servicio).
   */
  private static function detectTipoGasto(?string $cabys, float $tarifa, string $unidad): string {
    $cabys = trim((string)$cabys);
    if ($cabys !== '' && ctype_digit($cabys[0])) {
      $d = (int)$cabys[0];
      if ($d >= 1 && $d <= 6) return 'bien';
      if ($d >= 7 && $d <= 9) return 'servicio';
    }

    if ($tarifa == 10.0) return 'servicio';
    if ($tarifa == 4.0 || $tarifa == 2.0) return 'bien';

    $u = strtolower(trim($unidad));
    $serviceUnits = ['sp','spe','os','al','h','hr','hora','horas','dia','mes','año','anio'];
    if (in_array($u, $serviceUnits, true)) return 'servicio';

    return 'bien';
  }

  public static function parse(string $xmlContent): array {
    $xml = new SimpleXMLElement($xmlContent);

    // Tipo de documento por el nombre del elemento raiz
    $rootName     = $xml->getName();
    $tipoDocumento = self::ROOT_TO_TYPE[$rootName] ?? 'FE';

    $namespaces = $xml->getNamespaces(true);
    $defaultNs  = $namespaces[''] ?? null;
    if ($defaultNs) {
      $xml->registerXPathNamespace('fe', $defaultNs);
    }

    // Helper: obtiene el primer texto de un path tipo "Emisor/Nombre", probando
    // con prefijo fe: en cada segmento y sin prefijo como fallback.
    $get = function(string $path) use ($xml) {
      $segs = explode('/', $path);
      $withNs = '//fe:' . implode('/fe:', $segs);
      $noNs   = '//'    . implode('/',    $segs);
      $nodes = $xml->xpath($withNs) ?: $xml->xpath($noNs);
      if (!$nodes || !isset($nodes[0])) return null;
      $v = trim((string)$nodes[0]);
      return $v === '' ? null : $v;
    };

    // ── Datos generales ──────────────────────────────────────────
    $clave             = $get('Clave');
    $numeroConsecutivo = $get('NumeroConsecutivo');
    $fechaEmision      = $get('FechaEmision');

    // ── Emisor ───────────────────────────────────────────────────
    $emisorNombre          = $get('Emisor/Nombre');
    $emisorNombreComercial = $get('Emisor/NombreComercial');
    $emisorId              = $get('Emisor/Identificacion/Numero');

    // ── Receptor ─────────────────────────────────────────────────
    $receptorNombre          = $get('Receptor/Nombre');
    $receptorTipoId          = $get('Receptor/Identificacion/Tipo');
    $receptorId              = $get('Receptor/Identificacion/Numero');
    // v4.4: CodigoActividadEconomica del receptor (ruta nueva) / v4.3 no la tiene
    $receptorActividad       = $get('Receptor/CodigoActividadEconomica')
                            ?? $get('Receptor/ActividadEconomica');

    // ── Moneda / Tipo Cambio ─────────────────────────────────────
    $moneda     = $get('ResumenFactura/CodigoTipoMoneda/CodigoMoneda') ?? $get('ResumenFactura/CodigoMoneda');
    $tipoCambio = $get('ResumenFactura/CodigoTipoMoneda/TipoCambio')   ?? $get('ResumenFactura/TipoCambio');
    $tc         = $tipoCambio !== null ? (float)$tipoCambio : null;
    if (!$moneda) $moneda = 'CRC';
    if ($moneda === 'CRC' && ($tc === null || $tc <= 0)) $tc = 1.0;

    // ── Totales del ResumenFactura ───────────────────────────────
    $totalGravado     = self::num($get('ResumenFactura/TotalGravado'));
    $totalExento      = self::num($get('ResumenFactura/TotalExento'));
    $totalExonerado   = self::num($get('ResumenFactura/TotalExonerado'));
    $totalImpuesto    = self::num($get('ResumenFactura/TotalImpuesto'));
    $totalComprobante = self::num($get('ResumenFactura/TotalComprobante'));

    // ── InformacionReferencia (NC/ND apuntan a una FE previa) ───
    $claveReferencia = $get('InformacionReferencia/Numero');

    // ── LineaDetalle ─────────────────────────────────────────────
    $lineas = $xml->xpath('//fe:DetalleServicio/fe:LineaDetalle')
           ?: $xml->xpath('//DetalleServicio/LineaDetalle')
           ?: [];

    $breakdown       = [];   // key: tipo_gasto|tarifa|cabys
    $sumImpLineas    = 0.0;  // sum(impuesto neto de lineas) para control

    foreach ($lineas as $ln) {
      $base  = (float)($ln->BaseImponible ?? $ln->SubTotal ?? 0);
      $cabys = isset($ln->CodigoCABYS) ? trim((string)$ln->CodigoCABYS) : null;
      $unidad = (string)($ln->UnidadMedida ?? '');

      // Una linea puede tener 0..N impuestos (IVA + selectivos + otros).
      // Para IVA nos interesan codigos 01 (IVA) / 08 (IVA calc especial).
      // Si no hay <Impuesto>, la linea cuenta como exenta (tarifa 0).
      $impuestosNodo = isset($ln->Impuesto) ? $ln->Impuesto : null;
      $impuestosArr  = [];
      if ($impuestosNodo !== null) {
        // SimpleXML: acceder como lista aunque sea un solo elemento
        foreach ($impuestosNodo as $imp) $impuestosArr[] = $imp;
      }

      if (!$impuestosArr) {
        // Linea exenta/no gravada
        self::acumular($breakdown, $cabys, 0.0, $unidad, $base, 0.0, 0.0);
        continue;
      }

      foreach ($impuestosArr as $imp) {
        $codigo = isset($imp->Codigo) ? trim((string)$imp->Codigo) : '';
        // Consideramos IVA los codigos "01" y "08" (IVA factor); otros los ignoramos
        // para el breakdown de IVA (pero los acumulariamos en otra tabla si hiciera falta).
        if ($codigo !== '' && $codigo !== '01' && $codigo !== '08') continue;

        $tarifa = (float)($imp->Tarifa ?? 0);
        $monto  = (float)($imp->Monto  ?? 0);

        // Exoneracion aplicada a esta linea (si existe)
        $exonerado = 0.0;
        if (isset($imp->Exoneracion)) {
          // v4.4: <MontoImpuesto> dentro de Exoneracion
          $exonerado = (float)($imp->Exoneracion->MontoImpuesto
                           ?? $imp->Exoneracion->MontoExoneracion
                           ?? 0);
        }

        $impNeto = $monto - $exonerado;
        if ($impNeto < 0) $impNeto = 0;
        $sumImpLineas += $impNeto;

        self::acumular($breakdown, $cabys, $tarifa, $unidad, $base, $monto, $exonerado);
      }
    }

    // Control: diferencia entre ResumenFactura y suma de lineas
    $impuestoDiff = null;
    if ($totalImpuesto !== null) {
      $impuestoDiff = round(($totalImpuesto - $totalExonerado - $sumImpLineas), 4);
    }

    // Conversion a CRC de totales del resumen
    $conv = function(?float $v) use ($tc) {
      if ($v === null) return null;
      if ($tc === null || $tc <= 0) return $v;
      return round($v * $tc, 4);
    };

    // Conversion a CRC para cada fila del breakdown
    $breakdownFinal = [];
    foreach ($breakdown as $row) {
      $row['impuesto_neto'] = max(0.0, $row['impuesto'] - $row['exonerado']);
      $row['base_crc']            = $conv($row['base']);
      $row['impuesto_crc']        = $conv($row['impuesto']);
      $row['exonerado_crc']       = $conv($row['exonerado']);
      $row['impuesto_neto_crc']   = $conv($row['impuesto_neto']);
      $breakdownFinal[] = $row;
    }

    return [
      'tipo_documento'         => $tipoDocumento,
      'clave'                  => $clave,
      'clave_referencia'       => $claveReferencia,
      'numero_consecutivo'     => $numeroConsecutivo,
      'fecha_emision'          => $fechaEmision,

      'emisor_nombre'          => $emisorNombre,
      'emisor_nombre_comercial'=> $emisorNombreComercial,
      'emisor_identificacion'  => $emisorId,

      'receptor_nombre'              => $receptorNombre,
      'receptor_identificacion'      => $receptorId,
      'receptor_tipo_identificacion' => $receptorTipoId,
      'receptor_actividad_economica' => $receptorActividad,

      'moneda'                 => $moneda,
      'tipo_cambio'            => $tc,

      'total_gravado'          => $totalGravado,
      'total_exento'           => $totalExento,
      'total_exonerado'        => $totalExonerado,
      'total_impuesto'         => $totalImpuesto,
      'total_comprobante'      => $totalComprobante,

      'total_gravado_crc'      => $conv($totalGravado),
      'total_exento_crc'       => $conv($totalExento),
      'total_exonerado_crc'    => $conv($totalExonerado),
      'total_impuesto_crc'     => $conv($totalImpuesto),
      'total_comprobante_crc'  => $conv($totalComprobante),

      'impuesto_diff'          => $impuestoDiff,
      'breakdown'              => $breakdownFinal,
    ];
  }

  /** Acumula una porcion de linea en el breakdown (clave: tipo_gasto|tarifa|cabys). */
  private static function acumular(
    array &$bd,
    ?string $cabys,
    float $tarifa,
    string $unidad,
    float $base,
    float $impuesto,
    float $exonerado
  ): void {
    $tipoGasto = self::detectTipoGasto($cabys, $tarifa, $unidad);
    $key = $tipoGasto . '|' . number_format($tarifa, 2, '.', '') . '|' . ($cabys ?? '');
    if (!isset($bd[$key])) {
      $bd[$key] = [
        'tipo_gasto' => $tipoGasto,
        'cabys'      => $cabys,
        'tarifa'     => (float)$tarifa,
        'base'       => 0.0,
        'impuesto'   => 0.0,
        'exonerado'  => 0.0,
      ];
    }
    $bd[$key]['base']      += $base;
    $bd[$key]['impuesto']  += $impuesto;
    $bd[$key]['exonerado'] += $exonerado;
  }

  private static function num(?string $v): ?float {
    return $v !== null ? (float)$v : null;
  }
}
