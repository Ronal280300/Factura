<?php

class InvoiceXmlParser {

  /**
   * Detecta si una linea de factura es bien o servicio.
   *
   * Reglas (ley IVA Costa Rica):
   *  - Tarifa 10%  → SIEMPRE servicio (solo restaurantes/hoteles pueden cobrar 10%)
   *  - Tarifa 2% o 4% → SIEMPRE bien  (tasas reducidas aplican solo a bienes)
   *  - Tarifa 1% o 13% → heuristica por UnidadMedida:
   *      Sp, Spe, Os, Al, h, Hr, Dia, Mes, Año → servicio
   *      cualquier otra unidad fisica → bien
   */
  private static function detectTipoGasto(float $tarifa, string $unidad): string {
    if ($tarifa == 10.0) return 'servicio';
    if ($tarifa == 4.0 || $tarifa == 2.0) return 'bien';

    $u = strtolower(trim($unidad));
    $serviceUnits = ['sp','spe','os','al','h','hr','hora','horas','dia','mes','año','anio'];
    if (in_array($u, $serviceUnits, true)) return 'servicio';

    return 'bien';
  }

  public static function parse(string $xmlContent): array {
    $xml = new SimpleXMLElement($xmlContent);

    // Namespace default del XML (v4.4 en tu ejemplo)
    $namespaces = $xml->getNamespaces(true);
    $defaultNs = $namespaces[''] ?? null;

    if ($defaultNs) {
      $xml->registerXPathNamespace('fe', $defaultNs);
    }

    $get = function(string $xpath) use ($xml) {
      $nodes = $xml->xpath($xpath);
      if (!$nodes || !isset($nodes[0])) return null;
      return trim((string)$nodes[0]);
    };

    $clave = $get('//fe:Clave') ?? $get('//Clave');
    $fechaEmision = $get('//fe:FechaEmision') ?? $get('//FechaEmision');

    $emisorNombre = $get('//fe:Emisor/fe:Nombre') ?? $get('//Emisor/Nombre');
    $emisorNombreComercial = $get('//fe:Emisor/fe:NombreComercial') ?? $get('//Emisor/NombreComercial');
    $emisorId = $get('//fe:Emisor/fe:Identificacion/fe:Numero') ?? $get('//Emisor/Identificacion/Numero');

    $moneda = $get('//fe:ResumenFactura/fe:CodigoTipoMoneda/fe:CodigoMoneda') ?? $get('//ResumenFactura/CodigoTipoMoneda/CodigoMoneda');
    $tipoCambio = $get('//fe:ResumenFactura/fe:CodigoTipoMoneda/fe:TipoCambio') ?? $get('//ResumenFactura/CodigoTipoMoneda/TipoCambio');

    $totalGravado = $get('//fe:ResumenFactura/fe:TotalGravado') ?? $get('//ResumenFactura/TotalGravado');
    $totalExento  = $get('//fe:ResumenFactura/fe:TotalExento')  ?? $get('//ResumenFactura/TotalExento');
    $totalImpuesto = $get('//fe:ResumenFactura/fe:TotalImpuesto') ?? $get('//ResumenFactura/TotalImpuesto');
    $totalComprobante = $get('//fe:ResumenFactura/fe:TotalComprobante') ?? $get('//ResumenFactura/TotalComprobante');

    // Breakdown por tarifa Y tipo_gasto basado en LineaDetalle
    $breakdown = [];
    $lineas = $xml->xpath('//fe:DetalleServicio/fe:LineaDetalle') ?: $xml->xpath('//DetalleServicio/LineaDetalle') ?: [];

    foreach ($lineas as $ln) {
      $base = (float)($ln->BaseImponible ?? $ln->SubTotal ?? 0);

      $tarifa = 0.00;
      $imp    = 0.0;

      if (isset($ln->Impuesto)) {
        $tarifa = (float)($ln->Impuesto->Tarifa ?? 0);
        $imp    = (float)($ln->Impuesto->Monto  ?? 0);
      }

      $unidad    = (string)($ln->UnidadMedida ?? '');
      $tipoGasto = self::detectTipoGasto($tarifa, $unidad);

      // Clave compuesta: tipo_gasto + tarifa (un bien al 13% y un servicio al 13% son filas separadas)
      $key = $tipoGasto . '_' . number_format($tarifa, 2, '.', '');
      if (!isset($breakdown[$key])) {
        $breakdown[$key] = [
          'tipo_gasto' => $tipoGasto,
          'tarifa'     => (float)$tarifa,
          'base'       => 0.0,
          'impuesto'   => 0.0,
        ];
      }
      $breakdown[$key]['base']     += $base;
      $breakdown[$key]['impuesto'] += $imp;
    }

    return [
      'clave'                  => $clave,
      'fecha_emision'          => $fechaEmision,
      'emisor_nombre'          => $emisorNombre,
      'emisor_nombre_comercial'=> $emisorNombreComercial,
      'emisor_identificacion'  => $emisorId,
      'moneda'                 => $moneda,
      'tipo_cambio'            => $tipoCambio !== null ? (float)$tipoCambio : null,
      'total_gravado'          => $totalGravado    !== null ? (float)$totalGravado    : null,
      'total_exento'           => $totalExento     !== null ? (float)$totalExento     : null,
      'total_impuesto'         => $totalImpuesto   !== null ? (float)$totalImpuesto   : null,
      'total_comprobante'      => $totalComprobante!== null ? (float)$totalComprobante: null,
      'breakdown'              => array_values($breakdown),
    ];
  }
}
