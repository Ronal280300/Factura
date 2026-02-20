<?php

class InvoiceXmlParser {

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

    // Breakdown por tarifa basado en LineaDetalle (robusto)
    $breakdown = [];
    $lineas = $xml->xpath('//fe:DetalleServicio/fe:LineaDetalle') ?: $xml->xpath('//DetalleServicio/LineaDetalle') ?: [];

    foreach ($lineas as $ln) {
      // Nota: si es con namespace, SimpleXML te lo da igual dentro del nodo
      $base = (float)($ln->BaseImponible ?? $ln->SubTotal ?? 0);

      // Puede haber líneas sin impuesto
      $tarifa = 0.00;
      $imp = 0.0;

      if (isset($ln->Impuesto)) {
        $tarifa = (float)($ln->Impuesto->Tarifa ?? 0);
        $imp = (float)($ln->Impuesto->Monto ?? 0);
      }

      $key = number_format($tarifa, 2, '.', '');
      if (!isset($breakdown[$key])) $breakdown[$key] = ['tarifa' => (float)$tarifa, 'base' => 0.0, 'impuesto' => 0.0];
      $breakdown[$key]['base'] += $base;
      $breakdown[$key]['impuesto'] += $imp;
    }

    return [
      'clave' => $clave,
      'fecha_emision' => $fechaEmision,
      'emisor_nombre' => $emisorNombre,
      'emisor_nombre_comercial' => $emisorNombreComercial,
      'emisor_identificacion' => $emisorId,
      'moneda' => $moneda,
      'tipo_cambio' => $tipoCambio !== null ? (float)$tipoCambio : null,
      'total_gravado' => $totalGravado !== null ? (float)$totalGravado : null,
      'total_exento' => $totalExento !== null ? (float)$totalExento : null,
      'total_impuesto' => $totalImpuesto !== null ? (float)$totalImpuesto : null,
      'total_comprobante' => $totalComprobante !== null ? (float)$totalComprobante : null,
      'breakdown' => array_values($breakdown),
    ];
  }
}